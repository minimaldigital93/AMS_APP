<?php

use App\Models\ApartmentFixedExpense;
use App\Models\Settings;
use App\Models\Utilities;
use Carbon\Carbon;

/**
 * The rent collection day as the collector actually sees it, driven through the
 * record-income page rather than the service. Pins the three things a set
 * collection day must change on that screen: the rent charged, the due day, and
 * when the row turns overdue — plus the bill detail the payment form spells out.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-20');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 300]);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 300,
        'start_date' => '2026-08-08',
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function incomeBill(int $month, int $year, $rentalId): ?array
{
    $response = test()->actingAs(test()->admin)->get(route('admin.revenue_expense.record_income', [
        'month' => $month,
        'year' => $year,
    ]));

    $response->assertOk();

    return collect($response->viewData('tenantBills')->items())
        ->firstWhere(fn ($b) => $b['rental']->id === $rentalId);
}

it('charges rent on the collection day once the account sets one', function () {
    Settings::set('billing_cycle_day', '2');

    $bill = incomeBill(10, 2026, $this->rental->id);

    expect($bill['monthly_rent'])->toBe(300.0)
        ->and($bill['due_day'])->toBe(2)
        ->and($bill['due_date']->toDateString())->toBe('2026-10-02')
        ->and($bill['billing_period']->label())->toBe('Oct 2 – Nov 2, 2026');
});

it('prorates the move-in month up to the collection day of the next month', function () {
    Settings::set('billing_cycle_day', '2');

    $bill = incomeBill(8, 2026, $this->rental->id);

    // Aug 8 → Sep 2 = 25 of August's 31 days, so 25 x ($300 / 31).
    expect($bill['monthly_rent'])->toBe(241.94)
        ->and($bill['due_date']->toDateString())->toBe('2026-09-02')
        ->and($bill['billing_period']->isProrated)->toBeTrue()
        ->and($bill['billing_period']->days)->toBe(25)
        ->and($bill['total_bill'])->toBe(241.94);
});

it('prorates a mid-month move-in the same way, whatever day it lands on', function () {
    Settings::set('billing_cycle_day', '2');

    // Same $300 room, but this tenant is assigned on the 15th.
    $apartment = makeApartment(null, ['monthly_rent' => 300]);
    $tenant = makeTenant($apartment, ['move_in_date' => '2026-08-15']);
    $rental = makeRental($tenant, $apartment, [
        'rent_amount' => 300,
        'start_date' => '2026-08-15',
    ]);

    // Aug 15 → Sep 2 = 18 of August's 31 days, so 18 x ($300 / 31) = $174.19.
    $august = incomeBill(8, 2026, $rental->id);

    expect($august['monthly_rent'])->toBe(174.19)
        ->and($august['due_date']->toDateString())->toBe('2026-09-02')
        ->and($august['billing_period']->isProrated)->toBeTrue()
        ->and($august['billing_period']->days)->toBe(18)
        ->and($august['checkout_detail']['period'])->toBe('Aug 15 – Sep 2, 2026');

    // From September on it is a plain full cycle on the collection day — the
    // move-in day is spent and never reappears.
    $september = incomeBill(9, 2026, $rental->id);

    expect($september['monthly_rent'])->toBe(300.0)
        ->and($september['due_date']->toDateString())->toBe('2026-09-02')
        ->and($september['billing_period']->isProrated)->toBeFalse()
        ->and($september['checkout_detail']['period'])->toBe('Sep 2 – Oct 2, 2026');
});

it('keeps billing on the tenant move-in day while no collection day is set', function () {
    $bill = incomeBill(10, 2026, $this->rental->id);

    expect($bill['monthly_rent'])->toBe(300.0)
        ->and($bill['due_day'])->toBe(8)
        ->and($bill['billing_period'])->toBeNull();
});

it('moves the overdue point to the collection day plus the grace days', function () {
    Settings::set('billing_cycle_day', '2');
    Settings::set('billing_overdue_days', '3');
    Settings::set('late_fee_percent', '1');

    // Viewing Oct on the 20th: due Oct 2, and the three days' grace run to the
    // end of Oct 5 — so the tenant is 14 whole days late, not 18.
    $bill = incomeBill(10, 2026, $this->rental->id);

    expect($bill['status'])->toBe('overdue')
        ->and($bill['overdue_days'])->toBe(14)
        ->and($bill['late_fee_suggested'])->toBe(round(300 * 0.01 * 14, 2));
});

it('is not overdue inside the grace window', function () {
    Carbon::setTestNow('2026-10-04');
    Settings::set('billing_cycle_day', '2');
    Settings::set('billing_overdue_days', '3');

    $bill = incomeBill(10, 2026, $this->rental->id);

    // Due Oct 2, three days' grace → still pending on the 4th.
    expect($bill['status'])->toBe('pending')
        ->and($bill['overdue_days'])->toBe(0);
});

it('hands the payment form the charge date and every fee by name', function () {
    Settings::set('billing_cycle_day', '2');

    Utilities::create([
        'tenant_id' => $this->tenant->id, 'rental_id' => $this->rental->id,
        'utility_type' => 'electricity', 'charge_amount' => 12.5,
        'billing_month' => 10, 'billing_year' => 2026,
    ]);
    ApartmentFixedExpense::create([
        'apartment_id' => $this->apartment->id, 'expense_name' => 'Internet',
        'expense_type' => 'internet', 'amount' => 10,
    ]);

    $detail = incomeBill(10, 2026, $this->rental->id)['checkout_detail'];

    expect($detail['period'])->toBe('Oct 2 – Nov 2, 2026')
        ->and($detail['due'])->toBe('Oct 2, 2026')
        ->and($detail['prorated'])->toBe(0)
        ->and($detail['items']->toArray())->toBe([
            ['type' => 'electricity', 'amount' => 12.5, 'paid' => false],
        ])
        ->and($detail['fixed']->toArray())->toBe([
            ['name' => 'Internet', 'amount' => 10.0],
        ]);
});

it('names the month and the prorated day count when the bill is a part month', function () {
    Settings::set('billing_cycle_day', '2');

    $detail = incomeBill(8, 2026, $this->rental->id)['checkout_detail'];

    expect($detail['period'])->toBe('Aug 8 – Sep 2, 2026')
        ->and($detail['due'])->toBe('Sep 2, 2026')
        ->and($detail['prorated'])->toBe(25);
});

it('falls back to the plain month when no collection day is set', function () {
    $detail = incomeBill(10, 2026, $this->rental->id)['checkout_detail'];

    expect($detail['period'])->toBe('October 2026')
        ->and($detail['due'])->toBe('Oct 8, 2026')
        ->and($detail['prorated'])->toBe(0);
});

it('sums the expected rent from the collection-day amounts, not the lease rent', function () {
    Settings::set('billing_cycle_day', '2');

    $response = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 8, 'year' => 2026]));

    expect($response->viewData('totalRentExpected'))->toBe(241.94);
});
