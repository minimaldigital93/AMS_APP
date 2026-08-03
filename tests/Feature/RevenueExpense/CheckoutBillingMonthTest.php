<?php

use App\Models\Accounts;
use App\Models\KhqrPayment;
use App\Models\MonthlyPeriod;
use App\Models\Payments;
use App\Models\Utilities;
use App\Services\RevenueExpense\IncomeRecordingService;
use App\Services\RevenueExpense\KhqrPaymentService;
use Carbon\Carbon;

/**
 * 2026-07 accounting audit (E3 + E5):
 *  - checkout settles the BILLING month's charges (the month the bill page was
 *    showing), never whatever month the server clock happens to be in;
 *  - a KHQR payment finalized after its month was closed re-dates the booking
 *    instead of desyncing the closed month's frozen totals.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => now()->startOfYear()->toDateString(),
        'closing_date' => now()->endOfYear()->toDateString(),
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment, ['rent_amount' => 500]);
    auth()->logout();
});

it('settles the billing month passed with the checkout, not the current server month', function () {
    $lastMonth = now()->subMonthNoOverflow();

    // Unpaid charge billed LAST month + one billed THIS month.
    $old = Utilities::create([
        'tenant_id' => $this->tenant->id, 'rental_id' => $this->rental->id,
        'utility_type' => 'electricity', 'charge_amount' => 40,
        'billing_month' => $lastMonth->month, 'billing_year' => $lastMonth->year,
        'paid_status' => false,
    ]);
    $current = Utilities::create([
        'tenant_id' => $this->tenant->id, 'rental_id' => $this->rental->id,
        'utility_type' => 'electricity', 'charge_amount' => 60,
        'billing_month' => now()->month, 'billing_year' => now()->year,
        'paid_status' => false,
    ]);

    $paymentDate = $lastMonth->copy()->day(25)->toDateString();
    (new IncomeRecordingService(userId: $this->admin->id, period: $this->period))
        ->checkout($this->rental, [
            'payment_date' => $paymentDate,
            'payment_method' => 'cash',
            'pay_utilities' => true,
            'billing_month' => $lastMonth->month,
            'billing_year' => $lastMonth->year,
            'rent_amount' => 0,
        ]);

    // LAST month's charge settled (paid_at = the payment date, not now());
    // this month's untouched.
    expect($old->refresh()->paid_status)->toBeTrue()
        ->and(Carbon::parse($old->paid_at)->toDateString())->toBe($paymentDate)
        ->and($current->refresh()->paid_status)->toBeFalse();

    // Ledger booked exactly the settled amount.
    expect((float) Accounts::where('account_type', 'income')->sum('amount'))->toEqual(40.0);
});

it('re-dates a KHQR finalize whose payment date falls in a since-closed month', function () {
    $lastMonth = now()->subMonthNoOverflow();
    $originalDate = $lastMonth->copy()->day(20)->toDateString();

    // The month has been closed since the QR was generated.
    MonthlyPeriod::create([
        'fiscal_period_id' => $this->period->id, 'user_id' => $this->admin->id,
        'name' => $lastMonth->format('F Y'),
        'month_number' => $lastMonth->month, 'year' => $lastMonth->year,
        'start_date' => $lastMonth->copy()->startOfMonth()->toDateString(),
        'end_date' => $lastMonth->copy()->endOfMonth()->toDateString(),
        'status' => 'closed', 'closed_at' => now(),
    ]);

    $row = KhqrPayment::create([
        'transaction_id' => 'KHQR-CLOSED-1',
        'rental_id' => $this->rental->id,
        'fiscal_period_id' => $this->period->id,
        'user_id' => $this->admin->id,
        'amount' => 500, 'status' => 'waiting_payment',
        'settlement_target' => 'merchant', 'channel' => 'api',
        'checkout_payload' => [
            'pay_rent' => true, 'rent_amount' => 500, 'late_fee' => 0,
            'payment_date' => $originalDate,
        ],
    ]);

    (new KhqrPaymentService)->finalize($row);

    expect($row->refresh()->status)->toBe('paid');

    // Booked on the confirmation date — NOT inside the closed month.
    $payment = Payments::sole();
    expect(Carbon::parse($payment->paid_at)->toDateString())->toBe(now()->toDateString())
        ->and($payment->note)->toContain('closed month');

    $ledger = Accounts::where('account_type', 'income')->sole();
    expect($ledger->transaction_date->toDateString())->toBe(now()->toDateString());
});

/**
 * BOTH sides of a checkout settle the billing month. The charges side always
 * did; rent keyed off the payment date alone, and the checkout form's date
 * field defaults to TODAY — so collecting July's bill on Aug 3 settled July's
 * charges but booked the rent against August. July stayed overdue forever and
 * August read "paid" without a cent collected.
 */
it('anchors back-collected rent in the billed month, not the payment month', function () {
    $lastMonth = now()->subMonthNoOverflow();

    (new IncomeRecordingService(userId: $this->admin->id, period: $this->period))
        ->checkout($this->rental, [
            'payment_date' => now()->toDateString(),   // the form's default
            'payment_method' => 'cash',
            'pay_rent' => true,
            'rent_amount' => 500,
            'billing_month' => $lastMonth->month,      // the month being viewed
            'billing_year' => $lastMonth->year,
        ]);

    $payment = Payments::where('payment_type', 'rent')->sole();

    // The Payments anchor — what every derived rent figure reads — sits in the
    // month the rent was FOR.
    expect(Carbon::parse($payment->paid_at)->month)->toBe($lastMonth->month)
        ->and(Carbon::parse($payment->paid_at)->year)->toBe($lastMonth->year)
        ->and($payment->note)->toContain($lastMonth->format('F Y'));

    // The ledger still recognises the income on the day it was received.
    $ledger = Accounts::where('category', Accounts::CAT_RENT_INCOME)->sole();
    expect($ledger->transaction_date->toDateString())->toBe(now()->toDateString());
});

