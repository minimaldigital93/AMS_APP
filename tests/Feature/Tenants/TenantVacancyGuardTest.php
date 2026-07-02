<?php

use App\Models\Rentals;
use App\Models\Tenants;

/**
 * Checking a tenant in (or moving them) must verify server-side that the target
 * room is actually vacant — the create form only lists available units, but the
 * raw apartment_id is client-supplied and assigning an occupied unit would
 * double-book it.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
});

function tenantStorePayload(int $apartmentId, string $phone): array
{
    return [
        'apartment_id' => $apartmentId,
        'name' => 'New Tenant',
        'phone' => $phone,
        'move_in_date' => now()->toDateString(),
        'status' => 'active',
        'deposit' => 0,
    ];
}

it('rejects checking a tenant into an occupied room', function () {
    $occupied = makeApartment(null, ['status' => 'occupied']);

    $this->post(route('admin.tenants.store'), tenantStorePayload($occupied->id, '0711111111'))
        ->assertSessionHasErrors('apartment_id');

    expect(Tenants::where('name', 'New Tenant')->exists())->toBeFalse();
});

it('checks a tenant into a vacant room and occupies it', function () {
    $vacant = makeApartment();

    $this->post(route('admin.tenants.store'), tenantStorePayload($vacant->id, '0722222222'))
        ->assertRedirect(route('admin.tenants.index'))
        ->assertSessionHas('success');

    $tenant = Tenants::where('name', 'New Tenant')->firstOrFail();
    expect($vacant->fresh()->status)->toBe('occupied')
        ->and(Rentals::where('tenant_id', $tenant->id)->where('apartment_id', $vacant->id)->exists())->toBeTrue();
});

it('rejects moving a tenant into an occupied room on update', function () {
    $roomA = makeApartment(null, ['status' => 'occupied']);
    $tenant = makeTenant($roomA, ['phone' => '0733333333']);
    makeRental($tenant, $roomA);

    $roomB = makeApartment(null, ['status' => 'occupied']); // someone else's room

    $this->put(route('admin.tenants.update', $tenant), [
        'apartment_id' => $roomB->id,
        'name' => $tenant->name,
        'phone' => $tenant->phone,
        'move_in_date' => $tenant->move_in_date->toDateString(),
        'status' => 'active',
    ])->assertSessionHasErrors('apartment_id');
});

it('allows an update that keeps the tenant in their current (occupied) room', function () {
    $roomA = makeApartment(null, ['status' => 'occupied']);
    $tenant = makeTenant($roomA, ['phone' => '0744444444']);
    makeRental($tenant, $roomA);

    $this->put(route('admin.tenants.update', $tenant), [
        'apartment_id' => $roomA->id,
        'name' => 'Renamed Tenant',
        'phone' => $tenant->phone,
        'move_in_date' => $tenant->move_in_date->toDateString(),
        'status' => 'active',
    ])->assertSessionHas('success');

    expect($tenant->fresh()->name)->toBe('Renamed Tenant');
});
