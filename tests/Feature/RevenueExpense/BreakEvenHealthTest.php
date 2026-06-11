<?php

use App\Services\RevenueExpense\BreakEvenService;
use App\Services\RevenueExpense\RevenueExpenseQueryService;

/**
 * Smoke coverage for the break-even page's business-health section:
 * the page renders with the health charts, and getBusinessHealth()
 * returns consistent scores / trend / mix data derived from calculate().
 */
it('renders the break-even page with the business health charts', function () {
    $admin = makeAdmin();
    makeFiscalPeriod($admin);

    $floor = makeFloor('Floor 1');
    $apartment = makeApartment($floor, ['apartment_number' => 'A-101', 'status' => 'occupied']);
    $tenant = makeTenant($apartment);
    makeRental($tenant, $apartment, [
        'start_date' => now()->subMonth()->toDateString(),
        'rent_amount' => 300,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.revenue_expense.break_even'))
        ->assertOk()
        ->assertSee('healthGauge')
        ->assertSee('healthRadar')
        ->assertSee('healthTrend')
        ->assertSee('unitEconomicsChart');
});

it('computes bounded health scores, a trend window and mixes from the snapshot', function () {
    $admin = makeAdmin();
    $period = makeFiscalPeriod($admin);

    $floor = makeFloor('Floor 1');
    $apartment = makeApartment($floor, ['apartment_number' => 'A-101', 'status' => 'occupied']);
    $tenant = makeTenant($apartment);
    makeRental($tenant, $apartment, [
        'start_date' => now()->subMonth()->toDateString(),
        'rent_amount' => 300,
    ]);

    $this->actingAs($admin);

    $apartmentsScope = \App\Models\Apartments::query();
    $service = new BreakEvenService(
        new RevenueExpenseQueryService($admin->id, $period, $apartmentsScope->clone()),
        $admin->id,
        $period,
        $apartmentsScope,
    );

    $snapshot = $service->calculate(now()->month, now()->year);
    $health = $service->getBusinessHealth($snapshot, now()->month, now()->year);

    expect($health)->toHaveKeys(['scores', 'trend', 'revenue_mix', 'expense_mix']);

    foreach (['occupancy', 'profitability', 'break_even_coverage', 'cost_efficiency', 'collection', 'overall'] as $key) {
        expect($health['scores'][$key])->toBeInt()
            ->toBeGreaterThanOrEqual(0)
            ->toBeLessThanOrEqual(100);
    }

    // Trend ends at the selected month and never exceeds 6 entries.
    expect(count($health['trend']))->toBeLessThanOrEqual(6)
        ->and(end($health['trend'])['label'])->toBe(now()->format('M Y'));

    // Full occupancy (1/1) scores 100.
    expect($health['scores']['occupancy'])->toBe(100);
});
