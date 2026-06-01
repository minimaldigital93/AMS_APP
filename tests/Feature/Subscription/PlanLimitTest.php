<?php

use App\Models\Floors;
use App\Models\Plan;
use App\Models\Subscription;
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

it('allows floors up to the plan cap and blocks beyond it', function () {
    [$admin, $plan] = makeAdminOnPlan(['max_floors' => 2, 'max_apartments' => 10]);
    $this->actingAs($admin);

    $svc = app(SubscriptionService::class);
    expect($svc->canAddFloors($admin->id))->toBeTrue();

    Floors::create(['floor_name' => 'F1']);
    Floors::create(['floor_name' => 'F2']);

    expect($svc->canAddFloors($admin->id))->toBeFalse();
});

it('blocks the floor store route once the cap is reached', function () {
    [$admin] = makeAdminOnPlan(['max_floors' => 1, 'max_apartments' => 10]);
    $this->actingAs($admin);

    Floors::create(['floor_name' => 'F1']);

    $this->post(route('admin.floors.store'), ['floor_name' => 'F2'])
        ->assertSessionHas('error');

    expect(Floors::count())->toBe(1);
});

it('treats unlimited plans as never capped', function () {
    [$admin] = makeAdminOnPlan(['max_floors' => null, 'max_apartments' => null]);
    $this->actingAs($admin);

    foreach (range(1, 6) as $i) {
        Floors::create(['floor_name' => 'F'.$i]);
    }

    $svc = app(SubscriptionService::class);
    expect($svc->canAddFloors($admin->id))->toBeTrue();
    expect($svc->canAddApartments($admin->id, 999))->toBeTrue();
});
