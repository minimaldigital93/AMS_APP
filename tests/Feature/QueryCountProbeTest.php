<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Property;
use App\Models\Settings;
use App\Models\Utilities;
use Illuminate\Support\Facades\DB;

/**
 * Phase 9 regression net: page query counts on a building-scale dataset
 * (2 properties, 4 floors, 16 occupied rooms). Before the Phase 9 fixes the
 * revenue dashboard ran 386 queries and floors 121 — the ceilings below are
 * deliberately generous (roughly 2× the fixed counts) so they only trip on a
 * real N+1 regression (per-row settings lookups, relation method calls in
 * Blade loops), not on an added feature query or two.
 */
function probeFixture(): array
{
    $admin = makeAdmin();
    auth()->login($admin);
    makeFiscalPeriod($admin);

    foreach ([1, 2] as $p) {
        $property = Property::create(['name' => "Building {$p}"]);
        foreach ([1, 2] as $f) {
            $floor = Floors::create(['property_id' => $property->id, 'floor_name' => "B{$p}F{$f}"]);
            foreach (range(1, 4) as $a) {
                $apt = Apartments::create([
                    'floor_id' => $floor->id,
                    'apartment_number' => "B{$p}F{$f}A{$a}",
                    'monthly_rent' => 300 + $a * 10,
                    'status' => 'occupied',
                ]);
                $tenant = makeTenant($apt);
                $rental = makeRental($tenant, $apt);
                Payments::create([
                    'rental_id' => $rental->id, 'amount' => 300, 'due_date' => now()->addDays(3),
                    'payment_status' => 'pending', 'payment_type' => 'rent',
                ]);
                Payments::create([
                    'rental_id' => $rental->id, 'amount' => 300, 'due_date' => now()->subMonth(),
                    'paid_at' => now()->subDays(2), 'payment_status' => 'paid', 'payment_type' => 'rent',
                ]);
                Utilities::create([
                    'tenant_id' => $tenant->id, 'rental_id' => $rental->id, 'utility_type' => 'electricity',
                    'charge_amount' => 25, 'billing_month' => now()->month, 'billing_year' => now()->year,
                    'paid_status' => false,
                ]);
            }
        }
    }
    auth()->logout();

    return compact('admin');
}

it('keeps page query counts free of N+1 regressions', function () {
    $f = probeFixture();

    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });

    $ceilings = [
        'admin.dashboard' => 80,
        'admin.tenants.index' => 60,
        'admin.floors.index' => 25,
        'admin.properties.index' => 20,
        'admin.users.index' => 20,
        'admin.revenue_expense.index' => 80,
        'admin.revenue_expense.record_income' => 30,
        'admin.revenue_expense.record_expense' => 30,
        'admin.revenue_expense.generate_bills' => 25,
        'admin.tenants.archived' => 20,
        'admin.billing.index' => 20,
    ];

    foreach ($ceilings as $route => $max) {
        $count = 0;
        Settings::flushMemo(); // fresh request semantics per page
        $this->actingAs($f['admin'])->get(route($route))->assertOk();
        expect($count)->toBeLessThanOrEqual($max, "GET {$route} ran {$count} queries (ceiling {$max})");
    }
});
