<?php

namespace App\Services\FiscalPeriod;

use App\Models\FiscalPeriods;
use Carbon\Carbon;

/**
 * Composite "reports" output for the period reports page: income statement,
 * cash flow statement, and trial balance. All three pull from the financials
 * + balance sheet services so numbers stay consistent across the page.
 */
class FiscalPeriodReportsService
{
    public function __construct(
        private FiscalPeriodFinancialsService $financials,
        private BalanceSheetService $balanceSheet,
    ) {}

    /**
     * Monthly + period totals for the Income Statement table.
     *
     * @param  \Illuminate\Support\Collection|iterable  $monthlyPeriods
     */
    public function incomeStatement(FiscalPeriods $fiscalPeriod, iterable $monthlyPeriods, ?int $propertyId = null): array
    {
        $months = [];
        $totals = [
            'rent_income' => 0,
            'late_fees' => 0,
            'other_income' => 0,
            'total_income' => 0,
            'total_expenses' => 0,
            'net_income' => 0,
        ];

        foreach ($monthlyPeriods as $month) {
            $data = $this->financials->forMonth($fiscalPeriod, $month, $propertyId);

            $months[] = [
                'name' => $month->name,
                'short' => Carbon::parse($month->start_date)->format('M'),
                'data' => $data,
            ];

            $totals['rent_income'] += $data['rent_income'];
            $totals['late_fees'] += $data['late_fees'];
            $totals['other_income'] += $data['other_income'];
            $totals['total_income'] += $data['total_income'];
            $totals['total_expenses'] += $data['total_expenses'];
            $totals['net_income'] += $data['net_income'];
        }

        return ['months' => $months, 'totals' => $totals];
    }

    /**
     * Cash Flow Statement — running opening/closing balance per month.
     *
     * @param  \Illuminate\Support\Collection|iterable  $monthlyPeriods
     */
    public function cashFlow(FiscalPeriods $fiscalPeriod, iterable $monthlyPeriods, ?int $propertyId = null): array
    {
        // Opening balance and owner draws are entered at the account level, not
        // per property — so a single-property cash flow shows only the operating
        // cash it generated, starting from zero with no draws.
        $isConsolidated = $propertyId === null;

        $months = [];
        $runningBalance = $isConsolidated ? (float) $fiscalPeriod->opening_balance : 0.0;

        foreach ($monthlyPeriods as $month) {
            $data = $this->financials->forMonth($fiscalPeriod, $month, $propertyId);

            // An owner draw is not an expense (net income is untouched), but it
            // is cash leaving the business, so the cash flow must subtract it
            // from the running balance carried into the next month.
            $withdrawal = $isConsolidated ? (float) $month->owner_withdrawal : 0.0;

            $openBal = $runningBalance;
            $closeBal = $openBal + $data['net_income'] - $withdrawal;

            $months[] = [
                'name' => $month->name,
                'short' => Carbon::parse($month->start_date)->format('M'),
                'opening_balance' => $openBal,
                'cash_in' => $data['total_income'],
                'cash_out' => $data['total_expenses'],
                'net_cash_flow' => $data['net_income'],
                'owner_withdrawal' => $withdrawal,
                'closing_balance' => $closeBal,
            ];

            $runningBalance = $closeBal;
        }

        $openingBalance = $isConsolidated ? (float) $fiscalPeriod->opening_balance : 0.0;

        return [
            'months' => $months,
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'total_cash_in' => array_sum(array_column($months, 'cash_in')),
            'total_cash_out' => array_sum(array_column($months, 'cash_out')),
            'total_withdrawals' => array_sum(array_column($months, 'owner_withdrawal')),
            'net_change' => $runningBalance - $openingBalance,
            'is_consolidated' => $isConsolidated,
        ];
    }

    /**
     * Post-closing Trial Balance — assets on the debit side, liabilities and
     * equity on the credit side. Reports is_balanced when |debit − credit| < 0.01.
     *
     * The figures come straight from the auto-calculated balance sheet, which
     * has already folded retained earnings (net income) and owner draws into
     * both current assets and current equity. Because the opening figures must
     * balance (Assets = Liabilities + Equity) and operations move assets and
     * equity by the same amount, the trial balance self-balances by construction.
     */
    public function trialBalance(FiscalPeriods $fiscalPeriod): array
    {
        $summary = $this->balanceSheet->summary($fiscalPeriod);
        $nonZero = fn ($amount) => abs((float) $amount) > 0.005;

        $debits = [];
        $totalDebits = 0;

        if ($nonZero($summary['total_assets'])) {
            $debits[] = ['account' => 'Assets (incl. retained earnings)', 'amount' => $summary['total_assets']];
            $totalDebits += $summary['total_assets'];
        }

        $credits = [];
        $totalCredits = 0;

        if ($nonZero($summary['total_liabilities'])) {
            $credits[] = ['account' => 'Liabilities', 'amount' => $summary['total_liabilities']];
            $totalCredits += $summary['total_liabilities'];
        }
        // Equity already includes opening equity + retained earnings − draws.
        if ($nonZero($summary['total_equity'])) {
            $credits[] = ['account' => 'Equity (incl. retained earnings)', 'amount' => $summary['total_equity']];
            $totalCredits += $summary['total_equity'];
        }

        return [
            'debits' => $debits,
            'credits' => $credits,
            'total_debits' => round($totalDebits, 2),
            'total_credits' => round($totalCredits, 2),
            'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
            'difference' => round($totalDebits - $totalCredits, 2),
            'retained_earnings' => $summary['retained_earnings'],
        ];
    }
}
