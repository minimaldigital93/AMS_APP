<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * The supervisor panel (/supervisor/*) is gated role:supervisor|admin|superadmin.
 * Supervisors own it; admin (the account owner) and superadmin (platform owner)
 * may also enter it to view/preview. These tests pin that access in place.
 */
beforeEach(function () {
    foreach (['superadmin', 'admin', 'supervisor', 'tenant'] as $r) {
        Role::findOrCreate($r, 'web');
    }
});

function makeOwner(string $role): User
{
    $u = User::factory()->create(['status' => 'active']);
    $u->update(['account_id' => $u->id]);
    $u->assignRole($role);

    return $u;
}

// Pages that don't depend on MySQL-only YEAR()/MONTH() fiscal aggregation,
// so they're safe to assert "no server error" on the SQLite test DB.
const NON_FISCAL_PAGES = [
    '/supervisor/apartments',
    '/supervisor/tenants',
    '/supervisor/settings',
];

const ALL_PAGES = [
    '/supervisor/dashboard',
    '/supervisor/apartments',
    '/supervisor/tenants',
    '/supervisor/settings',
];

it('still lets a supervisor in (no regression)', function () {
    $admin = makeOwner('admin');
    $sup = User::factory()->create(['status' => 'active', 'account_id' => $admin->id]);
    $sup->assignRole('supervisor');

    $this->actingAs($sup);
    foreach (ALL_PAGES as $uri) {
        expect($this->get($uri)->getStatusCode())->not->toBe(403, "supervisor GET {$uri}");
    }
    foreach (NON_FISCAL_PAGES as $uri) {
        expect($this->get($uri)->getStatusCode())->toBeLessThan(500, "supervisor GET {$uri}");
    }
});

it('now lets an admin into the supervisor panel (no 403)', function () {
    $admin = makeOwner('admin');
    makeFiscalPeriod($admin);

    $this->actingAs($admin);
    foreach (ALL_PAGES as $uri) {
        expect($this->get($uri)->getStatusCode())->not->toBe(403, "admin GET {$uri}");
    }
    foreach (NON_FISCAL_PAGES as $uri) {
        expect($this->get($uri)->getStatusCode())->toBeLessThan(500, "admin GET {$uri}");
    }
});

it('now lets a superadmin into the supervisor panel (no 403)', function () {
    $super = makeOwner('superadmin');

    $this->actingAs($super);
    foreach (ALL_PAGES as $uri) {
        expect($this->get($uri)->getStatusCode())->not->toBe(403, "superadmin GET {$uri}");
    }
    foreach (NON_FISCAL_PAGES as $uri) {
        expect($this->get($uri)->getStatusCode())->toBeLessThan(500, "superadmin GET {$uri}");
    }
});

it('still blocks a tenant from the supervisor panel (403)', function () {
    $admin = makeOwner('admin');
    $tenant = User::factory()->create(['status' => 'active', 'account_id' => $admin->id]);
    $tenant->assignRole('tenant');

    $this->actingAs($tenant);
    expect($this->get('/supervisor/dashboard')->getStatusCode())->toBe(403);
});
