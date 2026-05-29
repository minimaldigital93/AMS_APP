<?php

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\MonthlyPeriod;
use App\Models\User;
use App\Services\FiscalPeriod\MonthlyPeriodManager;

/**
 * Accounting invariant under test:
 *   An owner profit withdrawal is a DRAW, not an expense.
 *   - It must NOT change net income (income statement stays correct).
 *   - It MUST reduce the carried-forward closing balance.
 *       closing_balance = opening_balance + net_income − owner_withdrawal
 */
function makeFiscalPeriodWithTwoMonths(User $user): array
{
    $fp = FiscalPeriods::create([
        'user_id' => $user->id,
        'name' => 'FY Test',
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-02-28',
        'opening_balance' => 1000,
        'closing_balance' => 0,
        'status' => 'open',
    ]);

    $jan = MonthlyPeriod::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'name' => 'January 2026', 'month_number' => 1, 'year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        'opening_balance' => 1000, 'closing_balance' => 0,
        'total_income' => 0, 'total_expenses' => 0, 'net_income' => 0,
        'status' => 'open',
    ]);

    $feb = MonthlyPeriod::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'name' => 'February 2026', 'month_number' => 2, 'year' => 2026,
        'start_date' => '2026-02-01', 'end_date' => '2026-02-28',
        'opening_balance' => 0, 'closing_balance' => 0,
        'total_income' => 0, 'total_expenses' => 0, 'net_income' => 0,
        'status' => 'open',
    ]);

    // January ledger: 500 rent income, 200 expense → net 300.
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
        'description' => 'Rent', 'amount' => 500, 'transaction_date' => '2026-01-15',
    ]);
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_EXPENSE, 'category' => Accounts::CAT_UTILITIES_EXPENSE,
        'description' => 'Water', 'amount' => 200, 'transaction_date' => '2026-01-20',
    ]);

    return [$fp, $jan, $feb];
}

it('treats owner withdrawal as a draw, not an expense', function () {
    $user = User::factory()->create();
    [$fp, $jan, $feb] = makeFiscalPeriodWithTwoMonths($user);

    $manager = app(MonthlyPeriodManager::class);
    $result = $manager->closeMonth($fp, $jan, 100, 'Owner distribution');

    $jan->refresh();

    // Net income is operating profit only — withdrawal does NOT touch it.
    expect($jan->net_income)->toBe(300.0)
        ->and($jan->total_income)->toBe(500.0)
        ->and($jan->total_expenses)->toBe(200.0);

    // Closing = opening(1000) + net(300) − withdrawal(100) = 1200.
    expect($jan->closing_balance)->toBe(1200.0)
        ->and($jan->owner_withdrawal)->toBe(100.0)
        ->and($jan->withdrawal_note)->toBe('Owner distribution')
        ->and($jan->status)->toBe('closed');

    // Reduced cash carries forward to February's opening balance.
    expect($result['closing_balance'])->toBe(1200.0);
    $feb->refresh();
    expect($feb->opening_balance)->toBe(1200.0);
});

it('does not create any expense ledger entry for the withdrawal', function () {
    $user = User::factory()->create();
    [$fp, $jan] = makeFiscalPeriodWithTwoMonths($user);

    $expensesBefore = $fp->accounts()->where('account_type', Accounts::TYPE_EXPENSE)->sum('amount');

    app(MonthlyPeriodManager::class)->closeMonth($fp, $jan, 250, 'Draw');

    $expensesAfter = $fp->accounts()->where('account_type', Accounts::TYPE_EXPENSE)->sum('amount');
    expect($expensesAfter)->toBe($expensesBefore); // unchanged — no expense row added
});

it('clears the withdrawal when a month is reopened', function () {
    $user = User::factory()->create();
    [$fp, $jan] = makeFiscalPeriodWithTwoMonths($user);

    $manager = app(MonthlyPeriodManager::class);
    $manager->closeMonth($fp, $jan, 100, 'Draw');
    $manager->reopenMonth($fp, $jan->refresh());

    $jan->refresh();
    expect($jan->status)->toBe('open')
        ->and($jan->owner_withdrawal)->toBe(0.0)
        ->and($jan->withdrawal_note)->toBeNull();
});

it('subtracts stored withdrawals during a full recalculation', function () {
    $user = User::factory()->create();
    [$fp, $jan, $feb] = makeFiscalPeriodWithTwoMonths($user);

    $jan->update(['owner_withdrawal' => 100, 'status' => 'closed']);

    $carryForward = app(MonthlyPeriodManager::class)->recalculateBalances($fp);

    $jan->refresh();
    // Jan: 1000 + 300 − 100 = 1200 carried to Feb (no Feb activity) → final 1200.
    expect($jan->closing_balance)->toBe(1200.0)
        ->and($carryForward)->toBe(1200.0);
});
