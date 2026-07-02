<?php

use App\Models\Accounts;
use App\Models\FiscalPeriods;

/**
 * accounts.fiscal_period_id is ON DELETE CASCADE, so deleting a fiscal period
 * that has ledger rows would hard-delete its entire income/expense history —
 * and a closed period is frozen history. destroy() must refuse both; only an
 * open period with no recorded transactions may be removed.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
});

it('deletes an open fiscal period with no transactions', function () {
    $period = makeFiscalPeriod($this->admin);

    $this->delete(route('admin.fiscalperiod.destroy', $period))
        ->assertRedirect(route('admin.fiscalperiod.index'))
        ->assertSessionHas('success');

    expect(FiscalPeriods::whereKey($period->id)->exists())->toBeFalse();
});

it('blocks deleting a closed fiscal period', function () {
    $period = makeFiscalPeriod($this->admin, ['status' => 'closed']);

    $this->delete(route('admin.fiscalperiod.destroy', $period))
        ->assertRedirect(route('admin.fiscalperiod.index'))
        ->assertSessionHas('error');

    expect(FiscalPeriods::whereKey($period->id)->exists())->toBeTrue();
});

it('blocks deleting a fiscal period that has ledger transactions', function () {
    $period = makeFiscalPeriod($this->admin);

    $entry = Accounts::create([
        'fiscal_period_id' => $period->id,
        'user_id' => $this->admin->id,
        'account_type' => Accounts::TYPE_INCOME,
        'category' => Accounts::CAT_RENT_INCOME,
        'description' => 'Rent',
        'amount' => 500,
        'transaction_date' => now()->toDateString(),
    ]);

    $this->delete(route('admin.fiscalperiod.destroy', $period))
        ->assertRedirect(route('admin.fiscalperiod.index'))
        ->assertSessionHas('error');

    expect(FiscalPeriods::whereKey($period->id)->exists())->toBeTrue()
        ->and(Accounts::whereKey($entry->id)->exists())->toBeTrue();
});
