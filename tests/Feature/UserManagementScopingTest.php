<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * The {user} route parameter binds against the GLOBAL users table (User is not
 * account-scoped), so every mutating action in Admin\UserController must assert
 * the target belongs to the acting admin's account — otherwise admin A could
 * set a new password on / delete / demote admin B's team (or admin B).
 */
beforeEach(function () {
    $this->adminA = makeAdmin(['phone' => '0700000001']);
    $this->adminB = makeAdmin(['phone' => '0700000002']);
    $this->supervisorB = makeSupervisor(['phone' => '0700000003', 'account_id' => $this->adminB->id]);
});

function validUserUpdatePayload(User $user): array
{
    return [
        'name' => 'Renamed',
        'phone' => $user->phone,
        'role' => 'supervisor',
    ];
}

it('404s when an admin edits another accounts user', function () {
    $this->actingAs($this->adminA)
        ->get(route('admin.users.edit', $this->supervisorB))
        ->assertNotFound();
});

it('404s when an admin updates another accounts user', function () {
    $this->actingAs($this->adminA)
        ->put(route('admin.users.update', $this->supervisorB), array_merge(
            validUserUpdatePayload($this->supervisorB),
            ['password' => 'hijacked-password'],
        ))
        ->assertNotFound();

    expect($this->supervisorB->fresh()->name)->not->toBe('Renamed');
});

it('404s when an admin deletes another accounts user', function () {
    $this->actingAs($this->adminA)
        ->delete(route('admin.users.destroy', $this->supervisorB))
        ->assertNotFound();

    expect(User::whereKey($this->supervisorB->id)->exists())->toBeTrue();
});

it('404s when an admin deletes another admin', function () {
    $this->actingAs($this->adminA)
        ->delete(route('admin.users.destroy', $this->adminB))
        ->assertNotFound();

    expect(User::whereKey($this->adminB->id)->exists())->toBeTrue();
});

it('404s when an admin changes the role of another accounts user', function () {
    $role = Role::findByName('tenant');

    $this->actingAs($this->adminA)
        ->patch(route('admin.users.updateRole', $this->supervisorB), ['role' => $role->id])
        ->assertNotFound();

    expect($this->supervisorB->fresh()->hasRole('supervisor'))->toBeTrue();
});

it('404s when an admin assigns permissions to another accounts user', function () {
    $this->actingAs($this->adminA)
        ->post(route('admin.users.permissions', $this->supervisorB), ['permissions' => []])
        ->assertNotFound();
});

it('403s when an admin tries to update or delete themselves via user management', function () {
    $this->actingAs($this->adminA)
        ->put(route('admin.users.update', $this->adminA), validUserUpdatePayload($this->adminA))
        ->assertForbidden();

    $this->actingAs($this->adminA)
        ->delete(route('admin.users.destroy', $this->adminA))
        ->assertForbidden();
});

it('still lets an admin manage their own team members', function () {
    $ownSupervisor = makeSupervisor(['phone' => '0700000004', 'account_id' => $this->adminA->id]);

    $this->actingAs($this->adminA)
        ->put(route('admin.users.update', $ownSupervisor), validUserUpdatePayload($ownSupervisor))
        ->assertRedirect(route('admin.users.index'));

    expect($ownSupervisor->fresh()->name)->toBe('Renamed');

    $this->actingAs($this->adminA)
        ->delete(route('admin.users.destroy', $ownSupervisor))
        ->assertRedirect(route('admin.users.index'));

    expect(User::whereKey($ownSupervisor->id)->exists())->toBeFalse();
});

it('resets a team member password to a random value, not the old fixed default', function () {
    $ownSupervisor = makeSupervisor(['phone' => '0700000005', 'account_id' => $this->adminA->id]);

    $this->actingAs($this->adminA)
        ->post(route('admin.users.reset-password', $ownSupervisor))
        ->assertSessionHas('success');

    expect(Hash::check('12345678', $ownSupervisor->fresh()->password))->toBeFalse();
});
