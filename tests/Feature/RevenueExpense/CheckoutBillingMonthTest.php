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
