<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The tenant detail page's "Tenant Login" card lets an admin see and fix a
 * tenant's sign-in phone and reset their password, without hunting the
 * tenant's User row down in Team Management. It adds no new backend — both
 * forms post straight to the existing Admin\UserController actions that
 * already manage every login (tenant or staff), scoped to admin because those
 * routes only exist in the admin route group.
 *
 * `tenants.phone` (contact info, edited on the tenant edit page) and
 * `users.phone` (the login credential, managed here) are separate columns
 * that are never synced to each other — this file also pins that the card
 * touches one without disturbing the other.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    $this->apartment = makeApartment();
    $this->tenantUser = User::factory()->create([
        'account_id' => $this->admin->id,
        'phone' => '099-LOGIN-1',
    ]);
    seedRoles();
    $this->tenantUser->assignRole('tenant');
    $this->tenant = makeTenant($this->apartment, [
        'account_id' => $this->admin->id,
        'user_id' => $this->tenantUser->id,
        'phone' => '099-CONTACT-1',
    ]);
});

it('shows the tenant login card with the login phone, not the contact phone', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.tenants.show', $this->tenant));

    $response->assertOk()
        ->assertSee(__('messages.tenant_login_title'))
        ->assertSee('099-LOGIN-1')
        ->assertSee(__('messages.reset_password'));
});

it('hides the tenant login card from the supervisor panel', function () {
    $supervisor = makeSupervisor(['account_id' => $this->admin->id]);
    $property = Property::create(['name' => 'Sup Property', 'account_id' => $this->admin->id, 'supervisor_id' => $supervisor->id]);
    $this->apartment->floor->update(['property_id' => $property->id]);

    $response = $this->actingAs($supervisor)->get(route('supervisor.tenants.show', $this->tenant));

    $response->assertOk()->assertDontSee(__('messages.tenant_login_title'));
});

it('lets an admin reset a tenant login password without touching the contact phone', function () {
    $originalHash = $this->tenantUser->password;

    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-password', $this->tenantUser))
        ->assertRedirect();

    $this->tenantUser->refresh();
    $this->tenant->refresh();

    expect($this->tenantUser->password)->not->toBe($originalHash)
        ->and($this->tenant->phone)->toBe('099-CONTACT-1');

    session()->reflash();
    expect(session('password_reveal.name'))->toBe($this->tenantUser->name);
});

it('lets an admin correct a tenant login phone independently of the contact phone', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $this->tenantUser), [
            'name' => $this->tenantUser->name,
            'phone' => '099-LOGIN-2',
            'role' => 'tenant',
            'status' => $this->tenantUser->status,
        ])
        ->assertRedirect(route('admin.users.index'));

    $this->tenantUser->refresh();
    $this->tenant->refresh();

    expect($this->tenantUser->phone)->toBe('099-LOGIN-2')
        ->and($this->tenant->phone)->toBe('099-CONTACT-1')
        ->and($this->tenantUser->hasRole('tenant'))->toBeTrue();
});

it('refuses to manage a login belonging to another account', function () {
    $otherAdmin = makeAdmin(['phone' => '077-OTHER-ADMIN']);
    $otherTenantUser = User::factory()->create(['account_id' => $otherAdmin->id]);
    $otherTenantUser->assignRole('tenant');

    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-password', $otherTenantUser))
        ->assertNotFound();
});

/**
 * Regression, two bugs deep:
 *
 * 1. admin/users/edit.blade.php and superadmin/accounts/show.blade.php used to
 *    tell the admin the reset password would be "12345678" (both in the help
 *    text and the confirm dialog), while resetPassword() actually generates a
 *    random Str::random(10) value. An admin who trusted the on-page text
 *    handed the tenant a password that was never set.
 * 2. Even after that was fixed, the real password was glued into a sentence —
 *    "Password for X was reset to Ab12Cd34Ef." — with nothing but eyeballing
 *    to tell where it starts and ends. Selecting the whole sentence (easy to
 *    do by triple-click) and pasting it into the tenant's password field can
 *    never match. The password now arrives as its own `password_reveal`
 *    session value, rendered in a dedicated <code> block with a Copy button —
 *    see partials/flash.blade.php — instead of interpolated into prose.
 *
 * This pins that the exact value carried in that flash is what logs in.
 */
it('logs the tenant in with the actual password from the reset flash, not a hardcoded placeholder', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-password', $this->tenantUser))
        ->assertRedirect();

    session()->reflash();
    $newPassword = session('password_reveal.password');

    expect($newPassword)->not->toBeNull()
        ->and($newPassword)->not->toBe('12345678');

    Auth::logout();

    // The old hardcoded placeholder must not work.
    $this->post(route('login'), [
        'phone' => '099-LOGIN-1',
        'password' => '12345678',
    ])->assertSessionHasErrors('phone');
    $this->assertGuest();

    // The password actually shown in the flash must work.
    $this->post(route('login'), [
        'phone' => '099-LOGIN-1',
        'password' => $newPassword,
    ])->assertRedirect();
    $this->assertAuthenticatedAs($this->tenantUser);
});

it('does not promise a fixed placeholder password on the admin edit-user page', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.users.edit', $this->tenantUser));

    $response->assertOk()->assertDontSee('12345678');
});

it('renders the reset password in its own copyable code block, not glued into a sentence', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-password', $this->tenantUser))
        ->assertRedirect();

    $newPassword = session('password_reveal.password');

    $response = $this->actingAs($this->admin)->get(route('admin.tenants.show', $this->tenant));

    $response->assertOk()
        ->assertSee('<code', false)
        ->assertSee($newPassword)
        ->assertDontSee('reset to '.$newPassword.'.');
});
