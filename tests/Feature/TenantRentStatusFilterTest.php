<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Phase 7 (U10): the paid/overdue/unpaid tenant filter runs server-side via
 * ?rent_status=… so it spans every page of the paginated list — previously it
 * was Alpine-only and could only ever filter the visible page. These tests pin
 * the manually-paginated filter path (both panel twins) as renderable.
 */
beforeEach(function () {
    foreach (['superadmin', 'admin', 'supervisor', 'tenant'] as $r) {
        Role::findOrCreate($r, 'web');
    }
});

function rentFilterOwner(): User
{
    $u = User::factory()->create(['status' => 'active']);
    $u->update(['account_id' => $u->id]);
    $u->assignRole('admin');
    giveActiveSubscription($u);

    return $u;
}

it('renders the admin tenant list under each rent_status filter', function () {
    $admin = rentFilterOwner();

    foreach (['paid', 'overdue', 'unpaid', '', 'bogus'] as $status) {
        $this->actingAs($admin)
            ->get('/admin/tenants'.($status !== '' ? "?rent_status={$status}" : ''))
            ->assertOk();
    }
});

it('renders the supervisor tenant list under each rent_status filter', function () {
    $admin = rentFilterOwner();
    $sup = User::factory()->create(['status' => 'active', 'account_id' => $admin->id]);
    $sup->assignRole('supervisor');

    foreach (['paid', 'overdue', 'unpaid'] as $status) {
        $this->actingAs($sup)
            ->get("/supervisor/tenants?rent_status={$status}")
            ->assertOk();
    }
});
