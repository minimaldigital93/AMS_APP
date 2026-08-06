<?php

use App\Services\Dashboard\DashboardStatsService;
use App\Services\RevenueExpense\BreakEvenService;
use App\Services\RevenueExpense\RevenueExpenseQueryService;

/**
 * "Rooms rented" on the break-even page must agree with the dashboard.
 *
 * A room is single-occupancy, so it can be rented at most once in a month —
 * but during turnover the outgoing and incoming tenancies overlap (the room is
 * freed for reassignment the day the leave is processed, and the move-out date
 * can be any day of the month). The rent collection page and the dashboard
 * tiles already de-duplicate per apartment; break-even counted rentals and so
 * reported one room too many for every turnover month.
 */
function breakEvenFor(\App\Models\User $admin, \App\Models\FiscalPeriods $period): BreakEvenService
{
    $scope = \App\Models\Apartments::query();

    return new BreakEvenService(
        new RevenueExpenseQueryService($admin->id, $period, $scope->clone()),
        $admin->id,
        $period,
        $scope,
    );
}

it('counts a turnover room once, and agrees with the dashboard', function () {
    $admin = makeAdmin();
    $period = makeFiscalPeriod($admin);
    $this->actingAs($admin);

    $floor = makeFloor('Floor 1');
    $turnover = makeApartment($floor, ['apartment_number' => 'A-101', 'status' => 'occupied']);
    $steady = makeApartment($floor, ['apartment_number' => 'A-102', 'status' => 'occupied']);

    $monthStart = now()->startOfMonth();

    // A-101: outgoing tenant leaves mid-month, incoming tenant moves in after.
    makeRental(makeTenant($turnover), $turnover, [
        'start_date' => $monthStart->copy()->subMonths(2)->toDateString(),
        'end_date' => $monthStart->copy()->addDays(9)->toDateString(),
        'rent_amount' => 300,
    ]);
    makeRental(makeTenant($turnover), $turnover, [
        'start_date' => $monthStart->copy()->addDays(12)->toDateString(),
        'rent_amount' => 400,
    ]);

    // A-102: one tenancy running through the whole month.
    makeRental(makeTenant($steady), $steady, [
        'start_date' => $monthStart->copy()->subMonths(2)->toDateString(),
        'rent_amount' => 400,
    ]);

    $snapshot = breakEvenFor($admin, $period)->calculate(now()->month, now()->year);

    // Two rooms, three overlapping tenancies — two rooms rented.
    expect($snapshot['current_occupancy'])->toBe(2)
        ->and($snapshot['total_apartments'])->toBe(2);

    // Averaged over rooms, not tenancies: the newest tenancy that had begun by
    // month end is the room's occupant of record, exactly as the rent
    // collection page and the dashboard tiles pick it.
    expect($snapshot['avg_rent_per_apartment'])->toBe(400.0);

    // Same number the dashboard's occupancy card and rent tiles report.
    $stats = (new DashboardStatsService($admin->id))
        ->build(now()->startOfMonth(), now()->endOfMonth(), now());

    expect($stats['apartments']['occupied'])->toBe($snapshot['current_occupancy'])
        ->and($stats['payments']['bills_total'])->toBe($snapshot['current_occupancy']);
});

it('never plots a turnover month above 100% occupancy in the health trend', function () {
    $admin = makeAdmin();
    $period = makeFiscalPeriod($admin);
    $this->actingAs($admin);

    $floor = makeFloor('Floor 1');
    $apartment = makeApartment($floor, ['apartment_number' => 'A-101', 'status' => 'occupied']);

    $monthStart = now()->startOfMonth();

    makeRental(makeTenant($apartment), $apartment, [
        'start_date' => $monthStart->copy()->subMonths(2)->toDateString(),
        'end_date' => $monthStart->copy()->addDays(9)->toDateString(),
        'rent_amount' => 300,
    ]);
    makeRental(makeTenant($apartment), $apartment, [
        'start_date' => $monthStart->copy()->addDays(12)->toDateString(),
        'rent_amount' => 300,
    ]);

    $service = breakEvenFor($admin, $period);
    $snapshot = $service->calculate(now()->month, now()->year);
    $health = $service->getBusinessHealth($snapshot, now()->month, now()->year);

    expect($snapshot['current_occupancy'])->toBe(1);

    foreach ($health['trend'] as $point) {
        expect($point['occupancy_pct'])->toBeLessThanOrEqual(100);
    }
});
