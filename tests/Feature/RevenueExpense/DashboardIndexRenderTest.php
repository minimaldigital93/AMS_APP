<?php

use App\Models\ApartmentFixedExpense;
use App\Models\Utilities;

/**
 * Smoke coverage for the revenue/expense dashboard index() after the
 * single-fetch apartment consolidation. Exercises the merged load, the
 * active-rental PHP filter (clone + setRelation), the period-scoped utilities
 * superset, and the bills / expense / fixed sections with real nested data.
 */
it('renders the dashboard with apartments, an active rental, utilities and fixed expenses', function () {
    $admin = makeAdmin();
    makeFiscalPeriod($admin);

    $floor = makeFloor('Floor 1');
    $apartment = makeApartment($floor, ['apartment_number' => 'A-101', 'status' => 'occupied']);
    $tenant = makeTenant($apartment);
    $rental = makeRental($tenant, $apartment, ['start_date' => now()->subMonth()->toDateString()]);

    // Current-month utility (income/bills side) — must survive the period superset.
    Utilities::create([
        'tenant_id' => $tenant->id,
        'rental_id' => $rental->id,
        'utility_type' => 'electricity',
        'charge_amount' => 42.50,
        'billing_month' => now()->month,
        'billing_year' => now()->year,
        'paid_status' => false,
        'paid_at' => null,
    ]);

    ApartmentFixedExpense::create([
        'apartment_id' => $apartment->id,
        'expense_name' => 'Parking',
        'expense_type' => 'parking',
        'amount' => 15,
        'is_active' => true,
    ]);

    // A second apartment whose only rental has already ENDED — it must show up
    // in the expense set (all rentals) but be filtered out of the income view.
    $apt2 = makeApartment($floor, ['apartment_number' => 'A-102', 'status' => 'available']);
    $tenant2 = makeTenant($apt2);
    makeRental($tenant2, $apt2, [
        'start_date' => now()->subMonths(6)->toDateString(),
        'end_date' => now()->subMonths(2)->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.revenue_expense.index'))
        ->assertOk()
        ->assertSee('A-101')
        ->assertSee('A-102'); // expense/fixed sections list all apartments
});
