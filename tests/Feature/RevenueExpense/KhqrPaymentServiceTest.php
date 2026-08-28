<?php

use App\Models\Accounts;
use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Models\Payments;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // The array store lives for the whole process, so the verify cooldown and
    // the daily call counter would otherwise carry over between tests here.
    Cache::flush();

    config()->set('services.khqrpay.base_url', 'https://khqr.cc');
    config()->set('services.khqrpay.profile_id', 'profile123');
    config()->set('services.khqrpay.secret', 'test-secret');
    config()->set('services.khqrpay.currency', 'USD');
    config()->set('services.khqrpay.demo', false);

    $this->admin = makeAdmin();
    $this->period = makeFiscalPeriod($this->admin);
    $this->apartment = makeApartment(null, ['apartment_number' => 'A-101', 'monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment, ['rent_amount' => 500]);
    // Rent rows resolve the landlord's payment settings through the rental's account.
    $this->rental->forceFill(['account_id' => $this->admin->id])->save();
    $this->service = new KhqrPaymentService;
});

/** The landlord's own KHQRPay credentials — Flow B settles to the merchant. */
function givePaymentSettings(int $accountId, array $overrides = []): MerchantPaymentSetting
{
    $settings = new MerchantPaymentSetting(array_merge([
        'bank_name' => 'Test Bank',
        'bank_account_name' => 'Landlord One',
        'bank_account_number' => '000-111-222',
        'khqrpay_enabled' => true,
        'khqrpay_profile_id' => 'merchant-profile',
        'khqrpay_secret' => 'merchant-secret',
        'currency' => 'USD',
    ], $overrides));
    $settings->account_id = $accountId;
    $settings->save();

    return $settings;
}

it('creates a pending api-channel row with the MERCHANT credentials and stores the QR url', function () {
    givePaymentSettings($this->admin->id);

    Http::fake([
        'khqr.cc/*' => Http::response([
            'responseCode' => 0,
            'responseMessage' => 'Success',
            'data' => [
                'transaction_id' => 'KHQR-1',
                'amount' => 500.00,
                'qr' => 'https://khqr.cc/qr/abc123.png',
                'qr_url' => 'https://khqr.cc/qr/abc123.png',
                'md5' => 'deadbeef',
                'req_time' => time(),
                'hash' => 'abc123hash',
            ],
        ], 200),
    ]);

    $row = $this->service->createQr(
        rental: $this->rental,
        period: $this->period,
        userId: $this->admin->id,
        amount: 500.0,
        payload: ['pay_rent' => true, 'rent_amount' => 500, 'payment_date' => now()->toDateString()],
    );

    expect($row->status)->toBe('qr_generated'); // QR minted → awaiting payment
    expect($row->settlement_target)->toBe('merchant');
    expect($row->channel)->toBe('api');
    expect($row->qr_url)->toBe('https://khqr.cc/qr/abc123.png');
    expect($row->provider_ref)->toBe('deadbeef');
    expect((float) $row->amount)->toEqual(500.0);

    // The QR must be minted against the LANDLORD's profile, not the platform's.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/merchant-profile/'));
});

it('falls back to the manual channel when the landlord has no API credentials', function () {
    givePaymentSettings($this->admin->id, ['khqrpay_enabled' => false, 'khqrpay_profile_id' => null, 'khqrpay_secret' => null]);
    Http::fake();

    $row = $this->service->createQr(
        rental: $this->rental,
        period: $this->period,
        userId: $this->admin->id,
        amount: 500.0,
        payload: ['pay_rent' => true, 'rent_amount' => 500, 'payment_date' => now()->toDateString()],
    );

    expect($row->channel)->toBe('manual');
    expect($row->settlement_target)->toBe('merchant');
    expect($this->service->verify($row))->toBeFalse(); // never auto-confirms
    Http::assertNothingSent();

    // The landlord confirms by hand → payment is booked exactly once.
    $this->service->confirmManual($row);
    $row->refresh();
    expect($row->status)->toBe('paid');
    expect(Payments::count())->toBe(1);
});

it('refuses to mint a rent QR when the landlord has no payment settings', function () {
    Http::fake();

    $this->service->createQr(
        rental: $this->rental,
        period: $this->period,
        userId: $this->admin->id,
        amount: 500.0,
        payload: ['pay_rent' => true, 'rent_amount' => 500, 'payment_date' => now()->toDateString()],
    );
})->throws(RuntimeException::class);

