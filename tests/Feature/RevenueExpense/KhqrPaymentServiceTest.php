<?php

use App\Models\Accounts;
use App\Models\KhqrPayment;
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
    $this->service = new KhqrPaymentService;
});

it('creates a pending KhqrPayment row and stores the returned QR url', function () {
    Http::fake([
        'khqr.cc/*' => Http::response([
            'status' => 'success',
            'qr' => 'https://khqr.cc/qr/abc123.png',
            'md5' => 'deadbeef',
        ], 200),
    ]);

    $row = $this->service->createQr(
        rental: $this->rental,
        period: $this->period,
        userId: $this->admin->id,
        amount: 500.0,
        payload: ['pay_rent' => true, 'rent_amount' => 500, 'payment_date' => now()->toDateString()],
        successUrl: 'https://app.test/done',
    );

    expect($row->status)->toBe('pending');
    expect($row->qr_url)->toBe('https://khqr.cc/qr/abc123.png');
    expect($row->provider_ref)->toBe('deadbeef');
    expect((float) $row->amount)->toEqual(500.0);
    expect(KhqrPayment::where('status', 'pending')->count())->toBe(1);
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
        successUrl: 'https://app.test/done',
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
