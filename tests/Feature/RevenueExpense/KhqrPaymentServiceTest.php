<?php

use App\Models\Accounts;
use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Models\Payments;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
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

    expect($row->status)->toBe('pending');
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

    expect($row->status)->toBe('pending');
    expect($row->qr_url)->toContain('api.qrserver.com');
    expect($row->provider_ref)->toStartWith('DEMO-');
    Http::assertNothingSent();
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
