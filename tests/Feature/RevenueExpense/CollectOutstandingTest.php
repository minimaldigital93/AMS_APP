<?php

use App\Models\Accounts;
use App\Models\Payments;
use App\Models\Utilities;
use App\Services\RevenueExpense\IncomeRecordingService;

/**
 * "Collect Outstanding" settles a tenant's whole carried-forward debt at once.
 *
 * Accounting invariant: income is recognised on the payment date in the CURRENT
 * open period (closed books untouched), while each owed rent month's Payments
 * row is anchored in its own month so the tenant's derived debt clears.
 */
beforeEach(function () {
    seedRoles();
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
    $this->period = makeFiscalPeriod($this->admin);
});

function settleService(): IncomeRecordingService
{
    return new IncomeRecordingService(userId: test()->admin->id, period: test()->period);
}

it('settles all unpaid rent months and utilities and clears the debt', function () {
    $tenant = makeTenant();
    $rental = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-05-01', // May, Jun, Jul(current) = 3 rent months
        'rent_amount' => 500,
    ]);
    Utilities::create([
        'tenant_id' => $tenant->id, 'rental_id' => $rental->id,
        'utility_type' => 'water', 'meter_reading_in' => 0, 'meter_reading_out' => 0,
        'charge_amount' => 30, 'billing_month' => 6, 'billing_year' => 2026,
        'paid_status' => false, 'paid_at' => null,
    ]);

    $result = settleService()->settleOutstandingForTenant($tenant, '2026-07-22', 'cash');

    expect($result['rent_count'])->toBe(3)
        ->and($result['utilities_count'])->toBe(1)
        ->and($result['total'])->toBe(1530.0);

    // Debt is now zero.
    expect($tenant->fresh()->outstandingCharges()['total_due'])->toBe(0.0);

    // Utility marked paid.
    expect(Utilities::where('rental_id', $rental->id)->where('paid_status', false)->count())->toBe(0);
});

it('anchors each rent Payments row in its owed month but books income today', function () {
    $tenant = makeTenant();
    $rental = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-05-01',
        'rent_amount' => 500,
    ]);

    settleService()->settleOutstandingForTenant($tenant, '2026-07-22', 'cash');

    // One rent payment per owed month, paid_at inside that month.
    $rentPaidMonths = Payments::where('rental_id', $rental->id)
        ->where('payment_type', 'rent')->get()
        ->map(fn ($p) => $p->paid_at->format('Y-m'))->sort()->values()->all();
    expect($rentPaidMonths)->toBe(['2026-05', '2026-06', '2026-07']);

    // Every income ledger row is dated the payment date (today), in this period.
    $income = Accounts::where('account_type', Accounts::TYPE_INCOME)->get();
    expect($income)->toHaveCount(3)
        ->and($income->every(fn ($a) => $a->transaction_date->format('Y-m-d') === '2026-07-22'))->toBeTrue()
        ->and($income->every(fn ($a) => $a->fiscal_period_id === $this->period->id))->toBeTrue();
});

it('settles only the selected rent month and leaves the rest owed', function () {
    $tenant = makeTenant();
    $rental = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-05-01', // May, Jun, Jul = 3 months @ 500 = 1500
        'rent_amount' => 500,
    ]);

    // Collect only May.
    $result = settleService()->settleOutstandingForTenant(
        $tenant, '2026-07-22', 'cash', null, ['rent_'.$rental->id.'_2026_5'],
    );

    expect($result['rent_count'])->toBe(1)
        ->and($result['total'])->toBe(500.0)
        ->and($tenant->fresh()->outstandingCharges()['total_due'])->toBe(1000.0);

    // The one settled month is May.
    expect(Payments::where('rental_id', $rental->id)->where('payment_type', 'rent')->pluck('paid_at')
        ->map(fn ($d) => $d->format('Y-m'))->all())->toBe(['2026-05']);
});

it('settles only a selected utility charge', function () {
    $tenant = makeTenant();
    $rental = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-07-01', 'rent_amount' => 400,
    ]);
    $water = Utilities::create([
        'tenant_id' => $tenant->id, 'rental_id' => $rental->id,
        'utility_type' => 'water', 'meter_reading_in' => 0, 'meter_reading_out' => 0,
        'charge_amount' => 30, 'billing_month' => 7, 'billing_year' => 2026,
        'paid_status' => false, 'paid_at' => null,
    ]);
    $power = Utilities::create([
        'tenant_id' => $tenant->id, 'rental_id' => $rental->id,
        'utility_type' => 'electricity', 'meter_reading_in' => 0, 'meter_reading_out' => 0,
        'charge_amount' => 50, 'billing_month' => 7, 'billing_year' => 2026,
        'paid_status' => false, 'paid_at' => null,
    ]);

    // Pick only the water charge (leave rent + electricity owed).
    $result = settleService()->settleOutstandingForTenant(
        $tenant, '2026-07-22', 'cash', null, ['utility_'.$water->id],
    );

    expect($result['utilities_count'])->toBe(1)
        ->and($result['total'])->toBe(30.0)
        ->and($water->fresh()->paid_status)->toBeTrue()
        ->and($power->fresh()->paid_status)->toBeFalse();
});

it('reports nothing to collect when the tenant is settled', function () {
    $tenant = makeTenant();
    $rental = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-07-01', // current month only
        'rent_amount' => 400,
    ]);
    Payments::create([
        'rental_id' => $rental->id, 'amount' => 400,
        'due_date' => '2026-07-05', 'paid_at' => '2026-07-05',
        'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_type' => 'rent',
    ]);

    $result = settleService()->settleOutstandingForTenant($tenant, '2026-07-22', 'cash');

    expect($result['total'])->toBe(0.0)
        ->and($result['rent_count'])->toBe(0)
        ->and($result['utilities_count'])->toBe(0);
});

it('collects outstanding via the admin HTTP endpoint', function () {
    giveActiveSubscription($this->admin);
    $tenant = makeTenant();
    makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-06-01', // Jun, Jul = 2 months
        'rent_amount' => 300,
    ]);

    $response = $this->post(route('admin.revenue_expense.collect_outstanding', $tenant), [
        'payment_method' => 'cash',
        'payment_date' => '2026-07-22',
    ]);

    $response->assertRedirect();
    expect($tenant->fresh()->outstandingCharges()['total_due'])->toBe(0.0)
        ->and((float) Accounts::where('account_type', Accounts::TYPE_INCOME)->sum('amount'))->toBe(600.0);
});
