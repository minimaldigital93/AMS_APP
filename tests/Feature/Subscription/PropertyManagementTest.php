<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Property;

it('renders the admin properties index with floor/room counts', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    $property = Property::create(['name' => 'Sunrise']);
    $floor = Floors::create(['property_id' => $property->id, 'floor_name' => 'F1']);
    Apartments::create(['floor_id' => $floor->id, 'apartment_number' => '101', 'monthly_rent' => 0, 'status' => 'available']);

    $this->get(route('admin.properties.index'))
        ->assertOk()
        ->assertSee('Sunrise');
});

it('creates a property from the admin form', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    $this->post(route('admin.properties.store'), ['name' => 'Riverside', 'address' => '12 Main St'])
        ->assertRedirect(route('admin.properties.index'));

    expect(Property::where('name', 'Riverside')->where('account_id', $admin->id)->exists())->toBeTrue();
});

it('blocks deleting a property that still has floors', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    $property = Property::create(['name' => 'HasFloors']);
    Floors::create(['property_id' => $property->id, 'floor_name' => 'F1']);

    $this->delete(route('admin.properties.destroy', $property))->assertSessionHas('error');

    expect(Property::whereKey($property->id)->exists())->toBeTrue();
});
