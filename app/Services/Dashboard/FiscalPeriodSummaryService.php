<?php

namespace App\Services\Dashboard;

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "Active fiscal period" summary card shown at the top of the dashboard:
 *   revenue, expenses, net profit, balance sheet roll-up, and per-month
 *   income/expense series for the embedded chart.
 *
 * Returns the same shape regardless of whether a period is open — when none
 * exists, every numeric field is 0 and 'has_active_period' is false.
 */
class FiscalPeriodSummaryService
{
    public function __construct(
        private int $userId,
        private ?array $apartmentIds = null,
    ) {}

    public function build(?FiscalPeriods $activePeriod): array
    {
        if (! $activePeriod) {
            return $this->emptyShape();
        }

        $incomeRecords = $this->scopedAccountsQuery($activePeriod)
            ->where('account_type', Accounts::TYPE_INCOME)->get();
        $expenseRecords = $this->scopedAccountsQuery($activePeriod)
            ->where('account_type', Accounts::TYPE_EXPENSE)->get();

        $revenue = $incomeRecords->where('category', Accounts::CAT_RENT_INCOME)->sum('amount');
        $lateFees = $incomeRecords->where('category', Accounts::CAT_LATE_FEE_INCOME)->sum('amount');
        $totalIncome = $incomeRecords->sum('amount');

        $expenses = $expenseRecords->groupBy('category')
            ->map(fn ($items) => round($items->sum('amount'), 2))
            ->toArray();
        $totalExpenses = $expenseRecords->sum('amount');

        $netProfit = $totalIncome - $totalExpenses;
        $profitMargin = $totalIncome > 0 ? round(($netProfit / $totalIncome) * 100, 2) : 0;

        // Supervisor reads the admin's recent periods (period->user_id is the
        // admin's id either way, since supervisor periods *are* admin periods).
        $recentPeriods = FiscalPeriods::where('user_id', $activePeriod->user_id)
            ->where('status', 'closed')
            ->orderBy('closing_date', 'desc')
            ->take(5)
            ->get();

        $totalAssets = $activePeriod->balanceSheets()->where('item_type', 'asset')->sum('amount');
        $totalLiabilities = $activePeriod->balanceSheets()->where('item_type', 'liability')->sum('amount');
        $totalEquity = $activePeriod->balanceSheets()->where('item_type', 'equity')->sum('amount');

        return [
            'has_active_period' => true,
            'period' => $activePeriod,
            'revenue' => $revenue,
            'late_fees' => $lateFees,
            'total_income' => $totalIncome,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'is_profitable' => $netProfit > 0,
            'profit_margin' => $profitMargin,
            'opening_balance' => $activePeriod->opening_balance,
            'current_balance' => $activePeriod->opening_balance + $netProfit,
            'balance_sheet' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
            ],
            'recent_periods' => $recentPeriods,
            'monthly_revenue' => $this->monthlyTotals($activePeriod, Accounts::TYPE_INCOME),
            'monthly_expenses' => $this->monthlyTotals($activePeriod, Accounts::TYPE_EXPENSE),
        ];
    }

    /**
     * GROUP BY (year, month) aggregation for the embedded chart series.
     */
    private function monthlyTotals(FiscalPeriods $period, string $type): array
    {
        $rows = $this->scopedAccountsQuery($period)
            ->where('account_type', $type)
            ->selectRaw('YEAR(transaction_date) as year, MONTH(transaction_date) as month, SUM(amount) as total')
            ->groupByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->orderByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $label = date('M Y', mktime(0, 0, 0, $row->month, 1, $row->year));
            $result[$label] = round($row->total, 2);
        }

        return $result;
    }

    /**
     * Role-aware base query for the active period's Accounts:
     *   - admin: where user_id = $this->userId
     *   - supervisor: where (payment.rental.apartment_id in $apartmentIds) OR
     *                       (expense with no payment_id — manual business expense)
     */
    private function scopedAccountsQuery(FiscalPeriods $period): Builder
    {
        $query = Accounts::where('fiscal_period_id', $period->id);

        if ($this->apartmentIds === null) {
            $query->where('user_id', $this->userId);

            return $query;
        }

        $apartmentIds = $this->apartmentIds;
        $query->where(function ($q) use ($apartmentIds) {
            $q->whereHas('payment', function ($pq) use ($apartmentIds) {
                $pq->whereHas('rental', fn ($rq) => $rq->whereIn('apartment_id', $apartmentIds));
            })->orWhere(function ($q2) {
                $q2->where('account_type', Accounts::TYPE_EXPENSE)->whereNull('payment_id');
            });
        });

        return $query;
    }

    private function emptyShape(): array
    {
        return [
            'has_active_period' => false,
            'period' => null,
            'revenue' => 0,
            'late_fees' => 0,
            'total_income' => 0,
            'expenses' => [],
            'total_expenses' => 0,
            'net_profit' => 0,
            'is_profitable' => false,
            'profit_margin' => 0,
            'opening_balance' => 0,
            'current_balance' => 0,
            'balance_sheet' => ['total_assets' => 0, 'total_liabilities' => 0, 'total_equity' => 0],
            'recent_periods' => [],
            'monthly_revenue' => [],
            'monthly_expenses' => [],
        ];
    }
}
