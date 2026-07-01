<?php

use App\Models\Apartments;

/**
 * Apartments soft-delete, and soft-delete does NOT fire the DB onDelete('cascade')
 * to rentals/tenants. Deleting an *occupied* unit would therefore orphan the live
 * rental ($rental->apartment === null) and break ledger writes, so destroy() must
 * block it. A vacant / vacated unit can still be deleted.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
});

it('blocks deleting an apartment that still has an active tenant', function () {
    $apartment = makeApartment();
    $tenant = makeTenant($apartment);              // status = active
    makeRental($tenant, $apartment);                // end_date null → ongoing

    $this->from(route('admin.apartments.index'))
        ->delete(route('admin.apartments.destroy', $apartment))
        ->assertRedirect(route('admin.apartments.index'))
        ->assertSessionHas('error');

    // Still present (not soft-deleted).
    expect(Apartments::whereKey($apartment->id)->exists())->toBeTrue();
});

it('deletes a vacant apartment', function () {
    $apartment = makeApartment();

    $this->from(route('admin.apartments.index'))
        ->delete(route('admin.apartments.destroy', $apartment))
        ->assertRedirect(route('admin.apartments.index'))
        ->assertSessionHas('success');

    expect(Apartments::whereKey($apartment->id)->exists())->toBeFalse();
});

it('allows deleting an apartment whose tenant has moved out', function () {
    $apartment = makeApartment();
    $tenant = makeTenant($apartment, ['status' => 'inactive']);
    makeRental($tenant, $apartment, ['end_date' => now()->subDay()->toDateString()]);

    $this->from(route('admin.apartments.index'))
        ->delete(route('admin.apartments.destroy', $apartment))
        ->assertSessionHas('success');

    expect(Apartments::whereKey($apartment->id)->exists())->toBeFalse();
});
