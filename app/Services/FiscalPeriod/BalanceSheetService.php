<?php

namespace App\Services\FiscalPeriod;

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\MonthlyPeriod;
use Carbon\Carbon;

/**
 * Auto-calculated balance sheet for a fiscal period.
 *
 * The owner enters the opening Assets / Liabilities / Equity once when the
 * period is opened (they must balance: A = L + E). From there the balance
 * sheet is rolled forward automatically from the live Accounts ledger using
 * standard accounting:
 *
 *     current_assets      = opening_assets      + retained_earnings − owner_withdrawals
 *     current_liabilities = opening_liabilities (unchanged by operations)
 *     current_equity      = opening_equity      + retained_earnings − owner_withdrawals
 *
 * where retained_earnings = total_income − total_expenses (from the ledger) and
 * owner_withdrawals are the draws recorded when closing months. Because both
 * assets and equity move by the same amount, the sheet stays balanced for as
 * long as the opening figures balanced. No manual line-item entry is required.
 */
class BalanceSheetService
{
    /**
     * Full balance sheet summary for the whole fiscal period (all activity to
     * date). Used by the dashboard, balance-sheet page, reports, exports, and
     * the trial balance.
     */
    public function summary(FiscalPeriods $fiscalPeriod): array
    {
        $totalIncome = (float) $fiscalPeriod->accounts()
            ->where('account_type', Accounts::TYPE_INCOME)
            ->sum('amount');
        $totalExpenses = (float) $fiscalPeriod->accounts()
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->sum('amount');

        $withdrawals = (float) $fiscalPeriod->monthlyPeriods()->sum('owner_withdrawal');

        $incomeByCategory = $fiscalPeriod->accounts()
            ->where('account_type', Accounts::TYPE_INCOME)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        $expenseByCategory = $fiscalPeriod->accounts()
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        return $this->rollForward($fiscalPeriod, $totalIncome, $totalExpenses, $withdrawals) + [
            'income_by_category' => $incomeByCategory,
            'expense_by_category' => $expenseByCategory,
        ];
    }

    /**
     * Balance sheet as it stands at the end of a given month — opening figures
     * plus cumulative operations and owner draws from the start of the period
     * through that month's end. Used by the monthly period view and PDF.
     */
    public function summaryAsOf(FiscalPeriods $fiscalPeriod, MonthlyPeriod $month): array
    {
        $start = Carbon::parse($fiscalPeriod->opening_date)->startOfDay();
        $end = Carbon::parse($month->end_date)->endOfDay();

        $totalIncome = (float) $fiscalPeriod->accounts()
            ->where('account_type', Accounts::TYPE_INCOME)
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('amount');
        $totalExpenses = (float) $fiscalPeriod->accounts()
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('amount');

        // Draws are only recorded on closed months, so sum every month up to and
        // including this one.
        $withdrawals = (float) $fiscalPeriod->monthlyPeriods()
            ->where('end_date', '<=', $month->end_date)
            ->sum('owner_withdrawal');

        return $this->rollForward($fiscalPeriod, $totalIncome, $totalExpenses, $withdrawals);
    }

    /**
     * Apply the standard roll-forward to the period's opening figures and return
     * the stable summary shape consumed by views, reports and exports.
     */
    private function rollForward(FiscalPeriods $fiscalPeriod, float $totalIncome, float $totalExpenses, float $withdrawals): array
    {
        $openingAssets = (float) $fiscalPeriod->opening_assets;
        $openingLiabilities = (float) $fiscalPeriod->opening_liabilities;
        $openingEquity = (float) $fiscalPeriod->opening_equity;

        $retainedEarnings = $totalIncome - $totalExpenses;

        $currentAssets = $openingAssets + $retainedEarnings - $withdrawals;
        $currentLiabilities = $openingLiabilities;
        $currentEquity = $openingEquity + $retainedEarnings - $withdrawals;

        return [
            'opening_assets' => round($openingAssets, 2),
            'opening_liabilities' => round($openingLiabilities, 2),
            'opening_equity' => round($openingEquity, 2),
            'total_assets' => round($currentAssets, 2),
            'total_liabilities' => round($currentLiabilities, 2),
            'total_equity' => round($currentEquity, 2),
            'retained_earnings' => round($retainedEarnings, 2),
            'owner_withdrawals' => round($withdrawals, 2),
            // adjusted_equity is kept as an alias of total_equity for back-compat
            // with the trial balance and existing views.
            'adjusted_equity' => round($currentEquity, 2),
            'net_worth' => round($currentAssets - $currentLiabilities, 2),
            'balance_check' => abs($currentAssets - ($currentLiabilities + $currentEquity)) < 0.01,
            'total_income' => round($totalIncome, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_income' => round($retainedEarnings, 2),
            'cash_from_operations' => round($currentAssets, 2),
            'opening_balance' => round($fiscalPeriod->opening_balance, 2),
        ];
    }

    /**
     * Render a simple HTML table for the balance sheet. Currently used by the
     * "Export PDF" flow as a fallback before a real PDF library is wired in.
     *
     * @param  \Illuminate\Support\Collection|array  $balanceSheetItems
     */
    public function renderHtml(FiscalPeriods $fiscalPeriod, $balanceSheetItems): string
    {
        $html = '<h1>'.e($fiscalPeriod->name).'</h1>';
        $html .= '<p>Period: '.e($fiscalPeriod->opening_date).' to '.e($fiscalPeriod->closing_date).'</p>';
        $html .= '<table border="1" cellpadding="5" width="100%">';
        $html .= '<tr><th>Item Type</th><th>Name</th><th>Amount</th><th>As Of Date</th></tr>';

        foreach ($balanceSheetItems as $item) {
            $html .= '<tr>';
            $html .= '<td>'.ucfirst(e($item->item_type)).'</td>';
            $html .= '<td>'.e($item->name).'</td>';
            $html .= '<td>'.number_format((float) $item->amount, 2).'</td>';
            $html .= '<td>'.e($item->as_of_date).'</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
