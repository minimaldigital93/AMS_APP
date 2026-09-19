<?php

namespace App\Services\Bakong;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Builds and renders the KHQR payload.
 *
 * THIS IS THE PART THE BAKONG OPEN API DOES NOT DO. KHQRPay had a QR endpoint
 * and handed back a hosted PNG; the Open API has no such endpoint at all. The
 * payload is ours to construct, which moves it from a convenience into a
 * money-critical component in two ways at once:
 *
 *  1. THE PAYER PAYS WHAT IT SAYS. A wrong account tag sends money to the wrong
 *     place; a wrong amount collects the wrong sum. There is no gateway
 *     validating it on the way out.
 *  2. THE PAYLOAD IS THE VERIFICATION KEY. check_transaction_by_md5 takes the
 *     md5 of this exact string, so the bytes must be stable between rendering
 *     and checking — which is why the caller STORES the payload rather than
 *     rebuilding it later. See the qr_payload migration.
 *
 * Structure follows the sample QR printed in NBC's own document:
 *
 *   00020101021229190015gg_hh_1980@amkb5204599953038405403168
 *   5802KH5905Gg+Hh6010Phnom+Penh62100806#hello630498B8
 *
 *   00  payload format indicator      "01"
 *   01  point of initiation           "12" = dynamic (amount fixed by us)
 *   29  individual account info       sub-tag 00 = Bakong account id
 *   52  merchant category code        "5999"
 *   53  transaction currency          "840" USD / "116" KHR
 *   54  transaction amount
 *   58  country code                  "KH"
 *   59  merchant name                 <= 25 chars
 *   60  merchant city                 <= 15 chars
 *   62  additional data               sub-tag 01 = bill number
 *   63  CRC-16/CCITT-FALSE over everything including the "6304" prefix
 *
 * ONE DELIBERATE DIFFERENCE FROM THE SAMPLE. NBC's example puts its text in tag
 * 62 sub-tag 08 (purpose of transaction); this builder uses sub-tag 01 (bill
 * number), which is the EMVCo field for exactly what we put there — the
 * transaction id the payment is reconciled against. Both are valid tag-62
 * sub-tags and the choice changes only what a banking app labels the line.
 * Verification is unaffected either way, because the md5 is taken over whatever
 * this method emits, so the QR and the lookup key can never disagree.
 *
 * ALSO DIFFERENT FROM THE OLD KHQRPay BUILDER, which this replaces: that one
 * put the bill number in tag 99 (not an EMVCo additional-data field at all) and
 * formatted every amount to two decimals regardless of currency, which is wrong
 * for KHR — riel has no minor unit, so "54" must carry a whole number.
 */
class BakongQrService
{
    /** EMVCo caps. Exceeding either makes the QR malformed rather than merely long. */
    private const MAX_MERCHANT_NAME = 25;

    private const MAX_MERCHANT_CITY = 15;

    /** EMVCo allows 25 characters of bill number. */
    private const MAX_BILL_NUMBER = 25;

    /**
     * Build a dynamic KHQR for one payment.
     *
     * @param  string  $billNumber  the transaction id this payment reconciles against
     * @param  string  $bakongAccountId  WHERE THE MONEY GOES — the landlord's own id for
     *                                   rent, the platform's for a subscription
     */
    public function build(
        string $billNumber,
        float $amount,
        string $bakongAccountId,
        ?string $merchantName = null,
        ?string $merchantCity = null,
        ?string $currency = null,
    ): BakongQr {
        $bakongAccountId = trim($bakongAccountId);

        if ($bakongAccountId === '') {
            // Refused rather than defaulted. A QR with a placeholder account id
            // is a QR that collects someone else's money, and it would look
            // perfectly valid to the payer.
            throw new \InvalidArgumentException(__('messages.bakong_account_missing'));
        }

        $currency = strtoupper((string) ($currency ?: config('bakong.currency', 'USD')));
        $name = $this->sanitise((string) ($merchantName ?: config('bakong.merchant_name')), self::MAX_MERCHANT_NAME) ?: 'AMS';
        $city = $this->sanitise((string) ($merchantCity ?: config('bakong.merchant_city')), self::MAX_MERCHANT_CITY) ?: 'Phnom Penh';

        $payload = $this->tlv('00', '01')
            .$this->tlv('01', '12')
            .$this->tlv('29', $this->tlv('00', $bakongAccountId))
            .$this->tlv('52', '5999')
            .$this->tlv('53', $this->currencyCode($currency))
            .$this->tlv('54', $this->formatAmount($amount, $currency))
            .$this->tlv('58', 'KH')
            .$this->tlv('59', $name)
            .$this->tlv('60', $city)
            .$this->tlv('62', $this->tlv('01', substr($this->sanitise($billNumber, self::MAX_BILL_NUMBER), 0, self::MAX_BILL_NUMBER)));

        // The checksum covers the "6304" header of its own field, so it is
        // appended before the CRC is computed. Getting this wrong produces a QR
        // that every banking app rejects, which is at least a loud failure.
        $payload .= '6304';

        return BakongQr::fromPayload($payload.strtoupper($this->crc16($payload)));
    }

