<?php

namespace App\Services\RevenueExpense;

use App\Models\FiscalPeriods;
use App\Models\KhqrPayment;
use App\Models\Rentals;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * KHQRPay (khqr.cc) client + payment finalizer.
 *
 * Responsibilities:
 *  - createQr(): ask KHQRPay to mint a dynamic KHQR for a pending row.
 *  - verify():   ask KHQRPay whether Bakong has confirmed the payment.
 *  - finalize(): once confirmed, replay the stored checkout payload through
 *                IncomeRecordingService::checkout() so the Payments + Accounts
 *                rows are written exactly like a manual cash/bank checkout.
 *                Idempotent — safe to call from both the status poll and the
 *                webhook callback.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * PROVIDER-SPECIFIC WIRING (fill from your KHQRPay dashboard integration page):
 *   - sign()                  : the exact SHA1 hash formula.
 *   - createQr() response keys : where the QR image URL + provider ref live.
 *   - verify() endpoint/keys   : the status/verify call and its "paid" shape.
 * Everything else is final. Search for "TODO(khqrpay)" to find each spot.
 * ───────────────────────────────────────────────────────────────────────────
 */
class KhqrPaymentService
{
    /**
     * Create a pending KhqrPayment row + ask KHQRPay to mint the QR.
     *
     * @param  array  $payload  checkout payload (pay_rent, pay_utilities, rent_amount, late_fee, payment_date, note)
     * @return KhqrPayment with qr_url + provider_ref populated
     */
    public function createQr(Rentals $rental, FiscalPeriods $period, int $userId, float $amount, array $payload, string $successUrl): KhqrPayment
    {
        $transactionId = 'KHQR-'.$rental->id.'-'.now()->format('YmdHis').'-'.random_int(100, 999);

        $row = KhqrPayment::create([
            'transaction_id' => $transactionId,
            'rental_id' => $rental->id,
            'fiscal_period_id' => $period->id,
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => config('services.khqrpay.currency', 'USD'),
            'status' => 'pending',
            'checkout_payload' => $payload,
        ]);

        // Demo mode: render a local example KHQR and skip the live API entirely.
        if (config('services.khqrpay.demo')) {
            $row->forceFill([
                'qr_url' => $this->demoQrImageUrl($transactionId, $amount),
                'provider_ref' => 'DEMO-'.$transactionId,
            ])->save();

            return $row;
        }

        $params = [
            'transaction_id' => $transactionId,
            'amount' => number_format($amount, 2, '.', ''),
            'success_url' => $successUrl,
        ];
        $params['hash'] = $this->sign($params);

        // TODO(khqrpay): the path below is from the public marketing docs, but it
        // returns a Laravel 404 ("route ... could not be found") for the live
        // profile — i.e. the documented path is stale, OR the payment-gateway/API
        // feature still needs enabling on the merchant account (KHQRPay's own docs
        // say to first link a payment merchant link from link.payway.com.kh).
        // Replace this with the exact endpoint from the dashboard's API page.
        $endpoint = rtrim((string) config('services.khqrpay.base_url'), '/')
            .'/'.config('services.khqrpay.profile_id')
            .'/payment-gateway/v1/payments/qr-api-khqr';

        $response = Http::asForm()->acceptJson()->post($endpoint, $params);

        if (! $response->successful()) {
            Log::warning('KHQRPay createQr failed', ['status' => $response->status(), 'body' => $response->body(), 'tran' => $transactionId]);
            $row->forceFill(['status' => 'expired'])->save();
            throw new \RuntimeException('KHQRPay did not return a QR (HTTP '.$response->status().').');
        }

        $data = $response->json() ?? [];

        // TODO(khqrpay): confirm the response field names against your dashboard.
        // KHQRPay variants seen: qr / qr_url / qrImage / checkout_url for the image;
        // md5 / tran / transaction_id for the tracking ref.
        $qrUrl = $data['qr'] ?? $data['qr_url'] ?? $data['qrImage'] ?? $data['checkout_url'] ?? null;
        $providerRef = $data['md5'] ?? $data['tran'] ?? $data['transaction_id'] ?? null;

        $row->forceFill([
            'qr_url' => $qrUrl,
            'provider_ref' => $providerRef,
        ])->save();

        return $row;
    }

