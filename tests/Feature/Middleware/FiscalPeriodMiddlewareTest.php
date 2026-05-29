<?php

it('lets admin through when their own fiscal period is open', function () {
    $admin = makeAdmin();
    makeFiscalPeriod($admin);

    $this->actingAs($admin)
        ->get(route('admin.revenue_expense.index'))
        ->assertOk();
});

it('redirects admin to fiscal-period create when no open period exists', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->get(route('admin.revenue_expense.index'))
        ->assertRedirect(route('admin.fiscalperiod.create'));
});

it('lets supervisor through when an admin has an open period', function () {
    $admin = makeAdmin();
    makeFiscalPeriod($admin);
    $supervisor = makeSupervisor();

    $this->actingAs($supervisor)
        ->get(route('supervisor.revenue_expense.index'))
        ->assertOk();
});

it('redirects supervisor to supervisor dashboard (not admin create) when no admin period is open', function () {
    $supervisor = makeSupervisor();
    makeAdmin(); // exists but has no open period

    $this->actingAs($supervisor)
        ->get(route('supervisor.revenue_expense.index'))
        ->assertRedirect(route('supervisor.dashboard'));
});

it('does not bounce supervisor through their own user_id check', function () {
    // Even if the supervisor has *zero* of their own fiscal periods, the
    // middleware should only care about the admin's. This guards against a
    // regression to the old `where('user_id', Auth::id())` behaviour.
    $admin = makeAdmin();
    makeFiscalPeriod($admin);
    $supervisor = makeSupervisor();

    $this->actingAs($supervisor)
        ->get(route('supervisor.revenue_expense.record_income'))
        ->assertOk();
});
