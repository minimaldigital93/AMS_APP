<?php

use App\Models\Payments;
use App\Models\Utilities;
use App\Services\Dashboard\DashboardStatsService;
use Carbon\Carbon;

/**
 * The dashboard's Paid / Pending / Overdue tiles each link straight to the
 * matching filter chip on the rent collection page, so the number on the tile
 * has to be the number of rows behind the chip it opens — and the donut's
 * denominator has to be the page's row count.
 *
 * DashboardStatsService re-derives the classification independently of
 * Shared\RevenueExpenseController::recordIncome(), so this file pins the two
 * against each other rather than against hard-coded expectations. It caught the
 * tile deriving the due date off each tenant's own move-in day with no grace
 * period, ignoring the account's rent collection day entirely.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-07-10 10:00:00');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
});

afterEach(function () {
    auth()->logout();
    Carbon::setTestNow();
});

/**
 * Assert the July tiles and the July collection page agree, and hand back the
 * tile bundle so a test can also state what the counts should be.
 */
function assertJulyTilesMatchCollectionPage($test): array
{
    $tiles = (new DashboardStatsService($test->admin->id, null, null, $test->period->id))
        ->build(
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
            Carbon::parse('2026-07-01')
        )['payments'];

    $page = $test->actingAs($test->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 7, 'year' => 2026]));
    $page->assertOk();

    expect($tiles['paid'])->toBe($page->viewData('paidCount'))
        ->and($tiles['pending'])->toBe($page->viewData('pendingCount'))
        ->and($tiles['overdue'])->toBe($page->viewData('overdueCount'))
        // The donut denominator is the page's row count — every bill row lands
        // in exactly one of the three buckets.
        ->and($tiles['bills_total'])->toBe(count($page->viewData('apartmentSummary')))
        ->and(round($tiles['total_pending'], 2))->toBe(round(
            $page->viewData('totalPendingRent') + $page->viewData('totalPendingCharges'), 2
        ));

    return $tiles;
}

function sittingTenant(array $rental = []): App\Models\Rentals
{
    $apartment = makeApartment(null, ['monthly_rent' => 500, 'status' => 'occupied']);

    return makeRental(
        makeTenant($apartment, ['move_in_date' => $rental['start_date'] ?? '2026-01-01']),
        $apartment,
        array_merge(['start_date' => '2026-01-01', 'rent_amount' => 500], $rental)
    );
}

it('honours the rent collection day, not the move-in day', function () {
    // Collection day 5 + 3 days grace: on the 10th rent is late. The move-in
    // day (the 20th) is irrelevant once a collection day is set.
    settings(['billing_cycle_day' => 5, 'billing_overdue_days' => 3]);
    sittingTenant(['start_date' => '2026-01-20']);

    $tiles = assertJulyTilesMatchCollectionPage($this);

    expect($tiles['overdue'])->toBe(1);
});

it('leaves rent inside the grace period pending, not overdue', function () {
    // Due on the 8th, five days of grace → not late until the 13th.
    settings(['billing_cycle_day' => 8, 'billing_overdue_days' => 5]);
    sittingTenant(['start_date' => '2026-01-01']);

    $tiles = assertJulyTilesMatchCollectionPage($this);

    expect($tiles['pending'])->toBe(1)
        ->and($tiles['overdue'])->toBe(0);
});

it('applies the grace period with no collection day set either', function () {
    settings(['billing_cycle_day' => 0, 'billing_overdue_days' => 3]);
    sittingTenant(['start_date' => '2026-01-08']);

    $tiles = assertJulyTilesMatchCollectionPage($this);

    expect($tiles['pending'])->toBe(1)
        ->and($tiles['overdue'])->toBe(0);
});

it('states the prorated rent of a move-in month as pending, not a full month', function () {
    settings(['billing_cycle_day' => 5, 'billing_overdue_days' => 3]);
    sittingTenant(['start_date' => '2026-07-20']);

    $tiles = assertJulyTilesMatchCollectionPage($this);

    // Jul 20 → Aug 5 on a $500 room, prorated over July's 31 days.
    expect($tiles['pending'])->toBe(1)
        ->and(round($tiles['total_pending'], 2))->toBe(258.06);
});

