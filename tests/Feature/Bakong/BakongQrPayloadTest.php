<?php

use App\Services\Bakong\BakongQr;
use App\Services\Bakong\BakongQrService;

/**
 * THE PAYLOAD IS THE PRODUCT, AND THE KEY.
 *
 * The Bakong Open API has no QR endpoint, so this string is not a convenience —
 * it is both the instruction the payer's bank acts on and the lookup key
 * verification uses (check_transaction_by_md5 takes the md5 of exactly this).
 * A malformed payload either collects nothing or collects it for the wrong
 * account, and there is no gateway in between to catch it.
 *
 * So these are pinned test vectors, not smoke tests. The strongest of them
 * comes from NBC's own document: the sample QR printed in section 4.2 carries
 * its own CRC, so reproducing that checksum proves the algorithm AND the
 * reading of the payload structure at once.
 */
beforeEach(function () {
    config()->set('bakong.merchant_name', 'AMS');
    config()->set('bakong.merchant_city', 'Phnom Penh');
    config()->set('bakong.currency', 'USD');
});

function bakongCrc(string $body): string
{
    $method = new ReflectionMethod(BakongQrService::class, 'crc16');
    $method->setAccessible(true);

    return strtoupper($method->invoke(new BakongQrService, $body));
}

/**
 * Parse the TOP-LEVEL tags of an EMV payload into tag => value.
 *
 * Substring assertions are not good enough here: "99" occurs inside the
 * merchant category code 5999, and "62" inside plenty of amounts. Only a real
 * walk of the tag-length-value structure can say which tags a payload actually
 * carries.
 *
 * @return array<string, string>
 */
function bakongParseTlv(string $payload): array
{
    $tags = [];
    $i = 0;

    while ($i + 4 <= strlen($payload)) {
        $tag = substr($payload, $i, 2);
        $len = (int) substr($payload, $i + 2, 2);
        $tags[$tag] = substr($payload, $i + 4, $len);
        $i += 4 + $len;
    }

    return $tags;
}

/** Re-derive the checksum of a finished payload and compare it to the one it carries. */
function bakongCrcIsValid(string $payload): bool
{
    return bakongCrc(substr($payload, 0, -4)) === substr($payload, -4);
}

// ═══════════════════════════ the checksum ═══════════════════════════

it('implements CRC-16/CCITT-FALSE, checked against its canonical value', function () {
    // The published check value for this algorithm. If this drifts, every QR
    // this app produces becomes unscannable — loudly, at least.
    expect(bakongCrc('123456789'))->toBe('29B1');
});

it('reproduces the checksum of the sample QR printed in the NBC document', function () {
    // "Bakong OpenAPI Documentation" v1.0.2, section 4.2, Generate Deeplink
    // sample request. The document renders the spaces in the merchant name and
    // city as "+", the way a URL-encoded value would be; read as literal plus
    // signs the checksum does not match, and read as spaces it matches exactly.
    // That is itself the finding — it pins how the sample is to be read.
    $sample = '00020101021229190015gg_hh_1980@amkb52045999530384054031685802KH'
        .'5905Gg Hh6010Phnom Penh62100806#hello630498B8';

    expect(bakongCrcIsValid($sample))->toBeTrue()
        ->and(bakongCrc(substr($sample, 0, -4)))->toBe('98B8');
});

// ═══════════════════════════ the structure ═══════════════════════════

it('builds a dynamic KHQR whose fields match the documented sample', function () {
    $qr = (new BakongQrService)->build('SUB-42-001', 12.50, 'ams_test@devb', 'AMS Rentals', 'Phnom Penh', 'USD');
    $tags = bakongParseTlv($qr->payload);

    expect($tags['00'])->toBe('01')                      // payload format indicator
        ->and($tags['01'])->toBe('12')                   // dynamic, not static
        ->and($tags['29'])->toBe('0013ams_test@devb')    // sub-tag 00: where the money goes
        ->and($tags['52'])->toBe('5999')                 // merchant category code
        ->and($tags['53'])->toBe('840')                  // USD
        ->and($tags['54'])->toBe('12.50')                // amount, two decimals
        ->and($tags['58'])->toBe('KH')                   // country
        ->and($tags['59'])->toBe('AMS Rentals')          // merchant name
        ->and($tags['60'])->toBe('Phnom Penh')           // merchant city
        ->and($tags['62'])->toBe('0110SUB-42-001')       // sub-tag 01: the bill number
        ->and(bakongCrcIsValid($qr->payload))->toBeTrue();
});

it('carries the bill number in tag 62, not tag 99', function () {
    // The KHQRPay builder this replaces used tag 99, which is not an EMVCo
    // additional-data field at all.
    $qr = (new BakongQrService)->build('RENT-7-002', 300.0, 'landlord@aclb');
    $tags = bakongParseTlv($qr->payload);

    expect($tags)->not->toHaveKey('99')
        ->and($tags['62'])->toBe('0110RENT-7-002');
});

