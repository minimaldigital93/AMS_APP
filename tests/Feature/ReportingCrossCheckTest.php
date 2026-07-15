<?php

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Services\FiscalPeriod\BalanceSheetService;
use App\Services\FiscalPeriod\FiscalPeriodFinancialsService;
use App\Services\FiscalPeriod\FiscalPeriodReportsService;
use App\Services\FiscalPeriod\MonthlyPeriodManager;

/**
 * Phase 11 regression net: cross-checks every report surface against the
 * raw ledger and against each other on one seeded dataset — period totals vs raw ledger, monthly tiling, income statement, cash flow vs close-period math, balance-sheet roll-forward, trial balance.
 */
function reportingFixture(): array
{
    $admin = makeAdmin();
    auth()->login($admin);

    $period = FiscalPeriods::create([
        'user_id' => $admin->id,
        'name' => 'FY probe',
        'opening_date' => '2026-01-15',   // deliberately mid-month
        'closing_date' => '2026-12-31',
        'opening_balance' => 1000,
        'opening_assets' => 5000,
        'opening_liabilities' => 2000,
        'opening_equity' => 3000,
        'status' => 'open',
    ]);

    app(MonthlyPeriodManager::class)->generateForFiscalPeriod($period);

    $mk = fn (string $type, string $cat, float $amt, string $date) => Accounts::create([
        'fiscal_period_id' => $period->id,
        'user_id' => $admin->id,
        'account_type' => $type,
        'category' => $cat,
        'description' => "$cat $date",
        'amount' => $amt,
        'transaction_date' => $date,
    ]);

    // Ledger spread across the period, including edge dates.
    $mk(Accounts::TYPE_INCOME, Accounts::CAT_RENT_INCOME, 500, '2026-01-15');   // first day
    $mk(Accounts::TYPE_INCOME, Accounts::CAT_RENT_INCOME, 500, '2026-02-01');
    $mk(Accounts::TYPE_INCOME, Accounts::CAT_LATE_FEE_INCOME, 25, '2026-02-14');
    $mk(Accounts::TYPE_INCOME, Accounts::CAT_UTILITY_INCOME, 60, '2026-03-31');
    $mk(Accounts::TYPE_INCOME, Accounts::CAT_DEPOSIT_INCOME, 300, '2026-06-30');
    $mk(Accounts::TYPE_INCOME, Accounts::CAT_OTHER_INCOME, 40, '2026-12-31');   // last day
    $mk(Accounts::TYPE_EXPENSE, Accounts::CAT_UTILITIES_EXPENSE, 80, '2026-02-20');
    $mk(Accounts::TYPE_EXPENSE, Accounts::CAT_BUSINESS_FIXED, 120, '2026-05-10');
    $mk(Accounts::TYPE_EXPENSE, Accounts::CAT_BUSINESS_VARIABLE, 45, '2026-12-31');

    // An owner draw on one month.
    $period->monthlyPeriods()->where('month_number', 3)->first()
        ?->update(['owner_withdrawal' => 150]);

    auth()->logout();

    return compact('admin', 'period');
}

it('cross-checks all report surfaces against the ledger', function () {
    $f = reportingFixture();
    $period = $f['period'];
    auth()->login($f['admin']);

    $months = $period->monthlyPeriods()->orderBy('start_date')->get();
    $financials = app(FiscalPeriodFinancialsService::class);
    $reports = app(FiscalPeriodReportsService::class);
    $balance = app(BalanceSheetService::class);

    // Raw ledger truth
    $ledgerIncome = 500 + 500 + 25 + 60 + 300 + 40;   // 1425
    $ledgerExpense = 80 + 120 + 45;                   // 245
    $ledgerNet = $ledgerIncome - $ledgerExpense;      // 1180

    // 1. Period totals == raw ledger
    $forPeriod = $financials->forPeriod($period);
    expect($forPeriod['total_income'])->toBe((float) $ledgerIncome)
        ->and($forPeriod['total_expenses'])->toBe((float) $ledgerExpense)
        ->and($forPeriod['net_income'])->toBe((float) $ledgerNet);

    // 2. Months tile the period: sum of forMonth == forPeriod (no row falls in a gap)
    $sumIncome = $sumExpense = 0.0;
    foreach ($months as $m) {
        $d = $financials->forMonth($period, $m);
        $sumIncome += $d['total_income'];
        $sumExpense += $d['total_expenses'];
    }
    expect(round($sumIncome, 2))->toBe((float) $ledgerIncome, 'monthly tiling lost income rows')
        ->and(round($sumExpense, 2))->toBe((float) $ledgerExpense, 'monthly tiling lost expense rows');

    // 3. Income statement totals == period totals
    $stmt = $reports->incomeStatement($period, $months);
    expect(round($stmt['totals']['total_income'], 2))->toBe((float) $ledgerIncome)
        ->and(round($stmt['totals']['net_income'], 2))->toBe((float) $ledgerNet);

    // 4. Cash flow: closing == opening + net − draws; internal consistency
    $cash = $reports->cashFlow($period, $months);
    expect(round($cash['closing_balance'], 2))->toBe(round(1000 + $ledgerNet - 150, 2))
        ->and(round($cash['net_change'], 2))->toBe(round($ledgerNet - 150, 2))
        ->and(round($cash['total_cash_in'], 2))->toBe((float) $ledgerIncome)
        ->and(round($cash['total_withdrawals'], 2))->toBe(150.0);

    // 5. Balance sheet: rolls forward, balances, agrees with ledger
    $sheet = $balance->summary($period);
    expect($sheet['balance_check'])->toBeTrue()
        ->and($sheet['retained_earnings'])->toBe((float) $ledgerNet)
        ->and($sheet['total_assets'])->toBe(round(5000 + $ledgerNet - 150, 2))
        ->and($sheet['total_equity'])->toBe(round(3000 + $ledgerNet - 150, 2))
        ->and($sheet['total_liabilities'])->toBe(2000.0);

    // 6. Trial balance self-balances
    $trial = $reports->trialBalance($period);
    expect($trial['is_balanced'])->toBeTrue();

    // 7. As-of March == cumulative ledger through March + draws through March
    $march = $months->firstWhere('month_number', 3);
    $asOf = $balance->summaryAsOf($period, $march);
    $incomeThruMar = 500 + 500 + 25 + 60;
    $expenseThruMar = 80;
    expect($asOf['retained_earnings'])->toBe(round($incomeThruMar - $expenseThruMar, 2))
        ->and($asOf['owner_withdrawals'])->toBe(150.0)
        ->and($asOf['balance_check'])->toBeTrue();

    // 8. Period close math (recalculateBalances) agrees with cash flow
    $recalced = app(MonthlyPeriodManager::class)->recalculateBalances($period);
    expect(round($recalced, 2))->toBe(round($cash['closing_balance'], 2), 'closeperiod math disagrees with cash flow report');
});
