<?php

use App\Models\Accounts;
use App\Models\Payments;
use App\Models\Utilities;
use App\Services\RevenueExpense\IncomeRecordingService;

beforeEach(function () {
    $this->admin = makeAdmin();
    $this->period = makeFiscalPeriod($this->admin);
    $this->apartment = makeApartment(null, ['apartment_number' => 'A-101', 'monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment, ['rent_amount' => 500]);
    $this->service = new IncomeRecordingService(userId: $this->admin->id, period: $this->period);
});

it('records rent payment with paired Accounts entry', function () {
    $this->service->recordPayment($this->rental, [
        'amount' => 500,
        'transaction_date' => '2026-05-01',
        'payment_method' => 'cash',
        'payment_type' => 'rent',
        'late_fee' => 0,
        'transaction_reference' => null,
        'note' => null,
    ]);

    expect(Payments::count())->toBe(1);
    expect(Accounts::count())->toBe(1);

    $payment = Payments::sole();
    $account = Accounts::sole();

    expect($payment->amount)->toEqual(500);
    expect($payment->payment_type)->toBe('rent');
    expect($account->payment_id)->toBe($payment->id);
    expect($account->fiscal_period_id)->toBe($this->period->id);
    expect($account->category)->toBe(Accounts::CAT_RENT_INCOME);
    expect($account->account_type)->toBe(Accounts::TYPE_INCOME);
});

it('records payment without crashing when the rental apartment was soft-deleted', function () {
    // Apartments use SoftDeletes, and soft-deleting does NOT fire the DB
    // onDelete('cascade') to rentals — so $rental->apartment legitimately
    // resolves to null. The ledger description must degrade to "Apt N/A"
    // instead of throwing "Attempt to read property on null".
    $this->apartment->delete();

    $rental = $this->rental->fresh();
    expect($rental->apartment)->toBeNull();

    $this->service->recordPayment($rental, [
        'amount' => 500,
        'transaction_date' => '2026-05-01',
        'payment_method' => 'cash',
        'payment_type' => 'rent',
        'late_fee' => 0,
        'transaction_reference' => null,
        'note' => null,
    ]);

    expect(Accounts::count())->toBe(1);
    expect(Accounts::sole()->description)->toContain('[Apt N/A]');
});

it('records a separate late-fee Accounts row when late_fee > 0', function () {
    $this->service->recordPayment($this->rental, [
        'amount' => 500,
        'transaction_date' => '2026-05-15',
        'payment_method' => 'cash',
        'payment_type' => 'rent',
        'late_fee' => 25,
        'transaction_reference' => null,
        'note' => null,
    ]);

    expect(Accounts::count())->toBe(2);
    expect(Accounts::where('category', Accounts::CAT_RENT_INCOME)->count())->toBe(1);
    expect(Accounts::where('category', Accounts::CAT_LATE_FEE_INCOME)->count())->toBe(1);
});

it('checkout creates Payments + Accounts for rent and settles utilities atomically', function () {
    Utilities::create([
        'tenant_id' => $this->tenant->id,
        'rental_id' => $this->rental->id,
        'utility_type' => 'electricity',
        'charge_amount' => 30,
        'billing_month' => now()->month,
        'billing_year' => now()->year,
        'paid_status' => false,
    ]);

    $result = $this->service->checkout($this->rental, [
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'pay_rent' => true,
        'rent_amount' => 500,
        'pay_utilities' => true,
        'late_fee' => 0,
    ]);

    expect($result['total_paid'])->toEqual(530.0);

    expect(Payments::count())->toBe(2);
    expect(Accounts::where('category', Accounts::CAT_RENT_INCOME)->count())->toBe(1);
    expect(Accounts::where('category', Accounts::CAT_UTILITY_INCOME)->count())->toBe(1);
    expect(Utilities::where('paid_status', true)->count())->toBe(1);
});

it('rolls the whole checkout back when a write fails mid-flow', function () {
    Utilities::create([
        'tenant_id' => $this->tenant->id,
        'rental_id' => $this->rental->id,
        'utility_type' => 'electricity',
        'charge_amount' => 30,
        'billing_month' => now()->month,
        'billing_year' => now()->year,
        'paid_status' => false,
    ]);

    // Force a failure after the rent rows are written by deleting the rental's
    // apartment foreign key target — utility settlement reads $rental->apartment
    // for the description, but only after Payment+Accounts for rent are in.
    \App\Models\Accounts::saving(function ($account) {
        if ($account->category === \App\Models\Accounts::CAT_UTILITY_INCOME) {
            throw new RuntimeException('forced failure during utility settlement');
        }
    });

    try {
        $this->service->checkout($this->rental, [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'pay_rent' => true,
            'rent_amount' => 500,
            'pay_utilities' => true,
            'late_fee' => 0,
        ]);
        $this->fail('Expected exception was not thrown');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('forced failure');
    }

    // Critical: the rent Payment+Accounts rows from the *first half* of the
    // method must have been rolled back. If the transaction wrap is missing,
    // these counts would be 1 each — leaving the ledger inconsistent.
    expect(Payments::count())->toBe(0);
    expect(Accounts::count())->toBe(0);
    expect(Utilities::where('paid_status', true)->count())->toBe(0);
});
