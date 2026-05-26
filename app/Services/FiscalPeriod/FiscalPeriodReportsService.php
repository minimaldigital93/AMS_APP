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
    public function incomeStatement(FiscalPeriods $fiscalPeriod, iterable $monthlyPeriods): array
    {
        $months = [];
        $totals = [
            'rent_income'    => 0,
            'late_fees'      => 0,
            'other_income'   => 0,
            'total_income'   => 0,
            'total_expenses' => 0,
            'net_income'     => 0,
        ];

        foreach ($monthlyPeriods as $month) {
            $data = $this->financials->forMonth($fiscalPeriod, $month);

            $months[] = [
                'name'  => $month->name,
                'short' => Carbon::parse($month->start_date)->format('M'),
                'data'  => $data,
            ];

            $totals['rent_income']    += $data['rent_income'];
            $totals['late_fees']      += $data['late_fees'];
            $totals['other_income']   += $data['other_income'];
            $totals['total_income']   += $data['total_income'];
            $totals['total_expenses'] += $data['total_expenses'];
            $totals['net_income']     += $data['net_income'];
        }

        return ['months' => $months, 'totals' => $totals];
    }

    /**
     * Cash Flow Statement — running opening/closing balance per month.
     *
     * @param  \Illuminate\Support\Collection|iterable  $monthlyPeriods
     */
    public function cashFlow(FiscalPeriods $fiscalPeriod, iterable $monthlyPeriods): array
    {
        $months = [];
        $runningBalance = (float) $fiscalPeriod->opening_balance;

        foreach ($monthlyPeriods as $month) {
            $data = $this->financials->forMonth($fiscalPeriod, $month);
            $openBal  = $runningBalance;
            $closeBal = $openBal + $data['net_income'];

            $months[] = [
                'name'            => $month->name,
                'short'           => Carbon::parse($month->start_date)->format('M'),
                'opening_balance' => $openBal,
                'cash_in'         => $data['total_income'],
                'cash_out'        => $data['total_expenses'],
                'net_cash_flow'   => $data['net_income'],
                'closing_balance' => $closeBal,
            ];

            $runningBalance = $closeBal;
        }

        return [
            'months'          => $months,
            'opening_balance' => $fiscalPeriod->opening_balance,
            'closing_balance' => $runningBalance,
            'total_cash_in'   => array_sum(array_column($months, 'cash_in')),
            'total_cash_out'  => array_sum(array_column($months, 'cash_out')),
            'net_change'      => $runningBalance - $fiscalPeriod->opening_balance,
        ];
    }

    /**
     * Trial Balance — debits (assets + expenses + cash) on the left,
     * credits (liabilities + equity + revenue) on the right. Reports
     * is_balanced when |debit − credit| < 0.01.
     */
    public function trialBalance(FiscalPeriods $fiscalPeriod): array
    {
        $periodFinancials = $this->financials->forPeriod($fiscalPeriod);
        $summary          = $this->balanceSheet->summary($fiscalPeriod);

        $debits  = [];
        $totalDebits = 0;

        if ($summary['total_assets'] > 0) {
            $debits[] = ['account' => 'Assets', 'amount' => $summary['total_assets']];
            $totalDebits += $summary['total_assets'];
        }
        if ($periodFinancials['total_expenses'] > 0) {
            $debits[] = ['account' => 'Total Expenses', 'amount' => $periodFinancials['total_expenses']];
            $totalDebits += $periodFinancials['total_expenses'];
        }
        if ($fiscalPeriod->opening_balance > 0) {
            $debits[] = ['account' => 'Cash (Opening Balance)', 'amount' => $fiscalPeriod->opening_balance];
            $totalDebits += $fiscalPeriod->opening_balance;
        }

        $credits = [];
        $totalCredits = 0;

        if ($summary['total_liabilities'] > 0) {
            $credits[] = ['account' => 'Liabilities', 'amount' => $summary['total_liabilities']];
            $totalCredits += $summary['total_liabilities'];
        }
        if ($summary['total_equity'] > 0) {
            $credits[] = ['account' => 'Equity', 'amount' => $summary['total_equity']];
            $totalCredits += $summary['total_equity'];
        }
        if ($periodFinancials['total_income'] > 0) {
            $credits[] = ['account' => 'Total Revenue', 'amount' => $periodFinancials['total_income']];
            $totalCredits += $periodFinancials['total_income'];
        }

        return [
            'debits'        => $debits,
            'credits'       => $credits,
            'total_debits'  => $totalDebits,
            'total_credits' => $totalCredits,
            'is_balanced'   => abs($totalDebits - $totalCredits) < 0.01,
            'difference'    => $totalDebits - $totalCredits,
        ];
    }
}
