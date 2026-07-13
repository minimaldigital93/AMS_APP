<?php

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\MonthlyPeriod;
use App\Services\FiscalPeriod\MonthlyPeriodManager;

/**
 * 2026-07 audit F5+F6: period date edits keep the monthly skeleton in sync
 * (and refuse to strand data), the close requires every month frozen, and the
 * closing balance is computed — never taken from the form.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => now()->startOfYear()->toDateString(),
        'closing_date' => now()->startOfYear()->addMonths(5)->endOfMonth()->toDateString(), // Jan–Jun
    ]);
    app(MonthlyPeriodManager::class)->generateForFiscalPeriod($this->period);
    auth()->logout();
});

function updatePayload(FiscalPeriods $p, array $overrides = []): array
{
    return array_merge([
        'name' => $p->name,
        'opening_date' => $p->opening_date->toDateString(),
        'closing_date' => $p->closing_date->toDateString(),
        'opening_assets' => 0,
        'opening_liabilities' => 0,
        'opening_equity' => 0,
    ], $overrides);
}

it('regenerates the monthly skeleton when the period dates change', function () {
    expect($this->period->monthlyPeriods()->count())->toBe(6);

    $this->actingAs($this->admin)
        ->put(route('admin.fiscalperiod.update', $this->period), updatePayload($this->period, [
            'closing_date' => now()->startOfYear()->addMonths(8)->endOfMonth()->toDateString(), // Jan–Sep
        ]))
        ->assertRedirect(route('admin.fiscalperiod.show', $this->period->id));

    expect($this->period->refresh()->monthlyPeriods()->count())->toBe(9);
});

it('refuses date edits that would strand ledger rows outside the period', function () {
    auth()->login($this->admin);
    Accounts::create([
        'fiscal_period_id' => $this->period->id, 'user_id' => $this->admin->id,
        'account_type' => 'income', 'category' => 'rent_income', 'amount' => 100,
        'transaction_date' => now()->startOfYear()->addMonths(4)->toDateString(), // May
    ]);
    auth()->logout();

    $this->actingAs($this->admin)
        ->put(route('admin.fiscalperiod.update', $this->period), updatePayload($this->period, [
            'closing_date' => now()->startOfYear()->addMonths(2)->endOfMonth()->toDateString(), // Jan–Mar
        ]))
        ->assertSessionHas('error');

    expect($this->period->refresh()->monthlyPeriods()->count())->toBe(6); // untouched
});

it('refuses date edits while a month is closed', function () {
    $this->period->monthlyPeriods()->orderBy('start_date')->first()->update(['status' => 'closed']);

    $this->actingAs($this->admin)
        ->put(route('admin.fiscalperiod.update', $this->period), updatePayload($this->period, [
            'closing_date' => now()->startOfYear()->addMonths(8)->endOfMonth()->toDateString(),
        ]))
        ->assertSessionHas('error');
});

it('refuses to close the period while months are open, then closes with a computed balance', function () {
    // Ledger: 300 income in January.
    auth()->login($this->admin);
    Accounts::create([
        'fiscal_period_id' => $this->period->id, 'user_id' => $this->admin->id,
        'account_type' => 'income', 'category' => 'rent_income', 'amount' => 300,
        'transaction_date' => now()->startOfYear()->addDays(9)->toDateString(),
    ]);
    auth()->logout();

    $close = fn () => $this->actingAs($this->admin)
        ->post(route('admin.fiscalperiod.closeperiod', $this->period), [
            'closing_balance' => 999999, // client value must be IGNORED
        ]);

    // Blocked while months are open.
    $close()->assertSessionHas('error');
    expect($this->period->refresh()->status)->toBe('open');

    MonthlyPeriod::where('fiscal_period_id', $this->period->id)->update(['status' => 'closed']);

    $close()->assertSessionHas('success');
    $this->period->refresh();
    expect($this->period->status)->toBe('closed')
        // Computed from the ledger cascade (0 opening + 300 income), not the form.
        ->and((float) $this->period->closing_balance)->toEqual(300.0);
});

it('rejects a new period overlapping an existing one', function () {
    $this->period->update(['status' => 'closed']); // pass the one-open-period guard

    $this->actingAs($this->admin)
        ->post(route('admin.fiscalperiod.store'), [
            'name' => 'Overlapping',
            'opening_date' => now()->startOfYear()->addMonths(3)->toDateString(), // April — inside Jan–Jun
            'closing_date' => now()->endOfYear()->toDateString(),
        ])
        ->assertSessionHasErrors('opening_date');

    expect(FiscalPeriods::count())->toBe(1);
});
