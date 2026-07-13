<?php

use App\Models\Accounts;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\User;

/**
 * 2026-07 audit F1–F4: the admin assign-tenant flow gains the guards the
 * supervisor twin already had (vacancy, unhoused existing tenant), random
 * passwords, a global-login-namespace phone rule, and a deposit booking that
 * survives having no open fiscal period.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin);
    $this->vacant = makeApartment(null, ['apartment_number' => 'G-101', 'status' => 'available', 'monthly_rent' => 400]);
    auth()->logout();
});

function assignPayload(array $overrides = []): array
{
    return array_merge([
        'tenant_option' => 'new',
        'name' => 'Guard Tenant',
        'phone' => '097'.random_int(1000000, 9999999),
        'move_in_date' => now()->toDateString(),
        'deposit' => 100,
    ], $overrides);
}

it('assigns a new tenant to a vacant room: rental, occupied status, deposit income, random password', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), assignPayload(['phone' => '0971112223']))
        ->assertRedirect(route('admin.floors.index'));

    $tenant = Tenants::where('phone', '0971112223')->sole();
    expect($this->vacant->refresh()->status)->toBe('occupied')
        ->and($tenant->apartment_id)->toBe($this->vacant->id)
        ->and(Rentals::where('apartment_id', $this->vacant->id)->whereNull('end_date')->count())->toBe(1)
        ->and(Accounts::where('category', Accounts::CAT_DEPOSIT_INCOME)->count())->toBe(1);

    // Login exists with the tenant role and NOT the old fixed password.
    $user = User::where('phone', '0971112223')->sole();
    expect($user->hasRole('tenant'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Hash::check('12345678', $user->password))->toBeFalse();
});

it('refuses to assign into an occupied room (no second rental)', function () {
    auth()->login($this->admin);
    $sitting = makeTenant($this->vacant);
    makeRental($sitting, $this->vacant);
    $this->vacant->update(['status' => 'occupied']);
    auth()->logout();

    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), assignPayload())
        ->assertSessionHas('error');

    expect(Rentals::where('apartment_id', $this->vacant->id)->count())->toBe(1)
        ->and(Tenants::count())->toBe(1);
});

it('refuses to assign an existing tenant who still occupies another room', function () {
    auth()->login($this->admin);
    $otherRoom = makeApartment(null, ['apartment_number' => 'G-102', 'status' => 'occupied']);
    $housed = makeTenant($otherRoom, ['phone' => '555-7777']);
    auth()->logout();

    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), [
            'tenant_option' => 'existing',
            'tenant_id' => $housed->id,
            'move_in_date' => now()->toDateString(),
            'deposit' => 0,
        ])
        ->assertSessionHas('error');

    // Nothing moved: old room still theirs, target room untouched.
    expect($housed->refresh()->apartment_id)->toBe($otherRoom->id)
        ->and($this->vacant->refresh()->status)->toBe('available')
        ->and(Rentals::where('apartment_id', $this->vacant->id)->count())->toBe(0);
});

it('rejects a new tenant whose phone belongs to another account\'s login (global namespace)', function () {
    $otherAdmin = makeAdmin();
    auth()->login($otherAdmin);
    makeSupervisor(['account_id' => $otherAdmin->id, 'phone' => '0977776666']);
    auth()->logout();

    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), assignPayload(['phone' => '0977776666']))
        ->assertSessionHasErrors('phone');
});

it('still assigns (without booking deposit income) when no fiscal period is open', function () {
    $this->period->update(['status' => 'closed']);

    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), assignPayload(['phone' => '0973334445']))
        ->assertRedirect(route('admin.floors.index'));

    // The assignment succeeded (previously a NOT NULL violation 500'd it)…
    expect($this->vacant->refresh()->status)->toBe('occupied')
        ->and(Rentals::where('apartment_id', $this->vacant->id)->count())->toBe(1)
        // …and no ledger row was written without a period to hold it.
        ->and(Accounts::count())->toBe(0);
});