it('rejecting a manual payment never books it', function () {
    givePaymentSettings($this->admin->id, ['khqrpay_enabled' => false, 'khqrpay_profile_id' => null, 'khqrpay_secret' => null]);
    Http::fake();

    $row = $this->service->createQr(
        rental: $this->rental,
        period: $this->period,
        userId: $this->admin->id,
        amount: 500.0,
        payload: ['pay_rent' => true, 'rent_amount' => 500, 'payment_date' => now()->toDateString()],
    );

    $this->service->rejectManual($row);
    $row->refresh();
    expect($row->status)->toBe('rejected');

    // A rejected row can no longer be finalized.
    $this->service->finalize($row);
    expect(Payments::count())->toBe(0);
});

it('demo mode builds a local example QR without calling the live API', function () {
    config()->set('services.khqrpay.demo', true);
    Http::fake(); // any outbound HTTP would record here

    $row = $this->service->createQr(
        rental: $this->rental,
        period: $this->period,
        userId: $this->admin->id,
        amount: 12.50,
        payload: ['pay_rent' => true, 'rent_amount' => 12.50, 'payment_date' => now()->toDateString()],
    );

    expect($row->status)->toBe('qr_generated'); // demo QR minted → awaiting payment
    expect($row->qr_url)->toContain('api.qrserver.com');
    expect($row->provider_ref)->toStartWith('DEMO-');
    Http::assertNothingSent();
});

it('verify does NOT confirm an api payment that has not actually settled', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'SUB-VERIFY-1',
        'subscription_id' => null,
        'amount' => 500,
        'currency' => 'USD',
        'status' => 'pending',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'provider_ref' => 'deadbeef',
        'checkout_payload' => ['type' => 'subscription'],
    ]);

    // The status query succeeded (responseCode 0) but the money has NOT arrived.
    // The old code treated responseCode 0 as "paid" and auto-confirmed here.
    Http::fake([
        'khqr.cc/*' => Http::response([
            'responseCode' => 0,
            'responseMessage' => 'Success',
            'data' => ['transaction_id' => 'SUB-VERIFY-1', 'status' => 'PENDING'],
        ], 200),
    ]);

    expect($this->service->verify($row))->toBeFalse();
});

it('verify confirms an api payment only once the status reads PAID', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'SUB-VERIFY-2',
        'subscription_id' => null,
        'amount' => 500,
        'currency' => 'USD',
        'status' => 'pending',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'provider_ref' => 'deadbeef',
        'checkout_payload' => ['type' => 'subscription'],
    ]);

    Http::fake([
        'khqr.cc/*' => Http::response([
            'responseCode' => 0,
            'responseMessage' => 'Success',
            'data' => ['transaction_id' => 'SUB-VERIFY-2', 'status' => 'PAID'],
        ], 200),
    ]);

    expect($this->service->verify($row))->toBeTrue();
});

it('logs a provider refusal once per transaction instead of silently reading unpaid', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'SUB-VERIFY-3',
        'subscription_id' => null,
        'amount' => 500,
        'currency' => 'USD',
        'status' => 'pending',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'provider_ref' => 'deadbeef',
        'checkout_payload' => ['type' => 'subscription'],
    ]);

    // The refusal a lapsed Bakong OpenAPI token gives on EVERY poll: without a
    // log line the QR just expires with no trace of why it never confirmed.
    Http::fake([
        'khqr.cc/*' => Http::response([
            'responseCode' => 1,
            'responseMessage' => 'Bakong Token Required: No active official Bakong OpenAPI token configured.',
        ], 200),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $msg, array $ctx) => $msg === 'KHQRPay verify refused'
            && $ctx['tran'] === 'SUB-VERIFY-3'
            && $ctx['code'] === 1
            && str_contains($ctx['message'], 'Bakong Token Required'));
    Log::shouldReceive('info', 'debug')->zeroOrMoreTimes();

    // Three polls, one warning: the poll runs every few seconds for the QR's
    // whole life, so an unlatched log would flood.
    foreach (range(1, 3) as $_) {
        Cache::forget('khqr:verify:'.$row->transaction_id); // skip the verify cooldown
        expect($this->service->verify($row))->toBeFalse();
    }
});

it('finalize records Payments + Accounts exactly once (idempotent)', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'KHQR-TEST-1',
        'rental_id' => $this->rental->id,
        'fiscal_period_id' => $this->period->id,
        'user_id' => $this->admin->id,
        'amount' => 500,
        'currency' => 'USD',
        'status' => 'pending',
        'checkout_payload' => [
            'pay_rent' => true,
            'pay_utilities' => false,
            'rent_amount' => 500,
            'late_fee' => 0,
            'payment_date' => now()->toDateString(),
            'note' => null,
        ],
    ]);

    $this->service->finalize($row);
    $this->service->finalize($row->fresh()); // second call must be a no-op

    expect(Payments::count())->toBe(1);
    expect(Accounts::where('category', Accounts::CAT_RENT_INCOME)->count())->toBe(1);

    $row->refresh();
    expect($row->status)->toBe('paid');
    expect($row->paid_at)->not->toBeNull();

    $payment = Payments::sole();
    expect($payment->payment_method)->toBe('khqr');
    expect($payment->transaction_reference)->toBe('KHQR-TEST-1');
});

