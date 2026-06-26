<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Property;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Unit numbers and floor names are unique within their property, not across the
 * whole account — two properties may each have a "Floor 1" and a unit "101".
 */
it('allows the same unit number in two different properties', function () {
    $admin = makeAdmin();
    actingAs($admin);

    $p1 = Property::create(['name' => 'Alpha']);
    $p2 = Property::create(['name' => 'Bravo']);
    $f1 = Floors::create(['property_id' => $p1->id, 'floor_name' => 'F1']);
    $f2 = Floors::create(['property_id' => $p2->id, 'floor_name' => 'F1']);

    Apartments::create(['floor_id' => $f1->id, 'apartment_number' => '101', 'monthly_rent' => 0, 'status' => 'available']);

    post(route('admin.apartments.store'), [
        'apartment_number' => '101',
        'floor_id' => $f2->id,
        'monthly_rent' => 0,
        'status' => 'available',
    ])->assertSessionHasNoErrors();

    expect(Apartments::where('apartment_number', '101')->count())->toBe(2);
    expect(Apartments::where('floor_id', $f2->id)->value('property_id'))->toBe($p2->id);
});

it('blocks the same unit number within one property, even across floors', function () {
    $admin = makeAdmin();
    actingAs($admin);

    $p1 = Property::create(['name' => 'Alpha']);
    $f1 = Floors::create(['property_id' => $p1->id, 'floor_name' => 'F1']);
    $f2 = Floors::create(['property_id' => $p1->id, 'floor_name' => 'F2']);

    Apartments::create(['floor_id' => $f1->id, 'apartment_number' => '101', 'monthly_rent' => 0, 'status' => 'available']);

    post(route('admin.apartments.store'), [
        'apartment_number' => '101',
        'floor_id' => $f2->id,
        'monthly_rent' => 0,
        'status' => 'available',
    ])->assertSessionHasErrors('apartment_number');

    expect(Apartments::where('apartment_number', '101')->count())->toBe(1);
});

it('blocks a duplicate floor name within the active property but allows it in another', function () {
    $admin = makeAdmin();
    actingAs($admin);

    $p1 = Property::create(['name' => 'Alpha']);
    $p2 = Property::create(['name' => 'Bravo']);
    Floors::create(['property_id' => $p1->id, 'floor_name' => 'Ground Floor']);

    // Active property defaults to Alpha (alphabetical first) — duplicate rejected.
    post(route('admin.floors.store'), ['floor_name' => 'Ground Floor'])
        ->assertSessionHasErrors('floor_name');

    expect(Floors::where('property_id', $p1->id)->count())->toBe(1);

    // Same name is fine once the active property is Bravo.
    post(route('property.switch'), ['property_id' => $p2->id]);
    post(route('admin.floors.store'), ['floor_name' => 'Ground Floor'])
        ->assertSessionHasNoErrors();

    expect(Floors::where('property_id', $p2->id)->where('floor_name', 'Ground Floor')->count())->toBe(1);
});
