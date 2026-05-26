<?php

namespace App\Services\FiscalPeriod;

use App\Models\Accounts;
use App\Models\FiscalPeriods;

/**
 * Balance sheet roll-up and (legacy) HTML rendering for export.
 *
 * The summary combines manual balance sheet items (assets, liabilities, equity
 * entered by the user via the balance-sheet form) with retained earnings
 * computed from the live Accounts ledger, so the "Total equity" shown to the
 * user reflects current operations, not just the snapshot items.
 */
class BalanceSheetService
{
    /**
     * Full balance sheet summary array used by the dashboard, balance-sheet
     * page, reports page, exports, and the trial balance.
     */
    public function summary(FiscalPeriods $fiscalPeriod): array
    {
        $assets      = $fiscalPeriod->balanceSheets()->where('item_type', 'asset')->sum('amount');
        $liabilities = $fiscalPeriod->balanceSheets()->where('item_type', 'liability')->sum('amount');
        $equity      = $fiscalPeriod->balanceSheets()->where('item_type', 'equity')->sum('amount');

        $totalIncome = $fiscalPeriod->accounts()
            ->where('account_type', Accounts::TYPE_INCOME)
            ->sum('amount');
        $totalExpenses = $fiscalPeriod->accounts()
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->sum('amount');

        $retainedEarnings = $totalIncome - $totalExpenses;

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

        // Adjusted equity = manual equity + retained earnings from operations.
        $adjustedEquity     = $equity + $retainedEarnings;
        $cashFromOperations = $fiscalPeriod->opening_balance + $retainedEarnings;

        return [
            'total_assets'         => $assets,
            'total_liabilities'    => $liabilities,
            'total_equity'         => $equity,
            'retained_earnings'    => round($retainedEarnings, 2),
            'adjusted_equity'      => round($adjustedEquity, 2),
            'net_worth'            => $assets - $liabilities,
            'balance_check'        => abs(($liabilities + $adjustedEquity) - $assets) < 0.01,
            'total_income'         => round($totalIncome, 2),
            'total_expenses'       => round($totalExpenses, 2),
            'net_income'           => round($retainedEarnings, 2),
            'income_by_category'   => $incomeByCategory,
            'expense_by_category'  => $expenseByCategory,
            'cash_from_operations' => round($cashFromOperations, 2),
            'opening_balance'      => round($fiscalPeriod->opening_balance, 2),
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
        $html = '<h1>' . e($fiscalPeriod->name) . '</h1>';
        $html .= '<p>Period: ' . e($fiscalPeriod->opening_date) . ' to ' . e($fiscalPeriod->closing_date) . '</p>';
        $html .= '<table border="1" cellpadding="5" width="100%">';
        $html .= '<tr><th>Item Type</th><th>Name</th><th>Amount</th><th>As Of Date</th></tr>';

        foreach ($balanceSheetItems as $item) {
            $html .= '<tr>';
            $html .= '<td>' . ucfirst(e($item->item_type)) . '</td>';
            $html .= '<td>' . e($item->name) . '</td>';
            $html .= '<td>' . number_format((float) $item->amount, 2) . '</td>';
            $html .= '<td>' . e($item->as_of_date) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        return $html;
    }
}
