<?php

use App\Models\Accounts;
use App\Models\ApartmentFixedExpense;
use App\Models\Apartments;
use App\Models\Utilities;
use App\Services\RevenueExpense\MonthlyBillingService;
use Carbon\Carbon;

beforeEach(function () {
    $this->admin = makeAdmin();
    $this->period = makeFiscalPeriod($this->admin);
    $this->apartment = makeApartment(null, ['apartment_number' => 'B-201']);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment);

    $this->fixed = ApartmentFixedExpense::create([
        'apartment_id' => $this->apartment->id,
        'expense_name' => 'Parking',
        'expense_type' => 'parking',
        'amount' => 40,
        'is_active' => true,
    ]);

    $this->service = new MonthlyBillingService(userId: $this->admin->id, period: $this->period);
});

it('processSelected creates one Utilities + one Accounts row per billed expense', function () {
    $result = $this->service->processSelected([
        [
            'rental_id' => $this->rental->id,
            'selected' => true,
            'expenses' => [
                ['expense_id' => $this->fixed->id, 'amount' => 40, 'selected' => true],
            ],
        ],
    ], Carbon::parse('2026-05-10'));

    expect($result['count'])->toBe(1);
    expect($result['total'])->toEqual(40.0);
    expect(Utilities::count())->toBe(1);
    expect(Accounts::where('account_type', Accounts::TYPE_EXPENSE)->count())->toBe(1);

    $account = Accounts::sole();
    expect($account->category)->toBe(Accounts::CAT_BUSINESS_FIXED);
    expect($account->fiscal_period_id)->toBe($this->period->id);
});

it('refuses to double-bill the same (rental, type, month, year) tuple', function () {
    $date = Carbon::parse('2026-05-10');

    $this->service->processSelected([[
        'rental_id' => $this->rental->id,
        'selected' => true,
        'expenses' => [['expense_id' => $this->fixed->id, 'amount' => 40, 'selected' => true]],
    ]], $date);

    // Second run on the same month is a no-op
    $result = $this->service->processSelected([[
        'rental_id' => $this->rental->id,
        'selected' => true,
        'expenses' => [['expense_id' => $this->fixed->id, 'amount' => 40, 'selected' => true]],
    ]], $date);

    expect($result['count'])->toBe(0);
    expect(Utilities::count())->toBe(1);
    expect(Accounts::count())->toBe(1);
});

it('processAll bills every active fixed expense across the apartment scope', function () {
    ApartmentFixedExpense::create([
        'apartment_id' => $this->apartment->id,
        'expense_name' => 'Internet',
        'expense_type' => 'internet',
        'amount' => 15,
        'is_active' => true,
    ]);

    $result = $this->service->processAll(
        Apartments::query(),
        Carbon::parse('2026-06-01'),
    );

    expect($result['count'])->toBe(2);
    expect($result['total'])->toEqual(55.0);
    expect(Utilities::count())->toBe(2);
});

it('rolls back when a write fails mid-batch', function () {
    \App\Models\Accounts::saving(function ($account) {
        throw new RuntimeException('forced failure during billing');
    });

    try {
        $this->service->processSelected([[
            'rental_id' => $this->rental->id,
            'selected' => true,
            'expenses' => [['expense_id' => $this->fixed->id, 'amount' => 40, 'selected' => true]],
        ]], Carbon::parse('2026-05-10'));
        $this->fail('Expected exception was not thrown');
    } catch (RuntimeException) {
        // expected
    }

    // The Utilities row was inserted *before* the Accounts row inside bill();
    // if the transaction wrap is missing, the Utilities row would survive,
    // leaving a charge with no ledger entry.
    expect(Utilities::count())->toBe(0);
    expect(Accounts::count())->toBe(0);
});
