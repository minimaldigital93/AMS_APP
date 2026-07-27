<?php

use App\Models\Apartments;
use App\Models\Tenants;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\RevenueExpense\BreakEvenService;
use App\Services\RevenueExpense\RevenueExpenseQueryService;

/**
 * Maintenance mode takes a room out of the *rentable stock*: no tenant may be
 * assigned to it, and it is excluded from occupancy / expected-revenue /
 * break-even denominators so it never reads as a room the owner failed to rent.
 *
 * The one thing it must NOT do is rewrite history — income booked while the room
 * was still let stays in the reports.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
});

it('turns maintenance mode on from the switch, saving on its own', function () {
    $apartment = makeApartment();

    $this->from(route('admin.apartments.edit', $apartment))
        ->put(route('admin.apartments.maintenance', $apartment), ['under_maintenance' => 1])
        ->assertRedirect(route('admin.apartments.edit', $apartment))
        ->assertSessionHas('success');

    expect($apartment->fresh()->under_maintenance)->toBeTrue();
});

it('turns maintenance mode back off from the switch', function () {
    $apartment = makeApartment(null, ['under_maintenance' => true]);

    $this->from(route('admin.apartments.edit', $apartment))
        ->put(route('admin.apartments.maintenance', $apartment), ['under_maintenance' => 0])
        ->assertSessionHas('success');

    expect($apartment->fresh()->under_maintenance)->toBeFalse();
});

it('says nothing when the switch is submitted twice in the same state', function () {
    $apartment = makeApartment(null, ['under_maintenance' => true]);

    $this->from(route('admin.apartments.edit', $apartment))
        ->put(route('admin.apartments.maintenance', $apartment), ['under_maintenance' => 1])
        ->assertSessionMissing('success');

    expect($apartment->fresh()->under_maintenance)->toBeTrue();
});

it('refuses to switch on maintenance mode while a tenant still lives there', function () {
    $apartment = makeApartment(null, ['status' => 'occupied']);
    $tenant = makeTenant($apartment);
    makeRental($tenant, $apartment);

    $this->from(route('admin.apartments.edit', $apartment))
        ->put(route('admin.apartments.maintenance', $apartment), ['under_maintenance' => 1])
        ->assertSessionHas('error');

    expect($apartment->fresh()->under_maintenance)->toBeFalse();
});

// The main "Update Room" submit no longer carries the flag, but a stale tab
// still can — the same occupied guard has to hold on that path too.
it('refuses maintenance mode posted through the room update form while occupied', function () {
    $apartment = makeApartment(null, ['status' => 'occupied']);
    $tenant = makeTenant($apartment);
    makeRental($tenant, $apartment);

    $this->from(route('admin.apartments.edit', $apartment))
        ->put(route('admin.apartments.update', $apartment), [
            'apartment_number' => $apartment->apartment_number,
            'monthly_rent' => 500,
            'status' => 'occupied',
            'under_maintenance' => 1,
        ])->assertSessionHas('error');

    expect($apartment->fresh()->under_maintenance)->toBeFalse();
});

// The switch was pulled out of the main form on purpose (as an in-form field
// users flipped it and never pressed Save). That means "Update Room" posts no
// under_maintenance key at all — it must leave the stored flag alone rather
// than reading the absent field as "off" and silently returning the unit to
// the rentable stock.
it('keeps maintenance mode on when the room update form is saved without the switch', function () {
    $apartment = makeApartment(null, ['under_maintenance' => true]);

    $this->from(route('admin.apartments.edit', $apartment))
        ->put(route('admin.apartments.update', $apartment), [
            'apartment_number' => $apartment->apartment_number,
            'floor_id' => $apartment->floor_id,
            'monthly_rent' => 600,
            'status' => 'available',
        ])->assertSessionHasNoErrors();

    $apartment->refresh();
    expect($apartment->under_maintenance)->toBeTrue()
        ->and((float) $apartment->monthly_rent)->toBe(600.0);
});

it('blocks assigning a tenant to a room under maintenance', function () {
    $apartment = makeApartment(null, ['under_maintenance' => true]);

    $this->from(route('admin.floors.index'))
        ->post(route('admin.apartments.assignTenant', $apartment), contractAssignPayload())
        ->assertSessionHas('error');

    expect($apartment->fresh()->status)->toBe('available');
    expect(Tenants::where('apartment_id', $apartment->id)->exists())->toBeFalse();
});

it('rejects a maintenance room as the target of tenant create', function () {
    $apartment = makeApartment(null, ['under_maintenance' => true]);

    $this->from(route('admin.tenants.create'))
        ->post(route('admin.tenants.store'), [
            'apartment_id' => $apartment->id,
            'name' => 'Walk In',
            'phone' => '0961234567',
            'move_in_date' => now()->toDateString(),
            'status' => 'active',
        ])->assertSessionHasErrors('apartment_id');
});

it('leaves maintenance rooms out of the assignable room list', function () {
    $open = makeApartment();
    $closed = makeApartment(null, ['under_maintenance' => true]);

    $ids = Apartments::rentable()->where('status', 'available')->pluck('id');

    expect($ids)->toContain($open->id)
        ->and($ids)->not->toContain($closed->id);
});

it('excludes maintenance rooms from dashboard occupancy counts', function () {
    $floor = makeFloor();
    $occupied = makeApartment($floor, ['status' => 'occupied']);
    makeApartment($floor);                                  // available
    makeApartment($floor, ['under_maintenance' => true]);    // out of stock

    $tenant = makeTenant($occupied);
    makeRental($tenant, $occupied);

    $stats = (new DashboardStatsService($this->admin->id))
        ->build(now()->startOfMonth(), now()->endOfMonth(), now());

    // 3 physical rooms, but only 2 are rentable stock.
    expect($stats['apartments']['total'])->toBe(2)
        ->and($stats['apartments']['available'])->toBe(1)
        ->and($stats['apartments']['occupied'])->toBe(1)
        ->and($stats['apartments']['maintenance'])->toBe(1)
        ->and($stats['apartments']['total_all'])->toBe(3);

    // Occupancy is 1/2 = 50%, not 1/3 = 33%.
    expect($stats['floor_occupancy'][0])->toBe(50.0);
});

it('marks maintenance rooms in the dashboard floor quick view', function () {
    $floor = makeFloor();
    $occupied = makeApartment($floor, ['status' => 'occupied', 'apartment_number' => 'A1']);
    makeApartment($floor, ['apartment_number' => 'A2']);                                 // available
    makeApartment($floor, ['apartment_number' => 'A3', 'under_maintenance' => true]);     // out of stock

    $tenant = makeTenant($occupied);
    makeRental($tenant, $occupied);

    $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

    // Rentable stock only on both sides of the ratio: 1 of 2, not 1 of 3.
    expect($html)->toContain('1/2')
        // …and the maintenance room is called out rather than reading as vacant.
        ->and($html)->toContain('A3 — '.__('messages.maintenance_short'));
});

it('excludes maintenance rooms from the break-even unit count', function () {
    $period = makeFiscalPeriod($this->admin);
    makeApartment();
    makeApartment(null, ['under_maintenance' => true]);

    $queries = new RevenueExpenseQueryService($this->admin->id, $period, Apartments::query());
    $snapshot = (new BreakEvenService($queries, $this->admin->id, $period, Apartments::query()))
        ->calculate(now()->month, now()->year);

    // 2 physical rooms, 1 rentable — occupancy is measured against the latter.
    expect($snapshot['total_apartments'])->toBe(1);
});

// The maintenance flag has no history: the rentable count is today's state
// while occupancy is the viewed month's. A room that was let back then and is
// out of inventory now must not report more tenants than rooms — that rendered
// a >100% donut with a negative SVG dash gap (which browsers drop entirely).
it('never reports more rented units than rentable ones for a past month', function () {
    $period = makeFiscalPeriod($this->admin);

    $kept = makeApartment(null, ['status' => 'occupied']);
    makeRental(makeTenant($kept), $kept, ['start_date' => now()->subMonths(4)->startOfMonth()]);

    $closed = makeApartment(null, ['status' => 'occupied']);
    makeRental(makeTenant($closed), $closed, [
        'start_date' => now()->subMonths(4)->startOfMonth(),
        'end_date' => now()->subMonths(2)->endOfMonth(),
    ]);
    // Tenant moved out, then the room left the rentable stock.
    $closed->update(['status' => 'available', 'under_maintenance' => true]);

    $past = now()->subMonths(3);
    $queries = new RevenueExpenseQueryService($this->admin->id, $period, Apartments::query());
    $service = new BreakEvenService($queries, $this->admin->id, $period, Apartments::query());
    $snapshot = $service->calculate($past->month, $past->year);

    // Both rooms were let that month, so both count on both sides — 2 of 2.
    expect($snapshot['current_occupancy'])->toBe(2)
        ->and($snapshot['total_apartments'])->toBe(2);

    // …and the same flooring holds for every month of the trend chart.
    $trend = $service->getBusinessHealth($snapshot, $past->month, $past->year)['trend'];
    expect(collect($trend)->max('occupancy_pct'))->toBeLessThanOrEqual(100);
});

it('reports no rent due for a room under maintenance', function () {
    $period = makeFiscalPeriod($this->admin);
    makeApartment(null, ['under_maintenance' => true]);

    $rows = (new RevenueExpenseQueryService($this->admin->id, $period, Apartments::query()))
        ->calculatePerApartmentData(now()->startOfMonth(), now()->endOfMonth());

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['rent_due'])->toBe(0.0)
        ->and($rows[0]['under_maintenance'])->toBeTrue()
        ->and($rows[0]['status'])->toBe('maintenance');
});

// --- Render smoke checks: every surface that shows the maintenance state ---
it('renders the room edit page with the maintenance toggle', function () {
    $apartment = makeApartment();
    $this->get(route('admin.apartments.edit', $apartment))
        ->assertOk()
        ->assertSee('under_maintenance', false)
        ->assertSee(__('messages.maintenance_mode'));
});

it('renders the room edit page for an occupied room with the toggle disabled', function () {
    $apartment = makeApartment(null, ['status' => 'occupied']);
    $tenant = makeTenant($apartment);
    makeRental($tenant, $apartment);
    $this->get(route('admin.apartments.edit', $apartment))
        ->assertOk()
        ->assertSee(__('messages.maintenance_blocked_occupied'));
});

it('renders the floors quick view with a maintenance room', function () {
    $floor = makeFloor();
    makeApartment($floor, ['under_maintenance' => true]);
    makeApartment($floor);
    $this->get(route('admin.floors.index'))->assertOk()->assertSee('Maintenance');
});

it('renders the floor layout with a maintenance room', function () {
    $floor = makeFloor();
    makeApartment($floor, ['under_maintenance' => true]);
    $this->get(route('admin.floors.plan3d'))->assertOk()->assertSee('Maintenance');
});

it('renders the floor edit page with a maintenance room', function () {
    $floor = makeFloor();
    makeApartment($floor, ['under_maintenance' => true]);
    $this->get(route('admin.floors.edit', $floor))->assertOk()->assertSee('Maintenance');
});

it('renders the apartment show page for a maintenance room', function () {
    $apartment = makeApartment(null, ['under_maintenance' => true]);
    $this->get(route('admin.apartments.show', $apartment))->assertOk()->assertSee('Maintenance');
});