it('writes riel as a whole number, because KHR has no minor unit', function () {
    $khr = (new BakongQrService)->build('SUB-43', 4000.0, 'ams_test@devb', 'AMS', 'Phnom Penh', 'KHR');

    // "5303116" = KHR, then "5404 4000" — NOT "4000.00". The old builder
    // formatted every amount to two decimals regardless of currency, which
    // produces a malformed riel QR.
    expect($khr->payload)->toContain('5303116')
        ->toContain('54044000')
        ->not->toContain('4000.00')
        ->and(bakongCrcIsValid($khr->payload))->toBeTrue();

    $usd = (new BakongQrService)->build('SUB-44', 4000.0, 'ams_test@devb', 'AMS', 'Phnom Penh', 'USD');
    expect($usd->payload)->toContain('5303840')->toContain('54074000.00');
});

// ═══════════════════════ refusing to guess ═══════════════════════

it('refuses to build a QR with no account to pay into', function () {
    // A placeholder account id would produce a QR that looks perfectly valid to
    // the payer and collects someone else's money. Refused, never defaulted.
    foreach (['', '   '] as $blank) {
        expect(fn () => (new BakongQrService)->build('SUB-1', 10.0, $blank))
            ->toThrow(InvalidArgumentException::class);
    }
});

it('keeps every field inside the length its own prefix can express', function () {
    $qr = (new BakongQrService)->build(
        str_repeat('B', 60),
        10.0,
        'ams_test@devb',
        str_repeat('N', 60),
        str_repeat('C', 60),
    );

    // EMV length prefixes are two digits, so a value over 99 bytes cannot state
    // its own length. Names are capped at 25, cities at 15, bill numbers at 25.
    expect($qr->payload)->toContain('5925'.str_repeat('N', 25))
        ->toContain('6015'.str_repeat('C', 15))
        ->toContain('0125'.str_repeat('B', 25))
        ->and(bakongCrcIsValid($qr->payload))->toBeTrue();
});

it('strips characters that would break the byte-counted length prefix', function () {
    // A multi-byte glyph or a control character makes strlen() and the visible
    // length disagree, producing a QR no scanner will read — and only for the
    // accounts whose names happen to contain one.
    $qr = (new BakongQrService)->build('SUB-1', 10.0, 'ams_test@devb', "Sok\tSophea ផ្ទះ", 'Phnom Penh');

    expect(bakongParseTlv($qr->payload)['59'])->toBe('Sok Sophea')
        ->and(bakongCrcIsValid($qr->payload))->toBeTrue()
        ->and(mb_check_encoding($qr->payload, 'ASCII'))->toBeTrue();
});

// ═══════════════════════ the payload is the key ═══════════════════════

it('binds the md5 to the exact string it was taken from', function () {
    $qr = (new BakongQrService)->build('SUB-45', 25.0, 'ams_test@devb');

    expect($qr->md5)->toBe(md5($qr->payload))->toHaveLength(32);

    // The pair travels as one object precisely so they cannot drift apart: an
    // md5 taken from a different string silently asks Bakong about a payment
    // nobody made.
    expect(BakongQr::fromPayload('anything')->md5)->toBe(md5('anything'));
});

it('is byte-stable for identical inputs', function () {
    // The payload is stored rather than rebuilt for verification, but it must
    // still be deterministic: a builder that varied per call (a timestamp, a
    // random field) would make two QRs for one payment unverifiable.
    $a = (new BakongQrService)->build('SUB-46', 99.99, 'ams_test@devb', 'AMS', 'Phnom Penh', 'USD');
    $b = (new BakongQrService)->build('SUB-46', 99.99, 'ams_test@devb', 'AMS', 'Phnom Penh', 'USD');

    expect($a->payload)->toBe($b->payload)->and($a->md5)->toBe($b->md5);
});

it('changes the md5 when anything that matters changes', function () {
    $svc = new BakongQrService;
    $base = $svc->build('SUB-47', 10.0, 'ams_test@devb');

    $variants = [
        'amount' => $svc->build('SUB-47', 10.01, 'ams_test@devb'),
        'account' => $svc->build('SUB-47', 10.0, 'other@devb'),
        'bill number' => $svc->build('SUB-48', 10.0, 'ams_test@devb'),
    ];

    foreach ($variants as $what => $variant) {
        expect($variant->md5)->not->toBe($base->md5, $what);
    }
});

// ═══════════════════════════ rendering ═══════════════════════════

it('renders the QR locally, without reaching any image service', function () {
    $qr = (new BakongQrService)->build('SUB-49', 10.0, 'ams_test@devb');
    $svg = (new BakongQrService)->svg($qr->payload);

    expect($svg)->toContain('<svg')->toContain('</svg>');

    // The KHQRPay manual channel handed the payload to api.qrserver.com as a
    // URL parameter — a live payment instruction, with the merchant's account
    // and the amount, on someone else's server, and a QR that failed to render
    // whenever that service was unreachable.
    // The only URL in the output is the SVG namespace declaration; nothing is
    // fetched, so the QR renders with no network at all.
    expect($svg)->not->toContain('qrserver')
        ->not->toContain('<image')
        ->and(substr_count($svg, 'http'))->toBe(substr_count($svg, 'http://www.w3.org/2000/svg'));
});

it('offers the QR as a self-contained data URI', function () {
    $qr = (new BakongQrService)->build('SUB-50', 10.0, 'ams_test@devb');
    $uri = (new BakongQrService)->dataUri($qr->payload);

    expect($uri)->toStartWith('data:image/svg+xml;base64,');

    $decoded = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
    expect($decoded)->toContain('<svg');
});
