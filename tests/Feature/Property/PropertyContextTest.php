<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Property;
use App\Models\Tenants;
use App\Services\Property\PropertyContext;

/**
 * Build a Property → Floor → Apartment → Tenant tree owned by $admin's account.
 * account_id is set as an attribute (Property guards it) so the account scope
 * isolates it correctly; the property chain (floors.property_id) drives filtering.
 */
function makePropertyTree(\App\Models\User $admin, string $name): array
{
    $property = new Property(['name' => $name]);
    $property->account_id = $admin->id;
    $property->save();

    $floor = Floors::create(['floor_name' => $name.' F1', 'property_id' => $property->id]);
    $apartment = Apartments::create([
        'floor_id' => $floor->id,
        'apartment_number' => $name.'-101',
        'monthly_rent' => 500,
        'status' => 'available',
    ]);
    $tenant = Tenants::create([
        'apartment_id' => $apartment->id,
        'name' => $name.' Tenant',
        'phone' => '555-'.\Illuminate\Support\Str::random(6),
        'status' => 'active',
        'deposit' => 0,
    ]);

    return compact('property', 'floor', 'apartment', 'tenant');
}

/** Fresh PropertyContext (drops per-request memoization after a context change). */
function freshContext(): PropertyContext
{
    app()->forgetInstance(PropertyContext::class);

    return app(PropertyContext::class);
}

it('exposes only the account\'s properties and defaults to the first', function () {
    $admin = makeAdmin();
    $a = makePropertyTree($admin, 'Alpha');
    $b = makePropertyTree($admin, 'Bravo');

    $this->actingAs($admin);

    $context = freshContext();
    $ids = $context->accessibleProperties()->pluck('id')->all();

    expect($ids)->toContain($a['property']->id, $b['property']->id)
        ->and($context->activePropertyId())->toBe($a['property']->id) // alphabetical first
        ->and($context->selectorEnabled())->toBeTrue();
});

it('filters models to the active property', function () {
    $admin = makeAdmin();
    makePropertyTree($admin, 'Alpha');
    $b = makePropertyTree($admin, 'Bravo');

    $this->actingAs($admin);
    freshContext()->setActiveProperty($b['property']->id);

    expect(Floors::forActiveProperty()->pluck('id')->all())->toBe([$b['floor']->id])
        ->and(Apartments::forActiveProperty()->pluck('id')->all())->toBe([$b['apartment']->id])
        ->and(Tenants::forActiveProperty()->pluck('id')->all())->toBe([$b['tenant']->id]);
});

it('persists the selection to the session and the user row', function () {
    $admin = makeAdmin();
    makePropertyTree($admin, 'Alpha');
    $b = makePropertyTree($admin, 'Bravo');

    $this->actingAs($admin)
        ->post(route('property.switch'), ['property_id' => $b['property']->id])
        ->assertRedirect();

    expect(session(PropertyContext::SESSION_KEY))->toBe($b['property']->id)
        ->and($admin->fresh()->last_property_id)->toBe($b['property']->id);
});

it('restores the remembered property after the session is cleared (re-login)', function () {
    $admin = makeAdmin();
    makePropertyTree($admin, 'Alpha');
    $b = makePropertyTree($admin, 'Bravo');
    $admin->forceFill(['last_property_id' => $b['property']->id])->save();

    $this->actingAs($admin);

    // No session selection yet → falls back to the remembered property, not the first.
    expect(freshContext()->activePropertyId())->toBe($b['property']->id);
});

it('rejects switching to a property from another account', function () {
    $admin = makeAdmin();
    makePropertyTree($admin, 'Mine');

    $other = makeAdmin(['name' => 'Other Admin']);
    $foreign = makePropertyTree($other, 'Theirs');

    $this->actingAs($admin)
        ->post(route('property.switch'), ['property_id' => $foreign['property']->id])
        ->assertForbidden();

    // And the context never resolves a foreign property as active.
    expect(freshContext()->accessiblePropertyIds()->all())
        ->not->toContain($foreign['property']->id);
});

it('renders the property selector in the top bar', function () {
    $admin = makeAdmin();
    makePropertyTree($admin, 'Alpha');
    makePropertyTree($admin, 'Bravo');

    $this->actingAs($admin)
        ->get(route('admin.floors.index'))
        ->assertOk()
        ->assertSee('Alpha')                    // active property name in the selector button
        ->assertSee(route('property.switch'));  // the switch form action
});

it('disables the selector and auto-selects when only one property exists', function () {
    $admin = makeAdmin();
    $only = makePropertyTree($admin, 'Solo');

    $this->actingAs($admin);

    $context = freshContext();
    expect($context->selectorEnabled())->toBeFalse()
        ->and($context->hasSingleProperty())->toBeTrue()
        ->and($context->activePropertyId())->toBe($only['property']->id);
});
