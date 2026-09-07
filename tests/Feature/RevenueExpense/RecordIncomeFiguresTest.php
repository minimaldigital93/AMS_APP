<?php

use App\Models\ApartmentFixedExpense;
use App\Models\Payments;
use App\Models\Settings;
use App\Models\Utilities;
use App\Services\RevenueExpense\MonthlyBillingService;
use Carbon\Carbon;

/**
 * What the rent collection page SAYS a month is worth, against what the app
 * will actually collect and book for it. Every figure on that page is derived
 * per request from `rentals` + `utilities` (there is no invoice table), so a
 * figure that reaches wider than the month on screen, or counts one charge from
 * two sources, is money the operator quotes and never takes.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-08-20');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 500,
        'start_date' => '2026-05-10',
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

function augustBill(): array
{
    $response = test()->actingAs(test()->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 8, 'year' => 2026]));

    $response->assertOk();

    return [
        'bill' => collect($response->viewData('tenantBills')->items())->first(),
        'collected' => $response->viewData('totalRentCollected'),
        'expected' => $response->viewData('totalRentExpected'),
    ];
}

function payRentOn(string $date, float $amount = 500, float $lateFee = 0): Payments
{
    return Payments::create([
        'rental_id' => test()->rental->id,
        'amount' => $amount,
        'due_date' => $date,
        'paid_at' => $date,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'payment_type' => 'rent',
        'late_fee' => $lateFee,
    ]);
}

function fixedExpense(string $type = 'trash', float $amount = 25): ApartmentFixedExpense
{
    return ApartmentFixedExpense::create([
        'apartment_id' => test()->apartment->id,
        'expense_type' => $type,
        'expense_name' => ucfirst($type),
        'amount' => $amount,
        'is_active' => true,
    ]);
}

it('counts a fixed room cost once after the bill run has raised it as a charge', function () {
    fixedExpense('trash', 25);

    app(MonthlyBillingService::class)->processAll(
        App\Models\Apartments::query()->whereKey($this->apartment->id),
        Carbon::create(2026, 8, 1),
    );

    $bill = augustBill()['bill'];

    // One $25 charge row, and the template that raised it must not print beside
    // it: $550 on a $525 bill is the tenant being asked for trash twice.
    expect($bill['total_utilities'])->toBe(25.0)
        ->and((float) $bill['total_fixed'])->toBe(0.0)
        ->and($bill['total_bill'])->toBe(525.0);
});

it('counts a fixed room cost once when the charge was entered by hand', function () {
    fixedExpense('internet', 10);

    $this->actingAs($this->admin)->post(route('admin.revenue_expense.add_charge'), [
        'rental_id' => $this->rental->id,
        'charge_type' => 'internet',
        'charge_amount' => 10,
        'billing_month' => 8,
        'billing_year' => 2026,
    ]);

    $bill = augustBill()['bill'];

    expect((float) $bill['total_fixed'])->toBe(0.0)
        ->and($bill['total_bill'])->toBe(510.0);
});

it('states a room cost the month has not billed yet without billing it', function () {
    fixedExpense('trash', 25);

    $response = test()->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 8, 'year' => 2026]));

    $bill = collect($response->viewData('tenantBills')->items())->first();

    // Still shown — the operator needs to know the room bills it — but it is a
    // preview of the next bill run, not money owed. Nothing can collect it
    // until it is raised as a charge row, so quoting it on the bill total (or
    // in Pending) asked for money checkout would never book.
    expect($bill['total_fixed'])->toBe(25.0)
        ->and($bill['total_bill'])->toBe(500.0)
        ->and($response->viewData('totalPendingRent'))->toBe(500.0)
        ->and($response->viewData('totalPending'))->toBe(500.0);
});

it('bills the room cost once it has been raised as a charge', function () {
    fixedExpense('trash', 25);

    app(MonthlyBillingService::class)->processAll(
        App\Models\Apartments::query()->whereKey($this->apartment->id),
        Carbon::create(2026, 8, 1),
    );

    $response = test()->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 8, 'year' => 2026]));

    $bill = collect($response->viewData('tenantBills')->items())->first();

    expect($bill['total_bill'])->toBe(525.0)
        ->and($bill['unpaid_other_charges'])->toBe(25.0)
        ->and($response->viewData('totalPendingCharges'))->toBe(25.0);
});

it('reports what the month on screen collected, not the fiscal period to date', function () {
    payRentOn('2026-06-10', 500, 10);
    payRentOn('2026-07-10', 500, 10);
    payRentOn('2026-08-10', 500, 10);

    $august = augustBill();

    // The eager load reaches across the whole period on purpose (it is the
    // fallback that guarantees the month's own payments are loaded), so the
    // figures have to narrow back to the month or "Collected" outgrows
    // "Expected" by a month's rent every month.
    expect($august['bill']['collected'])->toBe(500.0)
        ->and($august['bill']['late_fees'])->toBe(10.0)
        ->and($august['bill']['payment_count'])->toBe(1)
        ->and($august['collected'])->toBe(510.0)
        ->and($august['expected'])->toBe(500.0);
});

it('still marks the month paid from a payment anchored in it', function () {
    payRentOn('2026-07-10');
    payRentOn('2026-08-10');

    expect(augustBill()['bill']['rent_status'])->toBe('paid');
});

it('clears only the month the charges modal is showing', function () {
    foreach ([7, 8] as $month) {
        Utilities::create([
            'tenant_id' => $this->tenant->id,
            'rental_id' => $this->rental->id,
            'utility_type' => 'electricity',
            'charge_amount' => 30,
            'billing_month' => $month,
            'billing_year' => 2026,
            'paid_status' => false,
        ]);
    }

    $this->actingAs($this->admin)->deleteJson(
        route('admin.revenue_expense.clear_charges', ['rental' => $this->rental->id, 'month' => 8, 'year' => 2026])
    )->assertOk();

    // July is unpaid debt the tenant still owes — outstandingCharges() collects
    // exactly these rows — and nobody was looking at it.
    expect(Utilities::where('rental_id', $this->rental->id)->pluck('billing_month')->all())
        ->toBe([7]);
});

it('prints the prorated rent and the viewed month on the tenant bill', function () {
    // Settings are account-scoped, so the collection day has to be written as
    // the account that will read it back.
    $this->actingAs($this->admin);
    Settings::set('billing_cycle_day', '2');

    $rental = $this->rental;
    $rental->update(['start_date' => '2026-08-08']);

    // The print button carries no month of its own — it follows the month the
    // operator navigated to.
    $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 8, 'year' => 2026]));

    $response = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.print_bill', ['rental' => $rental->id]));

    $response->assertOk();

    // Aug 8 → Sep 2 is 25 of August's 31 days at $500/mo, not a full month.
    expect(round($response->viewData('rent_amount'), 2))->toBe(403.23)
        ->and($response->viewData('monthYear'))->toBe('August 2026')
        ->and($response->viewData('dueDate')->toDateString())->toBe('2026-09-02');
});