    /**
     * Render a payload as an inline SVG.
     *
     * SVG rather than PNG deliberately: it needs neither GD nor Imagick, it
     * stays sharp on a phone held up to another phone and on a printed bill,
     * and it can be embedded directly in the page so the payer's browser makes
     * no second request for the thing they are about to pay.
     */
    public function svg(string $payload, int $size = 280): string
    {
        $writer = new Writer(new ImageRenderer(
            // Margin 1 module rather than the EMVCo-recommended 4: the QR sits
            // in a padded card already, and a doubled quiet zone only makes the
            // modules smaller on a phone screen.
            new RendererStyle(max(120, $size), 1),
            new SvgImageBackEnd,
        ));

        // Error correction M, the KHQR convention: enough redundancy to survive
        // a screen reflection or a thumb over one corner, without inflating the
        // module count the way Q or H would.
        return $writer->writeString($payload, 'UTF-8', ErrorCorrectionLevel::M());
    }

    /**
     * The same SVG as a data: URI, for an <img src> or a PDF.
     *
     * A data URI keeps the payload inside the document. The old KHQRPay manual
     * channel built the payload locally and then handed it to a third-party
     * image service (api.qrserver.com) as a URL parameter — which put a live
     * payment instruction, with the merchant's Bakong id and the amount, on
     * someone else's server, and made the QR fail to render whenever that
     * service was unreachable.
     */
    public function dataUri(string $payload, int $size = 280): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($payload, $size));
    }

    /**
     * EMVCo tag-length-value. Length is the BYTE length, zero-padded to two
     * digits — which is also why merchant name and city are capped: a value
     * longer than 99 bytes cannot express its own length.
     */
    private function tlv(string $tag, string $value): string
    {
        return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /** ISO 4217 numeric, the only two currencies Bakong settles in. */
    private function currencyCode(string $currency): string
    {
        return $currency === 'KHR' ? '116' : '840';
    }

    /**
     * Format the amount for tag 54, in the currency's own minor units.
     *
     * USD has two decimals; KHR has NONE. Sending "4000.00" riel is malformed,
     * and the old builder did exactly that for every currency — it formatted to
     * two decimals unconditionally.
     */
    private function formatAmount(float $amount, string $currency): string
    {
        return $currency === 'KHR'
            ? (string) (int) round($amount)
            : number_format($amount, 2, '.', '');
    }

    /**
     * Strip anything that cannot appear in an EMV field and clamp the length.
     *
     * Control characters and multi-byte glyphs break the byte-counted length
     * prefix, which is the failure that produces a QR no scanner will read —
     * and does so only for the accounts whose names happen to contain them.
     */
    private function sanitise(string $value, int $max): string
    {
        // Whitespace first, so a tab between two words leaves a space rather
        // than gluing them together; then anything outside printable ASCII;
        // then collapse whatever that left behind.
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
        $value = preg_replace('/ {2,}/', ' ', $value) ?? $value;

        return substr(trim($value), 0, $max);
    }

    /** CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF) — the KHQR checksum. */
    private function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;

            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return str_pad(dechex($crc), 4, '0', STR_PAD_LEFT);
    }
}
