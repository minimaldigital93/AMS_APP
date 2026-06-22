<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;

function makeAdminOnPlan(array $planAttrs): array
{
    $admin = makeAdmin();

    $plan = Plan::create(array_merge([
        'slug' => 'test-'.uniqid(),
        'name' => 'Test',
        'price_usd' => 12,
        'billing_period_days' => 30,
        'is_active' => true,
    ], $planAttrs));

    // Replace the default unlimited subscription makeAdmin attached.
    giveActiveSubscription($admin, $plan);

    return [$admin, $plan];
}

it('allows properties up to the plan cap and blocks beyond it', function () {
    [$admin] = makeAdminOnPlan(['max_properties' => 2]);
    $this->actingAs($admin);

    $svc = app(SubscriptionService::class);
    expect($svc->canAddProperties($admin->id))->toBeTrue();

    Property::create(['name' => 'P1']);
    Property::create(['name' => 'P2']);

    expect($svc->canAddProperties($admin->id))->toBeFalse();
});

it('blocks the property store route once the cap is reached', function () {
    [$admin] = makeAdminOnPlan(['max_properties' => 1]);
    $this->actingAs($admin);

    Property::create(['name' => 'P1']);

    $this->post(route('admin.properties.store'), ['name' => 'P2'])
        ->assertSessionHas('error');

    expect(Property::count())->toBe(1);
});

it('allows rooms up to the plan cap and blocks beyond it', function () {
    [$admin] = makeAdminOnPlan(['max_rooms' => 2]);
    $this->actingAs($admin);

    $property = Property::create(['name' => 'P1']);
    $floor = Floors::create(['property_id' => $property->id, 'floor_name' => 'F1']);

    $svc = app(SubscriptionService::class);
    expect($svc->canAddRooms($admin->id))->toBeTrue();

    Apartments::create(['floor_id' => $floor->id, 'apartment_number' => '101', 'monthly_rent' => 0, 'status' => 'available']);
    Apartments::create(['floor_id' => $floor->id, 'apartment_number' => '102', 'monthly_rent' => 0, 'status' => 'available']);

    expect($svc->canAddRooms($admin->id))->toBeFalse();
});

it('blocks the room store route once the cap is reached', function () {
    [$admin] = makeAdminOnPlan(['max_rooms' => 1]);
    $this->actingAs($admin);

    $property = Property::create(['name' => 'P1']);
    $floor = Floors::create(['property_id' => $property->id, 'floor_name' => 'F1']);
    Apartments::create(['floor_id' => $floor->id, 'apartment_number' => '101', 'monthly_rent' => 0, 'status' => 'available']);

    $this->post(route('admin.apartments.store'), [
        'apartment_number' => '102',
        'floor_id' => $floor->id,
        'monthly_rent' => 0,
        'status' => 'available',
    ])->assertSessionHas('error');

    expect(Apartments::count())->toBe(1);
});

it('blocks creating a second supervisor past the staff cap', function () {
    [$admin] = makeAdminOnPlan(['max_staff' => 1]);
    $this->actingAs($admin);

    $this->post(route('admin.users.store'), [
        'name' => 'Sup One', 'phone' => '011111111', 'password' => 'Password123!', 'role' => 'supervisor',
    ]);

    $this->post(route('admin.users.store'), [
        'name' => 'Sup Two', 'phone' => '022222222', 'password' => 'Password123!', 'role' => 'supervisor',
    ])->assertSessionHas('error');

    expect(User::where('account_id', $admin->id)->role('supervisor')->count())->toBe(1);
});

it('treats unlimited plans as never capped', function () {
    [$admin] = makeAdminOnPlan(['max_properties' => null, 'max_rooms' => null, 'max_staff' => null, 'max_floors' => null]);
    $this->actingAs($admin);

    $svc = app(SubscriptionService::class);
    expect($svc->canAddProperties($admin->id, 999))->toBeTrue();
    expect($svc->canAddRooms($admin->id, 999))->toBeTrue();
    expect($svc->canAddStaff($admin->id, 999))->toBeTrue();
    expect($svc->canAddFloors($admin->id, 999))->toBeTrue();
});
