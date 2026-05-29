<?php

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\User;
use App\Services\FiscalPeriod\FiscalPeriodReportsService;

/**
 * Accounting invariant under test:
 *   The post-closing trial balance reads straight from the auto-calculated
 *   balance sheet — assets on the debit side, liabilities + equity on the
 *   credit side. Because the opening figures must balance (A = L + E) and
 *   operations move assets and equity by the same amount (retained earnings),
 *   the trial balance self-balances by construction, whether the period made a
 *   profit or a loss and whether or not it carries opening liabilities.
 */
function fiscalPeriodForTrialBalance(
    User $user,
    float $assets = 1000,
    float $liabilities = 0,
    ?float $equity = null,
): FiscalPeriods {
    $equity ??= $assets - $liabilities;

    return FiscalPeriods::create([
        'user_id' => $user->id,
        'name' => 'FY Trial Balance',
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
        'opening_balance' => $assets,
        'opening_assets' => $assets,
        'opening_liabilities' => $liabilities,
        'opening_equity' => $equity,
        'closing_balance' => 0,
        'status' => 'open',
    ]);
}

function trialBalanceFor(FiscalPeriods $fp): array
{
    return app(FiscalPeriodReportsService::class)->trialBalance($fp);
}

it('self-balances for an operations-only profit', function () {
    $user = User::factory()->create();
    $fp = fiscalPeriodForTrialBalance($user, assets: 1000);

    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
        'description' => 'Rent', 'amount' => 500, 'transaction_date' => '2026-03-15',
    ]);
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_EXPENSE, 'category' => Accounts::CAT_UTILITIES_EXPENSE,
        'description' => 'Water', 'amount' => 200, 'transaction_date' => '2026-03-20',
    ]);

    $tb = trialBalanceFor($fp);

    // retained earnings = 500 − 200 = 300
    expect($tb['retained_earnings'])->toBe(300.0)
        ->and($tb['is_balanced'])->toBeTrue()
        // debit: assets 1000 + 300 = 1300 ; credit: equity 1000 + 300 = 1300
        ->and($tb['total_debits'])->toBe(1300.0)
        ->and($tb['total_credits'])->toBe(1300.0);
});

it('self-balances for an operations-only loss', function () {
    $user = User::factory()->create();
    $fp = fiscalPeriodForTrialBalance($user, assets: 1000);

    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
        'description' => 'Rent', 'amount' => 100, 'transaction_date' => '2026-03-15',
    ]);
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_EXPENSE, 'category' => Accounts::CAT_UTILITIES_EXPENSE,
        'description' => 'Water', 'amount' => 400, 'transaction_date' => '2026-03-20',
    ]);

    $tb = trialBalanceFor($fp);

    // retained earnings = 100 − 400 = −300
    expect($tb['retained_earnings'])->toBe(-300.0)
        ->and($tb['is_balanced'])->toBeTrue()
        // debit: assets 1000 − 300 = 700 ; credit: equity 1000 − 300 = 700
        ->and($tb['total_debits'])->toBe(700.0)
        ->and($tb['total_credits'])->toBe(700.0);
});

it('self-balances with opening liabilities', function () {
    $user = User::factory()->create();
    // Opening: assets 1500 = liabilities 500 + equity 1000.
    $fp = fiscalPeriodForTrialBalance($user, assets: 1500, liabilities: 500, equity: 1000);

    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
        'description' => 'Rent', 'amount' => 500, 'transaction_date' => '2026-03-15',
    ]);

    $tb = trialBalanceFor($fp);

    expect($tb['is_balanced'])->toBeTrue()
        // debit: assets 1500 + 500 = 2000
        ->and($tb['total_debits'])->toBe(2000.0)
        // credit: liabilities 500 + equity (1000 + 500) = 2000
        ->and($tb['total_credits'])->toBe(2000.0);
});
