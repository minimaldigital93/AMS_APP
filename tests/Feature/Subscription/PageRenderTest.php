<?php

use App\Models\Plan;
use App\Models\User;

beforeEach(function () {
    seedRoles();
    Plan::create(['slug' => 'basic', 'name' => 'Basic', 'price_usd' => 12, 'max_floors' => 2, 'max_apartments' => 10, 'billing_period_days' => 30, 'is_active' => true]);
    Plan::create(['slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24, 'max_floors' => 4, 'max_apartments' => 200, 'billing_period_days' => 30, 'is_active' => true]);
    Plan::create(['slug' => 'max', 'name' => 'Max', 'price_usd' => 50, 'max_floors' => null, 'max_apartments' => null, 'billing_period_days' => 30, 'is_active' => true]);
});

it('renders the login page with the pricing modal', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Subscribe')
        ->assertSee('Choose your plan');
});

it('renders the signup form for a selected plan', function () {
    $this->get(route('subscribe.create', ['plan' => 'pro']))
        ->assertOk()
        ->assertSee('Create your account');
});

it('renders the admin billing page', function () {
    $admin = makeAdmin(); // provisioned with active subscription
    $this->actingAs($admin)
        ->get(route('admin.billing.index'))
        ->assertOk()
        ->assertSee('Billing');
});

it('renders every superadmin platform page', function () {
    $su = User::factory()->create();
    $su->assignRole('superadmin');
    $su->forceFill(['account_id' => $su->id])->save();

    foreach (['superadmin.dashboard', 'superadmin.accounts.index', 'superadmin.plans.index', 'superadmin.subscriptions.index'] as $route) {
        $this->actingAs($su)->get(route($route))->assertOk();
    }
});

it('redirects an admin with no active subscription to billing', function () {
    $admin = makeAdmin();
    // Drop the auto-provisioned subscription.
    $admin->subscription()->delete();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.billing.index'));
});
