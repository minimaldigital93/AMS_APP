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

    $this->service = new MonthlyBillingService;
});

it('processSelected creates one Utilities charge per billed expense and NO ledger expense', function () {
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

    // 2026-07 accounting audit: billing a tenant is a receivable, not a cost
    // the landlord incurred — the old mirror business_fixed expense suppressed
    // real profit and double-counted actual vendor bills. No ledger row here.
    expect(Accounts::count())->toBe(0);
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

it('rolls back the whole batch when a write fails mid-batch', function () {
    // Second fixed expense: the first insert succeeds, the second throws —
    // without the transaction wrap the first Utilities row would survive.
    ApartmentFixedExpense::create([
        'apartment_id' => $this->apartment->id,
        'expense_name' => 'Internet',
        'expense_type' => 'internet',
        'amount' => 15,
        'is_active' => true,
    ]);

    $inserts = 0;
    Utilities::creating(function () use (&$inserts) {
        if (++$inserts === 2) {
            throw new RuntimeException('forced failure during billing');
        }
    });

    try {
        $this->service->processAll(Apartments::query(), Carbon::parse('2026-05-10'));
        $this->fail('Expected exception was not thrown');
    } catch (RuntimeException) {
        // expected
    }

    expect(Utilities::count())->toBe(0);
});