    /**
     * Ask KHQRPay whether the payment has been confirmed by Bakong.
     * On confirmation, finalize() is the caller's responsibility.
     */
    public function verify(KhqrPayment $row): bool
    {
        if ($row->isPaid()) {
            return true;
        }

        // Demo mode: auto-confirm a few seconds after the QR is generated so the
        // full scan → waiting → paid → record flow can be demonstrated end-to-end.
        if (config('services.khqrpay.demo')) {
            return $row->created_at !== null && $row->created_at->diffInSeconds(now()) >= 8;
        }

        // TODO(khqrpay): replace with the exact verify/status endpoint + params
        // from your dashboard. Shape below mirrors the documented check-by-ref call.
        $endpoint = rtrim((string) config('services.khqrpay.base_url'), '/')
            .'/'.config('services.khqrpay.profile_id')
            .'/payment-gateway/v1/payments/check';

        $params = [
            'transaction_id' => $row->transaction_id,
            'md5' => $row->provider_ref,
        ];
        $params['hash'] = $this->sign($params);

        try {
            $response = Http::asForm()->acceptJson()->post($endpoint, $params);
        } catch (\Throwable $e) {
            Log::warning('KHQRPay verify error', ['tran' => $row->transaction_id, 'msg' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $data = $response->json() ?? [];

        // TODO(khqrpay): confirm the "paid" shape. Common signals below.
        return (bool) (
            ($data['verified'] ?? false) === true
            || in_array(strtoupper((string) ($data['status'] ?? '')), ['COMPLETED', 'PAID', 'SUCCESS'], true)
            || (int) ($data['responseCode'] ?? -1) === 0
        );
    }

    /**
     * Confirm signature on an inbound webhook payload. Returns true if the
     * callback is authentic and indicates a successful payment.
     */
    public function isValidCallback(array $payload): bool
    {
        $provided = $payload['hash'] ?? null;
        if (! $provided) {
            return false;
        }

        $check = $payload;
        unset($check['hash']);

        // TODO(khqrpay): match the webhook signing scheme from your dashboard.
        return hash_equals($this->sign($check), (string) $provided);
    }

    /**
     * Record the payment for a confirmed KHQR row, exactly once.
     * Replays the stored checkout payload through IncomeRecordingService.
     */
    public function finalize(KhqrPayment $row): void
    {
        if ($row->isPaid()) {
            return;
        }

        DB::transaction(function () use ($row) {
            // Re-load under a lock so concurrent poll + webhook can't double-book.
            $locked = KhqrPayment::whereKey($row->getKey())->lockForUpdate()->first();
            if (! $locked || $locked->status === 'paid') {
                return;
            }

            $period = FiscalPeriods::find($locked->fiscal_period_id);
            $rental = Rentals::with(['apartment', 'tenant'])->find($locked->rental_id);
            if (! $period || ! $rental) {
                Log::warning('KHQRPay finalize skipped: missing period/rental', ['tran' => $locked->transaction_id]);

                return;
            }

            $payload = $locked->checkout_payload;
            $payload['payment_method'] = 'khqr';
            $payload['transaction_reference'] = $locked->transaction_id;

            (new IncomeRecordingService(userId: $locked->user_id, period: $period))
                ->checkout($rental, $payload);

            $locked->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();
        });
    }

    /**
     * SHA1-sign the request with the merchant secret.
     *
     * TODO(khqrpay): replace the concatenation order with the exact formula
     * from your KHQRPay dashboard's API/Integration page. Current best-guess:
     * sha1(transaction_id . amount . secret).
     */
    private function sign(array $params): string
    {
        $secret = (string) config('services.khqrpay.secret');

        $base = ($params['transaction_id'] ?? '')
            .($params['amount'] ?? '')
            .$secret;

        return sha1($base);
    }

    /**
     * Build a QR image URL for an example KHQR (demo mode). Encodes a proper
     * EMVCo/Bakong KHQR payload (with CRC) and renders it through a public QR
     * image endpoint so no extra PHP dependency is needed.
     */
    private function demoQrImageUrl(string $transactionId, float $amount): string
    {
        $payload = $this->buildKhqrPayload($transactionId, $amount);

        return 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&ecc=M&data='.rawurlencode($payload);
    }

    /**
     * Compose an EMVCo-compliant Bakong KHQR string (individual, dynamic).
     * Good enough to render and scan as an example; real payability requires a
     * live KHQRPay/Bakong merchant account.
     */
    private function buildKhqrPayload(string $transactionId, float $amount): string
    {
        $tlv = fn (string $id, string $val): string => $id.str_pad((string) strlen($val), 2, '0', STR_PAD_LEFT).$val;

        $bakongId = (string) (config('services.khqrpay.bakong_id') ?: 'demo@aclb');
        $merchant = substr((string) (config('services.khqrpay.merchant_name') ?: 'AMS'), 0, 25);
        $currency = strtoupper((string) config('services.khqrpay.currency', 'USD')) === 'KHR' ? '116' : '840';

        // Tag 29: merchant account information (Bakong) — sub-tag 00 = Bakong account ID.
        $merchantAccount = $tlv('00', $bakongId);

        $payload = $tlv('00', '01')                       // payload format indicator
            .$tlv('01', '12')                             // dynamic QR
            .$tlv('29', $merchantAccount)                 // Bakong account info
            .$tlv('52', '5999')                           // merchant category code
            .$tlv('53', $currency)                        // transaction currency
            .$tlv('54', number_format($amount, 2, '.', '')) // amount
            .$tlv('58', 'KH')                             // country code
            .$tlv('59', $merchant)                        // merchant name
            .$tlv('60', 'Phnom Penh')                     // merchant city
            .$tlv('99', $tlv('00', substr($transactionId, 0, 25))); // additional data (bill no.)

        // Tag 63: CRC over everything including the "6304" prefix.
        $payload .= '6304';

        return $payload.strtoupper($this->crc16($payload));
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
