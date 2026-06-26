<?php

namespace App\Services\Dashboard;

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daily income/expense totals for the dashboard mini-calendar.
 *
 * Returns a per-day map for the selected month plus the month-level
 * roll-ups the view renders in its header (total income, total expense,
 * net, and which day was the "best" by net).
 */
class DashboardCalendarService
{
    public function __construct(
        private int $userId,
        private ?array $apartmentIds = null,
        private ?int $propertyId = null,
    ) {}

    /**
     * Build the calendar for the given month.
     *
     * @param  FiscalPeriods|null  $activePeriod  supervisor scopes to this period
     *                                            when present; admin uses user_id
     */
    public function build(?FiscalPeriods $activePeriod, Carbon $selectedMonth): array
    {
        $startOfMonth = $selectedMonth->copy()->startOfMonth();
        $endOfMonth = $selectedMonth->copy()->endOfMonth();

        $dailyIncome = $this->dailyTotals(Accounts::TYPE_INCOME, 'total_income', $activePeriod, $startOfMonth, $endOfMonth);
        $dailyExpenses = $this->dailyTotals(Accounts::TYPE_EXPENSE, 'total_expense', $activePeriod, $startOfMonth, $endOfMonth);

        $daysInMonth = $startOfMonth->daysInMonth;
        $firstDayOfWeek = $startOfMonth->dayOfWeek;
        $calendarDays = [];
        $monthTotalIncome = 0;
        $monthTotalExpense = 0;
        $bestDay = null;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = $startOfMonth->copy()->day($d)->toDateString();
            $income = $dailyIncome[$dateStr]->total_income ?? 0;
            $expense = $dailyExpenses[$dateStr]->total_expense ?? 0;
            $net = $income - $expense;
            $txCount = ($dailyIncome[$dateStr]->tx_count ?? 0) + ($dailyExpenses[$dateStr]->tx_count ?? 0);

            $monthTotalIncome += $income;
            $monthTotalExpense += $expense;

            $calendarDays[$d] = [
                'date' => $dateStr,
                'day' => $d,
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'net' => round($net, 2),
                'tx_count' => $txCount,
                'is_today' => $dateStr === now()->toDateString(),
                'is_future' => Carbon::parse($dateStr)->gt(now()),
            ];

            if ($txCount > 0 && ($bestDay === null || $net > $calendarDays[$bestDay]['net'])) {
                $bestDay = $d;
            }
        }

        return [
            'startOfMonth' => $startOfMonth,
            'firstDayOfWeek' => $firstDayOfWeek,
            'daysInMonth' => $daysInMonth,
            'calendarDays' => $calendarDays,
            'monthTotalIncome' => $monthTotalIncome,
            'monthTotalExpense' => $monthTotalExpense,
            'monthNet' => $monthTotalIncome - $monthTotalExpense,
            'bestDay' => $bestDay,
        ];
    }

    /**
     * GROUP BY DATE(transaction_date) aggregation, scoped to the role.
     */
    private function dailyTotals(string $accountType, string $sumAlias, ?FiscalPeriods $activePeriod, Carbon $start, Carbon $end)
    {
        $query = Accounts::where('account_type', $accountType)
            ->whereBetween('transaction_date', [$start, $end]);

        $this->applyScope($query, $activePeriod);

        return $query->selectRaw('DATE(transaction_date) as day, SUM(amount) as '.$sumAlias.', COUNT(*) as tx_count')
            ->groupByRaw('DATE(transaction_date)')
            ->get()
            ->keyBy('day');
    }

    /**
     * Apply the role-specific scope:
     *   - admin: where user_id = $this->userId
     *   - supervisor: where (payment.rental.apartment_id in $apartmentIds) OR
     *                       (expense with no payment_id — manual business expense),
     *                 also constrained to the active fiscal period
     */
    private function applyScope(Builder $query, ?FiscalPeriods $activePeriod): void
    {
        if ($this->apartmentIds === null) {
            $query->where('user_id', $this->userId)->forProperty($this->propertyId);

            return;
        }

        if ($activePeriod) {
            $query->where('fiscal_period_id', $activePeriod->id);
        }

        $apartmentIds = $this->apartmentIds;
        $query->where(function ($q) use ($apartmentIds) {
            $q->whereHas('payment.rental', fn ($r) => $r->whereIn('apartment_id', $apartmentIds))
                ->orWhere(function ($q2) {
                    $q2->where('account_type', Accounts::TYPE_EXPENSE)->whereNull('payment_id');
                });
        })->forProperty($this->propertyId);
    }
}
