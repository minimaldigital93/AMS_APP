<?php

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\MonthlyPeriod;
use App\Models\User;
use App\Services\FiscalPeriod\BalanceSheetService;

/**
 * Accounting invariant under test:
 *   The balance sheet is calculated automatically from the opening figures
 *   entered at period creation, rolled forward by operations:
 *
 *     current_assets = opening_assets + retained_earnings − owner_withdrawals
 *     current_equity = opening_equity + retained_earnings − owner_withdrawals
 *     current_liabilities = opening_liabilities (unchanged)
 *
 *   It stays balanced (A = L + E) because the opening figures balanced and
 *   assets/equity move by the same amount.
 */
function rollForwardPeriod(User $user): FiscalPeriods
{
    // Opening: assets 5000 = liabilities 2000 + equity 3000.
    $fp = FiscalPeriods::create([
        'user_id' => $user->id,
        'name' => 'FY Roll Forward',
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-02-28',
        'opening_balance' => 5000,
        'opening_assets' => 5000,
        'opening_liabilities' => 2000,
        'opening_equity' => 3000,
        'closing_balance' => 0,
        'status' => 'open',
    ]);

    MonthlyPeriod::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'name' => 'January 2026', 'month_number' => 1, 'year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        'opening_balance' => 5000, 'closing_balance' => 0,
        'total_income' => 0, 'total_expenses' => 0, 'net_income' => 0,
        'status' => 'open',
    ]);
    MonthlyPeriod::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'name' => 'February 2026', 'month_number' => 2, 'year' => 2026,
        'start_date' => '2026-02-01', 'end_date' => '2026-02-28',
        'opening_balance' => 0, 'closing_balance' => 0,
        'total_income' => 0, 'total_expenses' => 0, 'net_income' => 0,
        'status' => 'open',
    ]);

    // January: +1000 rent, −400 expense → net 600.
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
        'description' => 'Rent', 'amount' => 1000, 'transaction_date' => '2026-01-15',
    ]);
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_EXPENSE, 'category' => Accounts::CAT_UTILITIES_EXPENSE,
        'description' => 'Water', 'amount' => 400, 'transaction_date' => '2026-01-20',
    ]);
    // February: +800 rent.
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
        'description' => 'Rent', 'amount' => 800, 'transaction_date' => '2026-02-10',
    ]);

    return $fp;
}

it('auto-calculates the period balance sheet from opening figures plus operations', function () {
    $user = User::factory()->create();
    $fp = rollForwardPeriod($user);

    $summary = app(BalanceSheetService::class)->summary($fp);

    // Retained earnings = (1000 + 800) − 400 = 1400.
    expect($summary['retained_earnings'])->toBe(1400.0)
        // Assets = 5000 + 1400 = 6400 ; equity = 3000 + 1400 = 4400 ; liabilities = 2000.
        ->and($summary['total_assets'])->toBe(6400.0)
        ->and($summary['total_liabilities'])->toBe(2000.0)
        ->and($summary['total_equity'])->toBe(4400.0)
        ->and($summary['balance_check'])->toBeTrue();
});

it('rolls the balance sheet forward as of a given month', function () {
    $user = User::factory()->create();
    $fp = rollForwardPeriod($user);
    $jan = $fp->monthlyPeriods()->where('month_number', 1)->first();

    $asOfJan = app(BalanceSheetService::class)->summaryAsOf($fp, $jan);

    // As of January only: retained earnings = 1000 − 400 = 600.
    expect($asOfJan['retained_earnings'])->toBe(600.0)
        ->and($asOfJan['total_assets'])->toBe(5600.0)
        ->and($asOfJan['total_equity'])->toBe(3600.0)
        ->and($asOfJan['total_liabilities'])->toBe(2000.0)
        ->and($asOfJan['balance_check'])->toBeTrue();
});

it('subtracts owner withdrawals from assets and equity but stays balanced', function () {
    $user = User::factory()->create();
    $fp = rollForwardPeriod($user);

    // Record a draw on January (as closeMonth would).
    $fp->monthlyPeriods()->where('month_number', 1)->update(['owner_withdrawal' => 500]);

    $summary = app(BalanceSheetService::class)->summary($fp);

    // Assets = 5000 + 1400 − 500 = 5900 ; equity = 3000 + 1400 − 500 = 3900.
    expect($summary['owner_withdrawals'])->toBe(500.0)
        ->and($summary['total_assets'])->toBe(5900.0)
        ->and($summary['total_equity'])->toBe(3900.0)
        ->and($summary['total_liabilities'])->toBe(2000.0)
        ->and($summary['balance_check'])->toBeTrue();
});
