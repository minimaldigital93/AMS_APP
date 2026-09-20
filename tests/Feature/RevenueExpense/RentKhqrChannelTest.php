<?php

use App\Models\Accounts;
use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Models\Payments;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * TENANT RENT BY KHQR, AFTER khqr.cc.
 *
 * There is no gateway in this flow at all — no client, no signature, no poll,
 * no webhook. A QR is built on this server from the landlord's own Bakong
 * account, the tenant pays into the landlord's bank, and the landlord confirms
 * it after seeing the money arrive. Everything below is about that being true,
 * and staying true.
 *
 * Why manual rather than verifying rent through the platform's Bakong token:
 * that token is metered at roughly 100 requests a day for the WHOLE
 * installation. One busy building's tenants would lock out every other account,
 * and a rent payment's confirmation would be routed through credentials
 * belonging to an account the money never touches.
 */
beforeEach(function () {
    Cache::flush();

    config()->set('rent_qr.demo', false);
    config()->set('rent_qr.currency', 'USD');

    $this->admin = makeAdmin();
    $this->period = makeFiscalPeriod($this->admin);
    $this->apartment = makeApartment(null, ['apartment_number' => 'A-101', 'monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment, ['rent_amount' => 500]);
    // Rent rows resolve the landlord's payment settings through the rental's account.
    $this->rental->forceFill(['account_id' => $this->admin->id])->save();
    $this->service = new KhqrPaymentService;
});

/** Where this landlord's rent lands. No credentials — there is nothing to sign. */
function giveRentPayout(int $accountId, array $overrides = []): MerchantPaymentSetting
{
    $settings = new MerchantPaymentSetting(array_merge([
        'bank_name' => 'Test Bank',
        'bank_account_name' => 'Landlord One',
        'bank_account_number' => '000-111-222',
        'bakong_account_id' => 'landlord@aclb',
        'currency' => 'USD',
    ], $overrides));
    $settings->account_id = $accountId;
    $settings->save();

    return $settings;
}

function makeRentQr(array $payloadOverrides = []): KhqrPayment
{
    return test()->service->createQr(
        rental: test()->rental,
        period: test()->period,
        userId: test()->admin->id,
        amount: 500.0,
        payload: array_merge([
            'pay_rent' => true,
            'rent_amount' => 500,
            'payment_date' => now()->toDateString(),
        ], $payloadOverrides),
    );
}

// ─────────────────────────── minting, locally ───────────────────────────

it('builds the QR here, from the landlord\'s own account, without contacting anyone', function () {
    giveRentPayout($this->admin->id);
    Http::fake();

    $row = makeRentQr();

    expect($row->status)->toBe('qr_generated')
        ->and($row->settlement_target)->toBe('merchant')
        ->and($row->provider)->toBe('manual')
        ->and($row->channel)->toBe('manual')
        ->and($row->qr_payload)->not->toBeEmpty()
        ->and($row->qr_md5)->toBe(md5($row->qr_payload))
        ->and((float) $row->amount)->toEqual(500.0);

    // WHERE THE MONEY GOES is in the payload, and it is the landlord's account
    // — never the platform's. A wrong tag here collects someone else's rent
    // while looking perfectly valid to the tenant.
    expect($row->qr_payload)->toContain('landlord@aclb');

    Http::assertNothingSent();
});

it('renders the QR as an inline data URI, not a third-party image URL', function () {
    giveRentPayout($this->admin->id);
    Http::fake();

    $image = $this->service->qrImage(makeRentQr());

    // The old manual channel handed the payload to api.qrserver.com as a query
    // parameter — putting a live payment instruction, with the landlord's
    // Bakong id and the amount, on someone else's server, and making the QR
    // fail to appear whenever that service was unreachable.
    expect($image)->toStartWith('data:image/svg+xml;base64,')
        ->and($image)->not->toContain('qrserver.com');
});

it('falls back to the landlord\'s uploaded static image when no Bakong id is set', function () {
    giveRentPayout($this->admin->id, ['bakong_account_id' => null, 'khqr_image_path' => 'qr/landlord.png']);
    Http::fake();

    $row = makeRentQr();

    // A static image carries no amount, so the modal prints the figure beside
    // it — but there is still something to show and something to collect.
    expect($row->qr_payload)->toBeNull()
        ->and($row->qr_url)->toContain('qr/landlord.png')
        ->and($this->service->qrImage($row))->toBeNull();
});

it('refuses before creating a row when the landlord can collect nothing', function () {
    // No settings at all. A session nothing can settle is worse than no session:
    // it sits open, shows the tenant a QR pointing nowhere, and is swept by
    // every net that looks for open rows.
    Http::fake();

    expect(fn () => makeRentQr())->toThrow(RuntimeException::class);

    expect(KhqrPayment::count())->toBe(0);
    Http::assertNothingSent();
});

it('never falls back to the platform payout account', function () {
    config()->set('bakong.account_id', 'platform@aclb');
    giveRentPayout($this->admin->id, ['bakong_account_id' => null, 'bank_account_number' => '999']);
    Http::fake();

    $row = makeRentQr();

    // Rent money settles with the LANDLORD. Defaulting to the platform's
    // account would quietly divert every tenant's rent to the SaaS operator.
    expect($row->qr_payload)->toBeNull();
});

// ──────────────────────── confirming, by the landlord ────────────────────────

it('books the payment when the landlord confirms receipt', function () {
    giveRentPayout($this->admin->id);
    Http::fake();

    $row = makeRentQr();
    $this->service->confirmManual($row);

    expect($row->fresh()->status)->toBe('paid')
        ->and($row->fresh()->paid_at)->not->toBeNull()
        ->and(Payments::where('rental_id', $this->rental->id)->count())->toBe(1)
        ->and(Accounts::where('user_id', $this->admin->id)->where('category', Accounts::CAT_RENT_INCOME)->exists())->toBeTrue();

    Http::assertNothingSent();
});

it('is idempotent — confirming twice books the rent once', function () {
    giveRentPayout($this->admin->id);
    Http::fake();

    $row = makeRentQr();
    $this->service->confirmManual($row);
    $this->service->confirmManual($row->fresh());

    expect(Payments::where('rental_id', $this->rental->id)->count())->toBe(1);
});

it('closes the row without booking anything when the landlord rejects it', function () {
    giveRentPayout($this->admin->id);
    Http::fake();

    $row = makeRentQr();
    $this->service->rejectManual($row);

    expect($row->fresh()->status)->toBe('rejected')
        ->and($row->fresh()->isOpen())->toBeFalse()
        ->and(Payments::where('rental_id', $this->rental->id)->count())->toBe(0);
});

// ────────────────────────────── the status poll ──────────────────────────────

it('advances the row on a poll without asking anyone anything', function () {
    giveRentPayout($this->admin->id);
    Http::fake();

    $row = makeRentQr();

    $polled = $this->service->pollAndAdvance($row);

    expect($polled->status)->toBe('waiting_payment')
        // Kept so the three checkout pages share one response shape. The rent
        // channel asks no gateway, so no gateway can refuse it — a true here
        // would make a perfectly healthy checkout look broken.
        ->and($this->service->lastPollRefused())->toBeFalse();

    Http::assertNothingSent();
});

it('expires a row whose window has closed', function () {
    giveRentPayout($this->admin->id);
    Http::fake();

    $row = makeRentQr();
    $row->forceFill(['expires_at' => now()->subMinute()])->save();

    expect($this->service->pollAndAdvance($row)->status)->toBe('expired');
});

it('honours the configured window', function () {
    config()->set('rent_qr.ttl', 45);
    giveRentPayout($this->admin->id);
    Http::fake();

    $row = makeRentQr();

    expect($row->expires_at->diffInMinutes(now()->addMinutes(45), true))->toBeLessThan(2);
});