/**
 * The Bakong token is rated per DAY, and this poll runs every few seconds for a
 * QR's whole lifetime — so what one refusal costs is a quota question, not a
 * cosmetic one. Laravel's retry() rethrows any non-2xx to trigger the next
 * attempt unless a `when` callback says otherwise, and `throw: false` only
 * suppresses the FINAL exception. Without the callback a 502 (the
 * unprovisioned-profile signature on this integration) quietly cost two
 * requests per verify and halved the usable quota.
 */
it('spends exactly one Bakong request on a gateway error response, never retrying it', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'SUB-RETRY-1',
        'subscription_id' => null,
        'amount' => 500,
        'currency' => 'USD',
        'status' => 'pending',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'provider_ref' => 'deadbeef',
        'checkout_payload' => ['type' => 'subscription'],
    ]);

    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        return Http::response(['responseCode' => 1, 'responseMessage' => 'Bad Gateway'], 502);
    });

    expect($this->service->verify($row))->toBeFalse(); // an error still reads "unpaid"
    expect($attempts)->toBe(1);
});

/** A connection blip is the one case still worth a second attempt. */
it('still retries a failed connection, which costs the gateway nothing', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'SUB-RETRY-2',
        'subscription_id' => null,
        'amount' => 500,
        'currency' => 'USD',
        'status' => 'pending',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'provider_ref' => 'deadbeef',
        'checkout_payload' => ['type' => 'subscription'],
    ]);

    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('connection timed out');
    });

    expect($this->service->verify($row))->toBeFalse();
    expect($attempts)->toBe(2);
});

/**
 * A successful verify logs nothing and only the provider knows the running
 * total, so before this counter existed the only way to find out where a day's
 * Bakong quota went was to read the code. The counter is what makes the spend a
 * query instead of an audit — it has to survive the cooldown, count refusals,
 * and split the two credential sets apart.
 */
it('counts every live Bakong request it spends, split by settlement target', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'SUB-COUNT-1',
        'subscription_id' => null,
        'amount' => 500,
        'currency' => 'USD',
        'status' => 'pending',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'provider_ref' => 'deadbeef',
        'checkout_payload' => ['type' => 'subscription'],
    ]);

    Http::fake(fn () => Http::response(['responseCode' => 1, 'responseMessage' => 'not found'], 200));

    expect(KhqrPaymentService::providerCallsOn())->toBe(0);

    $this->service->verify($row);

    expect(KhqrPaymentService::providerCallsOn())->toBe(1);
    expect(KhqrPaymentService::providerCallsOn('platform'))->toBe(1);
    // A landlord's token is metered separately from the platform's.
    expect(KhqrPaymentService::providerCallsOn('merchant'))->toBe(0);
});

/**
 * The cooldown is the thing standing between a 3-second poll and the daily
 * quota, so the counter must measure calls that actually left the building —
 * not verify() invocations. If these two ever diverge the number stops meaning
 * anything.
 */
it('does not count a verify answered from the cooldown cache', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'SUB-COUNT-2',
        'subscription_id' => null,
        'amount' => 500,
        'currency' => 'USD',
        'status' => 'pending',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'provider_ref' => 'deadbeef',
        'checkout_payload' => ['type' => 'subscription'],
    ]);

    Http::fake(fn () => Http::response(['responseCode' => 1, 'responseMessage' => 'not found'], 200));

    $this->service->verify($row); // live call
    $this->service->verify($row); // served from the cooldown window
    $this->service->verify($row);

    expect(KhqrPaymentService::providerCallsOn())->toBe(1);
});

/** A refused request still spent the quota, so it still counts. */
it('counts a request the gateway refused', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'SUB-COUNT-3',
        'subscription_id' => null,
        'amount' => 500,
        'currency' => 'USD',
        'status' => 'pending',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'provider_ref' => 'deadbeef',
        'checkout_payload' => ['type' => 'subscription'],
    ]);

    Http::fake(fn () => Http::response(['responseCode' => 1], 502));

    $this->service->verify($row);

    expect(KhqrPaymentService::providerCallsOn())->toBe(1);
});
