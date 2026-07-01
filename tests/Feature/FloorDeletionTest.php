<?php

use App\Models\Floors;

/**
 * Floors soft-delete without cascading to apartments (the DB onDelete('cascade')
 * only fires on hard delete), so deleting a floor that still has rooms would
 * orphan them ($apartment->floor === null). destroy() must block it; an empty
 * floor (or one whose rooms were already removed) can still be deleted.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
});

it('blocks deleting a floor that still has apartments', function () {
    $floor = makeFloor();
    makeApartment($floor);

    $this->from(route('admin.floors.index'))
        ->delete(route('admin.floors.destroy', $floor))
        ->assertRedirect(route('admin.floors.index'))
        ->assertSessionHas('error');

    expect(Floors::whereKey($floor->id)->exists())->toBeTrue();
});

it('deletes an empty floor', function () {
    $floor = makeFloor();

    $this->from(route('admin.floors.index'))
        ->delete(route('admin.floors.destroy', $floor))
        ->assertRedirect(route('admin.floors.index'))
        ->assertSessionHas('success');

    expect(Floors::whereKey($floor->id)->exists())->toBeFalse();
});

it('allows deleting a floor whose apartments were already removed', function () {
    $floor = makeFloor();
    $apartment = makeApartment($floor);
    $apartment->delete(); // soft-delete the room first

    $this->from(route('admin.floors.index'))
        ->delete(route('admin.floors.destroy', $floor))
        ->assertSessionHas('success');

    expect(Floors::whereKey($floor->id)->exists())->toBeFalse();
});
