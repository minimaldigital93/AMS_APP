<?php

use App\Services\Bakong\BakongQrService;

/**
 * Tag 99 — the field whose absence made a structurally perfect QR unscannable.
 *
 * Every other check passed on the payload that failed in the wild: valid TLV,
 * correct lengths, matching CRC-16/CCITT-FALSE. It was rejected because KHQR
 * requires an expiration on any QR carrying an amount, and tag 99 is where that
 * lives. It is not an EMVCo field, which is why it was dropped, and the lesson
 * is that "not EMVCo" and "not required" are different claims.
 */
function tlvParse(string $s): array
{
    $out = [];
    $i = 0;
    while ($i < strlen($s)) {
        $tag = substr($s, $i, 2);
        $len = (int) substr($s, $i + 2, 2);
        $out[$tag] = substr($s, $i + 4, $len);
        $i += 4 + $len;
    }

    return $out;
}

function crcOf(string $body): string
{
    $crc = 0xFFFF;
    foreach (str_split($body) as $ch) {
        $crc ^= ord($ch) << 8;
        for ($i = 0; $i < 8; $i++) {
            $crc = $crc & 0x8000 ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
        }
    }

    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

it('carries a KHQR timestamp with creation and expiration in unix milliseconds', function () {
    $expiry = now()->addMinutes(6);

    $qr = app(BakongQrService::class)->build(
        billNumber: 'SUB-1-TEST',
        amount: 5.99,
        bakongAccountId: 'someone@bkrt',
        expiresAt: $expiry,
    );

    $tags = tlvParse($qr->payload);

    expect($tags)->toHaveKey('99');

    $ts = tlvParse($tags['99']);

    // 13 digits or the reference validator rejects it outright.
    expect($ts['00'])->toHaveLength(13)
        ->and($ts['01'])->toHaveLength(13)
        ->and((int) $ts['01'])->toBe($expiry->getTimestamp() * 1000)
        ->and((int) $ts['01'])->toBeGreaterThan((int) $ts['00']);
});

it('places the timestamp before the CRC, and the CRC still covers it', function () {
    $qr = app(BakongQrService::class)->build(
        billNumber: 'SUB-2-TEST', amount: 1.0, bakongAccountId: 'someone@bkrt',
    );

    // Order matters: the CRC field is always last, so 99 sits between the
    // additional-data field and it. A payload with 99 AFTER 63 has a checksum
    // over the wrong bytes and fails everywhere.
    expect(strpos($qr->payload, '99'))->toBeLessThan(strpos($qr->payload, '6304'))
        ->and(substr($qr->payload, -8, 4))->toBe('6304');

    $body = substr($qr->payload, 0, -4);
    expect(crcOf($body))->toBe(substr($qr->payload, -4));
});

it('never mints a QR that is already expired', function () {
    // A QR born expired scans as invalid, which to the payer is exactly the
    // bug this fixes wearing a different hat.
    $qr = app(BakongQrService::class)->build(
        billNumber: 'SUB-3-TEST', amount: 1.0, bakongAccountId: 'someone@bkrt',
        expiresAt: now()->subHour(),
    );

    $ts = tlvParse(tlvParse($qr->payload)['99']);

    expect((int) $ts['01'])->toBeGreaterThan((int) round(microtime(true) * 1000));
});

it('keeps the md5 taken over the payload that actually ships', function () {
    $qr = app(BakongQrService::class)->build(
        billNumber: 'SUB-4-TEST', amount: 2.5, bakongAccountId: 'someone@bkrt',
    );

    // check_transaction_by_md5 hashes this exact string. If the md5 were taken
    // before tag 99 were appended, every verification would ask Bakong about a
    // transaction that does not exist — and a refusal reads as UNPAID.
    expect($qr->md5)->toBe(md5($qr->payload));
});

it('still parses cleanly as TLV end to end', function () {
    $qr = app(BakongQrService::class)->build(
        billNumber: 'SUB-5-TEST', amount: 12.0, bakongAccountId: 'someone@bkrt',
        merchantName: 'AMS', merchantCity: 'Phnom Penh', currency: 'USD',
    );

    $tags = tlvParse($qr->payload);

    expect($tags['00'])->toBe('01')
        ->and($tags['01'])->toBe('12')
        ->and($tags['53'])->toBe('840')
        ->and($tags['54'])->toBe('12.00')
        ->and($tags['58'])->toBe('KH')
        ->and(tlvParse($tags['29'])['00'])->toBe('someone@bkrt');
});
