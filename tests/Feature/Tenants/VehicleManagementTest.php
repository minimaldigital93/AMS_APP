<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Property;
use App\Models\TenantVehicle;
use App\Models\Utilities;
use App\Services\RevenueExpense\MonthlyBillingService;
use Carbon\Carbon;

/**
 * The property-wide vehicle management page (Property Management → Vehicles).
 *
 * It only reads: every write goes to Shared\TenantVehicleController, the one
 * write path the tenant-detail card already uses, with `redirect_to=vehicles`
 * naming the page that submitted. What is pinned here is the layout by floor
 * and room, and the verification the page exists to give — a vehicle belongs to
 * a tenant, and the room comes through that tenant.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    // Log in before building fixtures so BelongsToAccount stamps account_id on
    // them — an unstamped row is the legacy "visible to everyone" case, which
    // would quietly defeat the per-account plate uniqueness below.
    auth()->login($this->admin);
    $this->floor = makeFloor('Floor 1');
    $this->room = makeApartment($this->floor, ['apartment_number' => 'A-101', 'status' => 'occupied']);
    $this->tenant = makeTenant($this->room, ['name' => 'Sophea']);
});

function addVehicle(array $overrides = []): array
{
    return array_merge([
        'vehicle_type' => 'motorbike',
        'plate_number' => '2A-1111',
        'monthly_fee' => 10,
        'redirect_to' => 'vehicles',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// The page
// ---------------------------------------------------------------------------

it('lists each vehicle under its floor and room', function () {
    TenantVehicle::create([
        'tenant_id' => $this->tenant->id,
        'vehicle_type' => 'car',
        'vehicle_model' => 'Toyota Vios',
        'plate_number' => '2B-2222',
        'monthly_fee' => 25,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.vehicles.index'))
        ->assertOk()
        ->assertSee('Floor 1')
        ->assertSee('A-101')
        ->assertSee('Sophea')
        ->assertSee('2B-2222')
        ->assertSee('Toyota Vios');
});

it('orders floors and rooms the way the Active Tenants roster does', function () {
    // Floor id — the order the building was entered, G → 1 → 2 — not
    // floor_name, which reads "1st, 2nd, Ground" and buries the ground floor.
    // Rooms sort naturally, so A-10 comes after A-2.
    $ground = makeFloor('Ground Floor');
    $second = makeFloor('2nd Floor');
    foreach ([[$ground, 'G-2'], [$ground, 'G-10'], [$second, 'C-1']] as [$floor, $number]) {
        $room = makeApartment($floor, ['apartment_number' => $number, 'status' => 'occupied']);
        $tenant = makeTenant($room, ['name' => 'T '.$number]);
        TenantVehicle::create([
            'tenant_id' => $tenant->id,
            'vehicle_type' => 'car',
            'plate_number' => 'P-'.$number,
            'monthly_fee' => 10,
        ]);
    }

    $html = $this->actingAs($this->admin)->get(route('admin.vehicles.index'))->assertOk()->getContent();

    $at = fn (string $needle) => mb_strpos($html, $needle);

    expect($at('Floor 1'))->toBeLessThan($at('Ground Floor'))
        ->and($at('Ground Floor'))->toBeLessThan($at('2nd Floor'))
        ->and($at('G-2'))->toBeLessThan($at('G-10'));
});

it('summarises the fleet and this month’s parking money', function () {
    // The first three tiles come off the vehicle rows — what the *next* bill
    // run will charge; the revenue tile comes off the parking charge rows —
    // what was actually billed and collected. They are deliberately different
    // questions, so a free vehicle counts as registered and bills nothing.
    TenantVehicle::create(['tenant_id' => $this->tenant->id, 'vehicle_type' => 'car', 'plate_number' => 'CAR-1', 'monthly_fee' => 25]);
    TenantVehicle::create(['tenant_id' => $this->tenant->id, 'vehicle_type' => 'motorbike', 'plate_number' => 'MOTO-1', 'monthly_fee' => 0]);

    $otherRoom = makeApartment($this->floor, ['apartment_number' => 'A-102', 'status' => 'occupied']);
    $otherTenant = makeTenant($otherRoom, ['name' => 'Dara']);
    TenantVehicle::create(['tenant_id' => $otherTenant->id, 'vehicle_type' => 'tuktuk', 'plate_number' => 'TUK-1', 'monthly_fee' => 15]);

    $now = Carbon::now();
    $paidRental = makeRental($this->tenant, $this->room);
    $unpaidRental = makeRental($otherTenant, $otherRoom);

    Utilities::create([
        'tenant_id' => $this->tenant->id, 'rental_id' => $paidRental->id, 'utility_type' => 'parking',
        'charge_amount' => 25, 'billing_month' => $now->month, 'billing_year' => $now->year,
        'paid_status' => true, 'paid_at' => $now->copy(),
    ]);
    Utilities::create([
        'tenant_id' => $otherTenant->id, 'rental_id' => $unpaidRental->id, 'utility_type' => 'parking',
        'charge_amount' => 15, 'billing_month' => $now->month, 'billing_year' => $now->year,
        'paid_status' => false,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.vehicles.index'))
        ->assertOk()
        ->assertViewHas('totalVehicles', 3)
        ->assertViewHas('billableCount', 2)
        ->assertViewHas('totalBilled', 40.0)
        ->assertViewHas('typeCounts', ['car' => 1, 'tuktuk' => 1, 'motorbike' => 1])
        ->assertViewHas('parkingRevenue', 25.0)
        ->assertViewHas('parkingOutstanding', 15.0);
});

it('renders for the supervisor panel, scoped to their assigned properties', function () {
    $property = Property::create(['name' => 'Main']);
    $ownFloor = Floors::create(['property_id' => $property->id, 'floor_name' => 'Own Floor']);
    $ownRoom = Apartments::create(['floor_id' => $ownFloor->id, 'apartment_number' => 'S-1', 'monthly_rent' => 400, 'status' => 'occupied']);
    $ownTenant = makeTenant($ownRoom, ['name' => 'Assigned Tenant']);
    TenantVehicle::create(['tenant_id' => $ownTenant->id, 'vehicle_type' => 'car', 'plate_number' => 'OWN-1', 'monthly_fee' => 5]);

    // Another property, not assigned to this supervisor.
    $other = Property::create(['name' => 'Other']);
    $otherFloor = Floors::create(['property_id' => $other->id, 'floor_name' => 'Other Floor']);
    $otherRoom = Apartments::create(['floor_id' => $otherFloor->id, 'apartment_number' => 'O-1', 'monthly_rent' => 400, 'status' => 'occupied']);
    $otherTenant = makeTenant($otherRoom, ['name' => 'Other Tenant']);
    TenantVehicle::create(['tenant_id' => $otherTenant->id, 'vehicle_type' => 'car', 'plate_number' => 'OTHER-1', 'monthly_fee' => 5]);

    $sup = makeSupervisor(['account_id' => $this->admin->id]);
    $property->update(['supervisor_id' => $sup->id]);

    $this->actingAs($sup)
        ->get(route('supervisor.vehicles.index'))
        ->assertOk()
        ->assertSee('OWN-1')
        ->assertDontSee('OTHER-1');
});

it('filters by vehicle type and by search', function () {
    TenantVehicle::create(['tenant_id' => $this->tenant->id, 'vehicle_type' => 'car', 'plate_number' => 'CAR-1', 'monthly_fee' => 25]);
    TenantVehicle::create(['tenant_id' => $this->tenant->id, 'vehicle_type' => 'motorbike', 'plate_number' => 'MOTO-1', 'monthly_fee' => 10]);

    $this->actingAs($this->admin)
        ->get(route('admin.vehicles.index', ['type' => 'car']))
        ->assertSee('CAR-1')
        ->assertDontSee('MOTO-1');

    // The search reaches the plate, the model, the tenant and the room number.
    $this->actingAs($this->admin)
        ->get(route('admin.vehicles.index', ['search' => 'moto']))
        ->assertSee('MOTO-1')
        ->assertDontSee('CAR-1');
});

// ---------------------------------------------------------------------------
// Writes — the same controller the tenant card posts to, returning here
// ---------------------------------------------------------------------------

it('adds a vehicle from the page and comes back to the page', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.tenants.vehicles.store', $this->tenant), addVehicle([
            'apartment_id' => $this->room->id,
        ]))
        ->assertRedirect(route('admin.vehicles.index'));

    expect(TenantVehicle::first()->plate_number)->toBe('2A-1111');
});

it('still returns to the tenant page when a write omits redirect_to', function () {
    // The card's delete still posts from the tenant page, and the routes stay
    // generic — without redirect_to the default must remain the tenant page.
    $this->actingAs($this->admin)
        ->post(route('admin.tenants.vehicles.store', $this->tenant), [
            'vehicle_type' => 'car', 'plate_number' => 'CARD-1', 'monthly_fee' => 5,
        ])
        ->assertRedirect(route('admin.tenants.show', $this->tenant->id));
});

it('sends the tenant card here to register a vehicle instead of duplicating the form', function () {
    // The card reads; this page owns add/edit. Two copies of the same form was
    // the duplication — and only one of them could edit.
    foreach (['admin', 'supervisor'] as $panel) {
        $this->actingAs($this->admin)
            ->get(route($panel.'.tenants.show', $this->tenant->id))
            ->assertOk()
            ->assertSee(route($panel.'.vehicles.index', ['search' => 'A-101']), false)
            ->assertDontSee('name="plate_number"', false);
    }
});

it('edits a vehicle in place', function () {
    $vehicle = TenantVehicle::create([
        'tenant_id' => $this->tenant->id, 'vehicle_type' => 'car',
        'vehicle_model' => 'Vios', 'plate_number' => '2B-2222', 'monthly_fee' => 25,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.tenants.vehicles.update', [$this->tenant, $vehicle]), [
            'vehicle_type' => 'tuktuk',
            'vehicle_model' => ' Bajaj  RE ',
            'plate_number' => ' 2c-3333 ',
            'monthly_fee' => 15,
            'apartment_id' => $this->room->id,
            'redirect_to' => 'vehicles',
        ])
        ->assertRedirect(route('admin.vehicles.index'))
        ->assertSessionHasNoErrors();

    $vehicle->refresh();

    // Normalisation is the store request's, shared by both verbs.
    expect($vehicle->vehicle_type)->toBe('tuktuk')
        ->and($vehicle->vehicle_model)->toBe('Bajaj RE')
        ->and($vehicle->plate_number)->toBe('2C-3333')
        ->and($vehicle->monthly_fee)->toEqual(15.0)
        ->and(TenantVehicle::count())->toBe(1);
});

it('lets an edit keep its own plate but not take another vehicle’s', function () {
    $vehicle = TenantVehicle::create(['tenant_id' => $this->tenant->id, 'vehicle_type' => 'car', 'plate_number' => 'KEEP-1', 'monthly_fee' => 25]);
    TenantVehicle::create(['tenant_id' => $this->tenant->id, 'vehicle_type' => 'motorbike', 'plate_number' => 'TAKEN-1', 'monthly_fee' => 10]);

    // Resubmitting the same plate is not a collision with itself.
    $this->actingAs($this->admin)
        ->put(route('admin.tenants.vehicles.update', [$this->tenant, $vehicle]), [
            'vehicle_type' => 'car', 'plate_number' => 'KEEP-1', 'monthly_fee' => 30,
        ])
        ->assertSessionHasNoErrors();

    expect($vehicle->fresh()->monthly_fee)->toEqual(30.0);

    // The other vehicle's plate still is.
    $this->actingAs($this->admin)
        ->put(route('admin.tenants.vehicles.update', [$this->tenant, $vehicle]), [
            'vehicle_type' => 'car', 'plate_number' => 'TAKEN-1', 'monthly_fee' => 30,
        ])
        ->assertSessionHasErrors('plate_number');
});

it('bills the fee assigned on this page, and rebills an edited one', function () {
    // The page writes the same tenant_vehicles rows the tenant card does, so a
    // fee set here is the tenant's parking charge on the next bill run — there
    // is no separate billing lane for the management page to miss.
    makeFiscalPeriod($this->admin);
    makeRental($this->tenant, $this->room);
    $service = new MonthlyBillingService;

    $this->actingAs($this->admin)
        ->post(route('admin.tenants.vehicles.store', $this->tenant), addVehicle([
            'apartment_id' => $this->room->id, 'monthly_fee' => 12,
        ]))
        ->assertSessionHasNoErrors();

    $service->processAll(Apartments::query(), Carbon::parse('2026-05-10'));

    $row = Utilities::where('utility_type', 'parking')->sole();
    expect($row->charge_amount)->toEqual(12.0);

    // Editing the fee restates what the *next* month bills; the month already
    // raised keeps its figure (closed money is never restated from here).
    $this->actingAs($this->admin)->put(route('admin.tenants.vehicles.update', [$this->tenant, TenantVehicle::first()]), [
        'vehicle_type' => 'motorbike', 'plate_number' => '2A-1111', 'monthly_fee' => 20,
        'apartment_id' => $this->room->id, 'redirect_to' => 'vehicles',
    ])->assertSessionHasNoErrors();

    $service->processAll(Apartments::query(), Carbon::parse('2026-06-10'));

    expect($row->fresh()->charge_amount)->toEqual(12.0)
        ->and(Utilities::where('utility_type', 'parking')->where('billing_month', 6)->sole()->charge_amount)->toEqual(20.0);
});

it('refuses to edit a vehicle through a tenant that does not own it', function () {
    $vehicle = TenantVehicle::create(['tenant_id' => $this->tenant->id, 'vehicle_type' => 'car', 'plate_number' => 'MINE-1', 'monthly_fee' => 25]);
    $other = makeTenant();

    $this->actingAs($this->admin)
        ->put(route('admin.tenants.vehicles.update', [$other, $vehicle]), [
            'vehicle_type' => 'car', 'plate_number' => 'MINE-1', 'monthly_fee' => 99,
        ])
        ->assertForbidden();

    expect($vehicle->fresh()->monthly_fee)->toEqual(25.0);
});

// ---------------------------------------------------------------------------
// Verification — the vehicle belongs to a tenant, the room comes through them
// ---------------------------------------------------------------------------

it('refuses a write whose room no longer holds that tenant', function () {
    // The page rendered the form under A-101; the tenant has since moved.
    $elsewhere = makeApartment($this->floor, ['apartment_number' => 'A-202']);
    $this->tenant->update(['apartment_id' => $elsewhere->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.tenants.vehicles.store', $this->tenant), addVehicle([
            'apartment_id' => $this->room->id,
        ]))
        ->assertSessionHasErrors('apartment_id');

    expect(TenantVehicle::count())->toBe(0);

    // The room they are actually in goes through.
    $this->actingAs($this->admin)
        ->post(route('admin.tenants.vehicles.store', $this->tenant), addVehicle([
            'apartment_id' => $elsewhere->id,
        ]))
        ->assertSessionHasNoErrors();

    expect(TenantVehicle::count())->toBe(1);
});

it('flags a vehicle whose tenant has moved out, and lets it be cleared', function () {
    $vehicle = TenantVehicle::create(['tenant_id' => $this->tenant->id, 'vehicle_type' => 'car', 'plate_number' => 'LEFT-1', 'monthly_fee' => 25]);

    // Moving out soft-deletes the tenant, so the FK cascade never fires and the
    // vehicle is left with no room to park in.
    $this->tenant->delete();

    expect($vehicle->fresh()->isVerified())->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('admin.vehicles.index'))
        ->assertOk()
        ->assertSee('LEFT-1')
        ->assertSee(__('messages.vehicle_tenant_departed'));

    // The destroy route binds the trashed tenant so the row can be cleared.
    $this->actingAs($this->admin)
        ->delete(route('admin.tenants.vehicles.destroy', [$this->tenant->id, $vehicle->id]), ['redirect_to' => 'vehicles'])
        ->assertRedirect(route('admin.vehicles.index'));

    expect(TenantVehicle::count())->toBe(0);
});

it('flags a vehicle whose tenant has no room', function () {
    $roomless = makeTenant(null, ['name' => 'Roomless', 'apartment_id' => null]);
    TenantVehicle::create(['tenant_id' => $roomless->id, 'vehicle_type' => 'motorbike', 'plate_number' => 'NOROOM-1', 'monthly_fee' => 0]);

    $this->actingAs($this->admin)
        ->get(route('admin.vehicles.index'))
        ->assertOk()
        ->assertSee('NOROOM-1')
        ->assertSee(__('messages.vehicle_no_room', ['name' => 'Roomless']), false);
});

it('keeps a vehicle in another account out of the list', function () {
    TenantVehicle::create(['tenant_id' => $this->tenant->id, 'vehicle_type' => 'car', 'plate_number' => 'MINE-1', 'monthly_fee' => 25]);

    $otherAdmin = makeAdmin(['name' => 'Other Account', 'phone' => '099888777']);
    $this->actingAs($otherAdmin)
        ->get(route('admin.vehicles.index'))
        ->assertOk()
        ->assertDontSee('MINE-1');
});

// ---------------------------------------------------------------------------
// Reaching the page on a phone
// ---------------------------------------------------------------------------

/**
 * On phones both panels suppress the hamburger ($useBottomNav) and replace the
 * sidebar with layouts/{,supervisor-}bottom-nav — so a page the bottom nav does
 * not list is unreachable there, however well it renders. Vehicles was missing
 * from both sheets. Any new Property Management page has to be added in three
 * places, not one.
 */
it('links to vehicles from the mobile bottom nav in both panels', function () {
    // The off-canvas sidebar is in the DOM on phones too (just unreachable), so
    // the assertion has to name the bottom-nav entry — `bn-sheet-link` — and not
    // merely the URL, or it passes with the bug in place.
    $sheetLink = fn (string $html, string $url) => (bool) preg_match(
        '/bn-sheet-link[^>]*"\s*>.*?'.preg_quote($url, '/').'|href="'.preg_quote($url, '/').'"[^>]*class="bn-sheet-link/s',
        $html
    );

    $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();
    expect($sheetLink($html, route('admin.vehicles.index')))->toBeTrue();

    $property = Property::create(['name' => 'Sup Property']);
    $sup = makeSupervisor(['account_id' => $this->admin->id]);
    $property->update(['supervisor_id' => $sup->id]);

    $html = $this->actingAs($sup)->get(route('supervisor.vehicles.index'))->assertOk()->getContent();
    expect($sheetLink($html, route('supervisor.vehicles.index')))->toBeTrue();
});