it('counts an empty room whose next tenancy is still to come, owing nothing', function () {
    settings(['billing_cycle_day' => 0]);
    $apartment = makeApartment(null, ['monthly_rent' => 500, 'status' => 'available']);
    makeRental(
        makeTenant($apartment, ['move_in_date' => '2026-08-01']),
        $apartment,
        ['start_date' => '2026-08-01', 'rent_amount' => 500]
    );

    $tiles = assertJulyTilesMatchCollectionPage($this);

    // The page gives it an "Upcoming" row in the pending bucket; the tile has
    // to count the same row, but nothing is owed for a month it never touched.
    expect($tiles['pending'])->toBe(1)
        ->and($tiles['bills_total'])->toBe(1)
        ->and($tiles['total_pending'])->toBe(0.0);
});

it('keeps rent-in-charges-out on the pending side of both', function () {
    settings(['billing_cycle_day' => 0]);
    $rental = sittingTenant();
    Payments::create([
        'rental_id' => $rental->id, 'amount' => 500, 'payment_type' => 'rent',
        'payment_status' => 'paid', 'paid_at' => '2026-07-05',
        'payment_method' => 'cash', 'due_date' => '2026-07-01',
    ]);
    Utilities::create([
        'rental_id' => $rental->id, 'tenant_id' => $rental->tenant_id,
        'utility_type' => 'electricity', 'charge_amount' => 30,
        'billing_month' => 7, 'billing_year' => 2026, 'paid_status' => false,
    ]);

    $tiles = assertJulyTilesMatchCollectionPage($this);

    expect($tiles['paid'])->toBe(0)
        ->and($tiles['pending'])->toBe(1)
        ->and($tiles['total_pending'])->toBe(30.0);
});

it('counts a fully settled bill as paid on both', function () {
    settings(['billing_cycle_day' => 0]);
    $rental = sittingTenant();
    Payments::create([
        'rental_id' => $rental->id, 'amount' => 530, 'payment_type' => 'rent',
        'payment_status' => 'paid', 'paid_at' => '2026-07-05',
        'payment_method' => 'cash', 'due_date' => '2026-07-01',
    ]);
    Utilities::create([
        'rental_id' => $rental->id, 'tenant_id' => $rental->tenant_id,
        'utility_type' => 'electricity', 'charge_amount' => 30,
        'billing_month' => 7, 'billing_year' => 2026,
        'paid_status' => true, 'paid_at' => '2026-07-05',
    ]);

    $tiles = assertJulyTilesMatchCollectionPage($this);

    expect($tiles['paid'])->toBe(1)
        ->and($tiles['total_pending'])->toBe(0.0);
});

it('counts a turnover room once on both', function () {
    settings(['billing_cycle_day' => 5, 'billing_overdue_days' => 3]);
    $apartment = makeApartment(null, ['monthly_rent' => 500, 'status' => 'occupied']);
    makeRental(makeTenant($apartment, ['status' => 'inactive']), $apartment, [
        'start_date' => '2026-01-01', 'end_date' => '2026-07-15', 'rent_amount' => 500,
    ]);
    makeRental(makeTenant($apartment), $apartment, [
        'start_date' => '2026-07-16', 'rent_amount' => 500,
    ]);

    $tiles = assertJulyTilesMatchCollectionPage($this);

    expect($tiles['bills_total'])->toBe(1);
});

it('agrees across a mixed property in a closed-out past month', function () {
    settings(['billing_cycle_day' => 5, 'billing_overdue_days' => 3]);

    // Never paid.
    sittingTenant(['start_date' => '2026-02-01']);

    // Paid, no charges ever raised — a rent-inclusive account.
    $paid = sittingTenant(['start_date' => '2026-03-01']);
    Payments::create([
        'rental_id' => $paid->id, 'amount' => 500, 'payment_type' => 'rent',
        'payment_status' => 'paid', 'paid_at' => '2026-07-04',
        'payment_method' => 'cash', 'due_date' => '2026-07-05',
    ]);

    // Moved in mid-month, prorated, unpaid.
    sittingTenant(['start_date' => '2026-07-12']);

    $tiles = assertJulyTilesMatchCollectionPage($this);

    expect($tiles['paid'] + $tiles['pending'] + $tiles['overdue'])->toBe(3);
});
