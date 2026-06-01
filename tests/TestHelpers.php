<?php

use App\Models\Apartments;
use App\Models\FiscalPeriods;
use App\Models\Floors;
use App\Models\Plan;
use App\Models\Rentals;
use App\Models\Subscription;
use App\Models\Tenants;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Test scaffolding helpers shared across feature tests.
 *
 * These intentionally use direct model creates instead of factories — we don't
 * have factories for domain models yet, and the create-shape is what we want
 * the test code to assert against anyway.
 */
function seedRoles(): void
{
    foreach (['superadmin', 'admin', 'supervisor', 'tenant'] as $name) {
        Role::findOrCreate($name, 'web');
    }
}

function makeAdmin(array $overrides = []): User
{
    seedRoles();
    $user = User::factory()->create(array_merge([
        'name' => 'Test Admin',
    ], $overrides));
    $user->assignRole('admin');
    // An admin owns its own account and (by default) has an active, unlimited
    // subscription so the subscription.active gate lets it use admin routes.
    $user->forceFill(['account_id' => $user->id])->save();
    giveActiveSubscription($user);

    return $user;
}

/**
 * Attach an active subscription to an account. Defaults to an unlimited plan so
 * tests aren't accidentally limited; pass a plan to test specific caps.
 */
function giveActiveSubscription(User $account, ?Plan $plan = null): Subscription
{
    $plan ??= Plan::firstOrCreate(
        ['slug' => 'test-unlimited'],
        ['name' => 'Test Unlimited', 'price_usd' => 0, 'max_floors' => null, 'max_apartments' => null, 'billing_period_days' => 30, 'is_active' => true]
    );

    return Subscription::updateOrCreate(
        ['account_id' => $account->id],
        ['plan_id' => $plan->id, 'status' => 'active', 'started_at' => now(), 'expires_at' => now()->addMonth()]
    );
}

function makeSupervisor(array $overrides = []): User
{
    seedRoles();
    $user = User::factory()->create(array_merge([
        'name' => 'Test Supervisor',
    ], $overrides));
    $user->assignRole('supervisor');

    return $user;
}

function makeFiscalPeriod(User $owner, array $overrides = []): FiscalPeriods
{
    return FiscalPeriods::create(array_merge([
        'user_id' => $owner->id,
        'name' => 'Test Period',
        'opening_date' => now()->startOfYear()->toDateString(),
        'closing_date' => now()->endOfYear()->toDateString(),
        'opening_balance' => 0,
        'closing_balance' => 0,
        'status' => 'open',
    ], $overrides));
}

function makeFloor(string $name = 'Floor 1'): Floors
{
    return Floors::create(['floor_name' => $name]);
}

function makeApartment(?Floors $floor = null, array $overrides = []): Apartments
{
    $floor ??= makeFloor();
    static $counter = 0;
    $counter++;

    return Apartments::create(array_merge([
        'floor_id' => $floor->id,
        'apartment_number' => 'TEST-'.$counter,
        'monthly_rent' => 500,
        'status' => 'available',
    ], $overrides));
}

function makeTenant(?Apartments $apartment = null, array $overrides = []): Tenants
{
    $apartment ??= makeApartment();
    static $counter = 0;
    $counter++;

    return Tenants::create(array_merge([
        'apartment_id' => $apartment->id,
        'name' => 'Tenant '.$counter,
        'email' => 'tenant'.$counter.'@example.test',
        'phone' => '555-0000',
        'move_in_date' => now()->subMonths(2)->toDateString(),
        'status' => 'active',
        'deposit' => 500,
    ], $overrides));
}

function makeRental(Tenants $tenant, ?Apartments $apartment = null, array $overrides = []): Rentals
{
    $apartment ??= $tenant->apartment ?? makeApartment();

    return Rentals::create(array_merge([
        'apartment_id' => $apartment->id,
        'tenant_id' => $tenant->id,
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => null,
        'rent_amount' => 500,
        'deposit' => 500,
    ], $overrides));
}
