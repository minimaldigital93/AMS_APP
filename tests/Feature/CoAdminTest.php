<?php

use App\Models\Accounts;
use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Spatie\Permission\Models\Role;

/**
 * An admin may hand out the admin role, creating a CO-ADMIN of the same
 * account — not a second account. Two things have to hold for that to be safe:
 *
 *  1. The co-admin lands in the OWNER's books. Fiscal periods and ledger rows
 *     hang off the account owner's user id, so anything that used to key off
 *     Auth::id() for an admin now keys off current_account_id(). Otherwise a
 *     co-admin would be pushed to open a second fiscal period and their
 *     income/expenses would disappear from the owner's reports.
 *  2. The owner row itself is never editable, demotable or deletable from team
 *     management — its user id IS the account id every row hangs off.
 */
beforeEach(function () {
    $this->owner = makeAdmin(['phone' => '0710000001']);
});

function makeCoAdmin(User $owner, array $overrides = []): User
{
    seedRoles();
    $user = User::factory()->create(array_merge([
        'name' => 'Co Admin',
        'phone' => '0710009'.random_int(100, 999),
        'account_id' => $owner->id,
    ], $overrides));
    $user->assignRole('admin');

    return $user;
}

it('lets an admin create another admin on their own account', function () {
    $this->actingAs($this->owner)
        ->post(route('admin.users.store'), [
            'name' => 'Second Admin',
            'phone' => '0710000002',
            'password' => 'correct-horse-battery',
            'role' => 'admin',
        ])
        ->assertRedirect(route('admin.users.index'));

    $created = User::where('phone', '0710000002')->first();

    expect($created)->not->toBeNull()
        ->and($created->hasRole('admin'))->toBeTrue()
        // The account stays the owner's — this is a co-admin, not a new tenant account.
        ->and($created->account_id)->toBe($this->owner->id);
});

it('promotes a supervisor to admin from the roster role picker', function () {
    $supervisor = makeSupervisor(['phone' => '0710000003', 'account_id' => $this->owner->id]);

    $this->actingAs($this->owner)
        ->patch(route('admin.users.updateRole', $supervisor), ['role' => Role::findByName('admin')->id])
        ->assertRedirect(route('admin.users.index'));

    expect($supervisor->fresh()->hasRole('admin'))->toBeTrue();
});

it('resolves a co-admin to the owners fiscal period rather than sending them to create one', function () {
    makeFiscalPeriod($this->owner);
    $coAdmin = makeCoAdmin($this->owner);

    // revenue_expense sits behind the fiscal.period gate; a co-admin with no
    // period of their own must ride on the owner's.
    $this->actingAs($coAdmin)
        ->get(route('admin.revenue_expense.index'))
        ->assertOk();
});

it('books a co-admins expense into the owners ledger', function () {
    $period = makeFiscalPeriod($this->owner);
    $coAdmin = makeCoAdmin($this->owner);

    $this->actingAs($coAdmin)
        ->post(route('admin.revenue_expense.store_other_expense'), [
            'category' => 'maintenance',
            'description' => 'Co-admin recorded repair',
            'amount' => 42,
            'transaction_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    $row = Accounts::where('description', 'like', '%Co-admin recorded repair%')->first();

    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($this->owner->id)
        ->and($row->fiscal_period_id)->toBe($period->id);
});

it('never lets the account owner row be demoted, edited or deleted from team management', function () {
    $coAdmin = makeCoAdmin($this->owner);

    $this->actingAs($coAdmin)
        ->patch(route('admin.users.updateRole', $this->owner), ['role' => Role::findByName('supervisor')->id])
        ->assertSessionHas('error');

    expect($this->owner->fresh()->hasRole('admin'))->toBeTrue();

    $this->actingAs($coAdmin)->get(route('admin.users.edit', $this->owner))->assertForbidden();
    $this->actingAs($coAdmin)->delete(route('admin.users.destroy', $this->owner))->assertForbidden();
    $this->actingAs($coAdmin)->post(route('admin.users.reset-password', $this->owner))->assertForbidden();

    expect(User::whereKey($this->owner->id)->exists())->toBeTrue();
});

it('never lets an admin change or delete their own row from team management', function () {
    $coAdmin = makeCoAdmin($this->owner);

    $this->actingAs($coAdmin)
        ->patch(route('admin.users.updateRole', $coAdmin), ['role' => Role::findByName('supervisor')->id])
        ->assertSessionHas('error');

    expect($coAdmin->fresh()->hasRole('admin'))->toBeTrue();

    $this->actingAs($coAdmin)->delete(route('admin.users.destroy', $coAdmin))->assertForbidden();
});

it('lets the owner manage a co-admin like any other team member', function () {
    $coAdmin = makeCoAdmin($this->owner, ['phone' => '0710000004']);

    $this->actingAs($this->owner)
        ->put(route('admin.users.update', $coAdmin), [
            'name' => 'Renamed Co Admin',
            'phone' => '0710000004',
            'role' => 'supervisor',
        ])
        ->assertRedirect(route('admin.users.index'));

    $coAdmin->refresh();

    expect($coAdmin->name)->toBe('Renamed Co Admin')
        ->and($coAdmin->hasRole('supervisor'))->toBeTrue();
});

it('counts co-admins against the plan staff cap but not the owner', function () {
    $plan = Plan::create([
        'slug' => 'staff-cap-'.uniqid(),
        'name' => 'Capped',
        'price_usd' => 12,
        'billing_period_days' => 30,
        'is_active' => true,
        'max_staff' => 1,
    ]);
    giveActiveSubscription($this->owner, $plan);

    $svc = app(SubscriptionService::class);

    // The owner is the subscription, not a seat under it.
    expect($svc->staffCount($this->owner->id))->toBe(0);

    makeCoAdmin($this->owner);

    expect($svc->staffCount($this->owner->id))->toBe(1)
        ->and($svc->canAddStaff($this->owner->id))->toBeFalse();

    $this->actingAs($this->owner)
        ->post(route('admin.users.store'), [
            'name' => 'Third Admin',
            'phone' => '0710000005',
            'password' => 'correct-horse-battery',
            'role' => 'admin',
        ])
        ->assertSessionHas('error');

    expect(User::where('phone', '0710000005')->exists())->toBeFalse();
});

it('does not let a co-admin inflate the superadmin account count', function () {
    makeCoAdmin($this->owner);

    seedRoles();
    $superadmin = User::factory()->create(['phone' => '0710000008']);
    $superadmin->assignRole('superadmin');

    // One account = one owner row, however many admins work inside it.
    $this->actingAs($superadmin)
        ->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertViewHas('accountsCount', 1);
});

it('still 404s a co-admin acting on another accounts user', function () {
    $coAdmin = makeCoAdmin($this->owner);
    $otherOwner = makeAdmin(['phone' => '0710000006']);
    $otherSupervisor = makeSupervisor(['phone' => '0710000007', 'account_id' => $otherOwner->id]);

    $this->actingAs($coAdmin)
        ->delete(route('admin.users.destroy', $otherSupervisor))
        ->assertNotFound();
});
