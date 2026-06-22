<?php

use App\Models\FiscalPeriods;
use App\Models\PlatformFiscalPeriod;
use App\Models\User;

/**
 * Invariant under test:
 *   Only ONE fiscal period may be open at a time — admin (per-account books) and
 *   superadmin (platform finance) alike. A new period can't be created while the
 *   current one is still open; the current one must be closed first.
 *
 *   This keeps the "active period" unambiguous (getActiveFiscalPeriod() and the
 *   fiscal.period middleware both assume a single open period) and the platform
 *   carry-forward chain unbroken.
 */

// ── Admin ──────────────────────────────────────────────────────────────────

it('blocks the admin create form while a period is open', function () {
    $admin = makeAdmin();
    makeFiscalPeriod($admin); // open by default

    $this->actingAs($admin)
        ->get(route('admin.fiscalperiod.create'))
        ->assertRedirect(route('admin.fiscalperiod.index'))
        ->assertSessionHas('warning');
});

it('refuses an admin store while a period is open, even if the form is bypassed', function () {
    $admin = makeAdmin();
    makeFiscalPeriod($admin);

    $this->actingAs($admin)
        ->post(route('admin.fiscalperiod.store'), [
            'name' => 'Second Period',
            'opening_date' => '2027-01-01',
            'closing_date' => '2027-12-31',
        ])
        ->assertRedirect(route('admin.fiscalperiod.index'));

    // No second period was created.
    expect(FiscalPeriods::where('user_id', $admin->id)->count())->toBe(1);
});

it('lets the admin open a new period once the current one is closed', function () {
    $admin = makeAdmin();
    makeFiscalPeriod($admin, ['status' => 'closed']);

    $this->actingAs($admin)
        ->get(route('admin.fiscalperiod.create'))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.fiscalperiod.store'), [
            'name' => 'Next Period',
            'opening_date' => '2027-01-01',
            'closing_date' => '2027-12-31',
        ])
        ->assertRedirect(route('admin.dashboard'));

    expect(FiscalPeriods::where('user_id', $admin->id)->where('status', 'open')->count())->toBe(1);
});

// ── Superadmin (platform finance) ────────────────────────────────────────────

function makeSuperadmin(): User
{
    seedRoles();
    $user = User::factory()->create();
    $user->assignRole('superadmin');

    return $user;
}

it('refuses a superadmin platform period while one is open', function () {
    $superadmin = makeSuperadmin();
    PlatformFiscalPeriod::create([
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'opening_balance' => 0,
        'status' => 'open',
    ]);

    $this->actingAs($superadmin)
        ->post(route('superadmin.finance.period.store'), [
            'name' => 'FY 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ])
        ->assertRedirect(route('superadmin.finance.index'))
        ->assertSessionHas('warning');

    expect(PlatformFiscalPeriod::count())->toBe(1);
});

it('lets a superadmin open a platform period once the current one is closed', function () {
    $superadmin = makeSuperadmin();
    PlatformFiscalPeriod::create([
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'opening_balance' => 0,
        'status' => 'closed',
    ]);

    $this->actingAs($superadmin)
        ->post(route('superadmin.finance.period.store'), [
            'name' => 'FY 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ])
        ->assertSessionHas('success');

    expect(PlatformFiscalPeriod::where('status', 'open')->count())->toBe(1);
});

it('refuses to reopen a closed platform period while a later one is open', function () {
    $superadmin = makeSuperadmin();
    // Closing a period spins up an open successor — reopening the closed one
    // would leave two periods open at once, breaking the single-open invariant.
    $closed = PlatformFiscalPeriod::create([
        'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        'opening_balance' => 0, 'status' => 'closed',
    ]);
    PlatformFiscalPeriod::create([
        'name' => 'FY 2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
        'opening_balance' => 0, 'status' => 'open',
    ]);

    $this->actingAs($superadmin)
        ->post(route('superadmin.finance.period.reopen', $closed))
        ->assertSessionHas('warning');

    expect($closed->fresh()->status)->toBe('closed');
    expect(PlatformFiscalPeriod::where('status', 'open')->count())->toBe(1);
});

it('blocks creating a platform period whose dates overlap another', function () {
    $superadmin = makeSuperadmin();
    PlatformFiscalPeriod::create([
        'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        'opening_balance' => 0, 'status' => 'closed', // closed, so the single-open guard passes
    ]);

    $this->actingAs($superadmin)
        ->post(route('superadmin.finance.period.store'), [
            'name' => 'Overlapping', 'start_date' => '2026-06-01', 'end_date' => '2027-05-31',
        ])
        ->assertSessionHas('warning');

    expect(PlatformFiscalPeriod::count())->toBe(1);
});

it('blocks re-dating a platform period onto another period’s range', function () {
    $superadmin = makeSuperadmin();
    PlatformFiscalPeriod::create([
        'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        'opening_balance' => 0, 'status' => 'closed',
    ]);
    $later = PlatformFiscalPeriod::create([
        'name' => 'FY 2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
        'opening_balance' => 0, 'status' => 'open',
    ]);

    $this->actingAs($superadmin)
        ->put(route('superadmin.finance.period.update', $later), [
            'name' => 'FY 2027', 'start_date' => '2026-06-01', 'end_date' => '2027-12-31',
        ])
        ->assertSessionHas('warning');

    expect($later->fresh()->start_date->toDateString())->toBe('2027-01-01');
});