it('leaves same-month rent dated exactly on the payment date', function () {
    $paymentDate = now()->startOfMonth()->addDays(4)->toDateString();

    (new IncomeRecordingService(userId: $this->admin->id, period: $this->period))
        ->checkout($this->rental, [
            'payment_date' => $paymentDate,
            'payment_method' => 'cash',
            'pay_rent' => true,
            'rent_amount' => 500,
            'billing_month' => now()->month,
            'billing_year' => now()->year,
        ]);

    $payment = Payments::where('payment_type', 'rent')->sole();

    expect(Carbon::parse($payment->paid_at)->toDateString())->toBe($paymentDate)
        ->and($payment->note)->not->toContain('collected');
});

it('anchors prepaid rent inside the month it buys', function () {
    $nextMonth = now()->addMonthNoOverflow();

    (new IncomeRecordingService(userId: $this->admin->id, period: $this->period))
        ->checkout($this->rental, [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'pay_rent' => true,
            'rent_amount' => 500,
            'billing_month' => $nextMonth->month,
            'billing_year' => $nextMonth->year,
        ]);

    $payment = Payments::where('payment_type', 'rent')->sole();

    expect(Carbon::parse($payment->paid_at)->month)->toBe($nextMonth->month)
        ->and(Carbon::parse($payment->paid_at)->year)->toBe($nextMonth->year);
});

/**
 * The checkout modal locks the rent line once the month's rent is in, but a
 * disabled checkbox is simply not posted — a double-click or a stale tab used
 * to book the rent twice. recordBulkRent() has been guarded since the 2026-07
 * audit; the per-tenant path had not.
 */
it('books rent once when the same checkout is posted twice', function () {
    $payload = [
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'pay_rent' => true,
        'rent_amount' => 500,
        'billing_month' => now()->month,
        'billing_year' => now()->year,
    ];

    $service = new IncomeRecordingService(userId: $this->admin->id, period: $this->period);
    $first = $service->checkout($this->rental, $payload);
    $second = $service->checkout($this->rental, $payload);

    expect($first['total_paid'])->toEqual(500.0)
        ->and($first['rent_already_paid'])->toBeFalse()
        ->and($second['total_paid'])->toEqual(0.0)
        ->and($second['rent_already_paid'])->toBeTrue();

    expect(Payments::where('payment_type', 'rent')->count())->toBe(1)
        ->and((float) Accounts::where('category', Accounts::CAT_RENT_INCOME)->sum('amount'))->toEqual(500.0);
});

it('still settles charges on a re-post whose rent line is a duplicate', function () {
    Utilities::create([
        'tenant_id' => $this->tenant->id, 'rental_id' => $this->rental->id,
        'utility_type' => 'water', 'charge_amount' => 25,
        'billing_month' => now()->month, 'billing_year' => now()->year,
        'paid_status' => false,
    ]);

    $service = new IncomeRecordingService(userId: $this->admin->id, period: $this->period);
    $service->checkout($this->rental, [
        'payment_date' => now()->toDateString(), 'payment_method' => 'cash',
        'pay_rent' => true, 'rent_amount' => 500,
        'billing_month' => now()->month, 'billing_year' => now()->year,
    ]);

    // Second visit: meters read since, so charges are new but rent is not.
    $result = $service->checkout($this->rental, [
        'payment_date' => now()->toDateString(), 'payment_method' => 'cash',
        'pay_rent' => true, 'rent_amount' => 500,
        'pay_utilities' => true,
        'billing_month' => now()->month, 'billing_year' => now()->year,
    ]);

    expect($result['total_paid'])->toEqual(25.0)
        ->and($result['rent_already_paid'])->toBeTrue()
        ->and(Payments::where('payment_type', 'rent')->count())->toBe(1);
});

/**
 * End to end through the panel route: the collection page must agree with
 * itself once the money is in — the billed month goes green, and the month the
 * cash happened to arrive in does not.
 */
it('marks the billed month paid on the collection page, not the payment month', function () {
    $lastMonth = now()->subMonthNoOverflow();

    $bill = function (Carbon $when) {
        $res = $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_income', [
            'month' => $when->month, 'year' => $when->year,
        ]));

        return collect($res->viewData('tenantBills')->items())
            ->firstWhere(fn ($b) => $b['rental']->id === $this->rental->id);
    };

    $this->actingAs($this->admin)->post(route('admin.revenue_expense.checkout'), [
        'rental_id' => $this->rental->id,
        'rent_amount' => 500,
        'late_fee' => 0,
        'pay_rent' => 1,
        'billing_month' => $lastMonth->month,
        'billing_year' => $lastMonth->year,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
    ])->assertRedirect();

    expect($bill($lastMonth)['rent_status'])->toBe('paid')
        ->and($bill(now())['rent_status'])->not->toBe('paid');
});
