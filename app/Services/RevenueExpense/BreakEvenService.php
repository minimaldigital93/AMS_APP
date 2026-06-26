<?php

namespace App\Services\RevenueExpense;

use App\Models\Accounts;
use App\Models\BusinessExpense;
use App\Models\FiscalPeriods;
use App\Models\Rentals;
use App\Models\Utilities;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Break-even analysis for a given month within the active fiscal period.
 *
 * Uses the dashboard's income/expense numbers (via RevenueExpenseQueryService)
 * so the two pages can never disagree — the contract documented in CLAUDE.md
 * (sec. "Break-even calculation").
 *
 *   variable cost / unit = (total_expenses − business_expenses) / occupied_units
 *   contribution margin  = avg_rent − variable_cost_per_unit
 *   break-even units     = business_expenses / contribution_margin
 *   safety margin        = current_revenue − total_expenses     (= net P/L)
 */
class BreakEvenService
{
    public function __construct(
        private RevenueExpenseQueryService $queryService,
        private ?int $userId,
        private ?FiscalPeriods $period,
        private Builder $apartmentsScope,
        private ?int $propertyId = null,
        private ?array $propertyIds = null,
    ) {}

    /**
     * Narrow a property-owned query (BusinessExpense or Accounts) to the caller's
     * property scope — the active property, else a bounded set (a supervisor's
     * assigned properties), else no narrowing (admin consolidated). Keeps the
     * overhead + variable-cost breakdowns aligned with the income/expense totals,
     * which RevenueExpenseQueryService already scopes the same way.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function scopeToProperty($query)
    {
        if ($this->propertyId !== null) {
            return $query->forProperty($this->propertyId);
        }

        if ($this->propertyIds !== null) {
            return $query->forProperties($this->propertyIds);
        }

        return $query;
    }

    /**
     * Full break-even snapshot for the given month. Falls back to now().
     */
    public function calculate(?int $month = null, ?int $year = null): array
    {
        $month = $month ?: now()->month;
        $year = $year ?: now()->year;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // Only the ids and the count are used below — select the id column alone
        // instead of hydrating every apartment row.
        $apartmentIds = $this->apartmentsScope->clone()->pluck('id');
        $totalApartments = $apartmentIds->count();

        $income = $this->queryService->calculateIncome($monthStart, $monthEnd);
        $expenses = $this->queryService->calculateExpenses($monthStart, $monthEnd);

        $totalRevenue = (float) $income['total_income'];
        $totalExpenses = (float) $expenses['total_expenses'];

        // Fetch the overlapping rentals once and derive both avg rent and the
        // occupancy count from the collection (was two identical DB queries).
        $activeRents = $this->activeRentalsQuery($apartmentIds, $monthStart, $monthEnd)
            ->pluck('rent_amount');
        $currentOccupancy = $activeRents->count();
        $avgRentPerApartment = (float) ($currentOccupancy > 0 ? $activeRents->avg() : 0);

        $businessExpenses = $this->calculateBusinessExpenses($month, $year);
        $variableTotal = max(0, $totalExpenses - $businessExpenses);
        $variableCostPerUnit = $currentOccupancy > 0 ? $variableTotal / $currentOccupancy : 0;

        $contributionMarginPerUnit = $avgRentPerApartment - $variableCostPerUnit;

        // Standard break-even: Fixed Costs / Contribution Margin per Unit.
        // If CM ≤ 0, every new tenant loses money — no occupancy level breaks even.
        $breakEvenFeasible = $contributionMarginPerUnit > 0 || $businessExpenses <= 0;
        $breakEvenUnits = $breakEvenFeasible && $contributionMarginPerUnit > 0
            ? round($businessExpenses / $contributionMarginPerUnit, 2)
            : 0;

        $breakEvenRevenue = $breakEvenFeasible
            ? round($breakEvenUnits * $avgRentPerApartment, 2)
            : 0;

        $safetyMargin = $totalRevenue - $totalExpenses;
        $safetyMarginPercent = $totalRevenue > 0
            ? round(($safetyMargin / $totalRevenue) * 100, 2)
            : 0;

        $isAboveBreakEven = $safetyMargin >= 0;
        $amountNeeded = max(0, -$safetyMargin);
        $unitsNeeded = $breakEvenFeasible
            ? max(0, (int) ceil($breakEvenUnits) - $currentOccupancy)
            : 0;

        $businessBreakdown = $this->getBusinessExpenseBreakdown($month, $year);
        $variableBreakdown = $this->getVariableCostBreakdown($month, $year);

        return [
            'total_apartments' => $totalApartments,
            'avg_rent_per_apartment' => round($avgRentPerApartment, 2),
            'business_expenses' => round($businessExpenses, 2),
            'variable_cost_per_unit' => round($variableCostPerUnit, 2),
            'contribution_margin_per_unit' => round($contributionMarginPerUnit, 2),
            'break_even_units' => $breakEvenUnits,
            'break_even_revenue' => round($breakEvenRevenue, 2),
            'current_occupancy' => $currentOccupancy,
            'current_revenue' => round($totalRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'safety_margin' => round($safetyMargin, 2),
            'safety_margin_percent' => $safetyMarginPercent,
            'is_above_break_even' => $isAboveBreakEven,
            'amount_needed' => round($amountNeeded, 2),
            'units_needed' => $unitsNeeded,
            'business_expense_breakdown' => $businessBreakdown,
            'variable_cost_breakdown' => $variableBreakdown,
            'break_even_feasible' => $breakEvenFeasible,
            'utility_analysis' => $this->getUtilityAnalysis($apartmentIds, $month, $year),
            'biggest_expense' => $this->biggestExpense($businessBreakdown, $variableBreakdown),
        ];
    }

    /**
     * Advanced business-health data for the break-even page: composite 0–100
     * scores, a trailing 6-month revenue/expense/occupancy trend (clamped to
     * the fiscal period) and the selected month's revenue / cost composition.
     *
     * Pure chart data — `$snapshot` is the array returned by calculate() for
     * the same month, so the scores can never disagree with the page above.
     */
    public function getBusinessHealth(array $snapshot, ?int $month = null, ?int $year = null): array
    {
        $month = $month ?: now()->month;
        $year = $year ?: now()->year;
        $selected = Carbon::create($year, $month, 1)->startOfMonth();

        $windowStart = $selected->copy()->subMonths(5);
        if ($this->period) {
            $periodStart = Carbon::parse($this->period->opening_date)->startOfMonth();
            if ($windowStart->lt($periodStart)) {
                $windowStart = $periodStart->copy();
            }
        }

        $apartmentIds = $this->apartmentsScope->clone()->pluck('id');
        $totalApartments = (int) $snapshot['total_apartments'];

        $trend = [];
        $selectedIncome = null;
        $selectedExpenses = null;

        for ($cursor = $windowStart->copy(); $cursor->lte($selected); $cursor->addMonth()) {
            $start = $cursor->copy()->startOfMonth();
            $end = $cursor->copy()->endOfMonth();

            $income = $this->queryService->calculateIncome($start, $end);
            $expenses = $this->queryService->calculateExpenses($start, $end);
            $occupied = $this->activeRentalsQuery($apartmentIds, $start, $end)->count();

            $trend[] = [
                'label' => $cursor->format('M Y'),
                'revenue' => round((float) $income['total_income'], 2),
                'expenses' => round((float) $expenses['total_expenses'], 2),
                'net' => round((float) $income['total_income'] - (float) $expenses['total_expenses'], 2),
                'occupancy_pct' => $totalApartments > 0 ? round($occupied / $totalApartments * 100, 1) : 0,
            ];

            if ($cursor->month === $selected->month && $cursor->year === $selected->year) {
                $selectedIncome = $income;
                $selectedExpenses = $expenses;
            }
        }

        // Defensive: callers outside breakEvenPoint() may pass a month before
        // the fiscal period start, leaving the loop empty.
        if (! $selectedIncome || ! $selectedExpenses) {
            $selectedIncome = $this->queryService->calculateIncome($selected, $selected->copy()->endOfMonth());
            $selectedExpenses = $this->queryService->calculateExpenses($selected, $selected->copy()->endOfMonth());
        }

        $occupancyScore = $totalApartments > 0
            ? min(100, $snapshot['current_occupancy'] / $totalApartments * 100)
            : 0;

        // A 30%+ net margin scores full marks.
        $profitabilityScore = $snapshot['current_revenue'] > 0
            ? min(100, max(0, $snapshot['safety_margin_percent'] / 30 * 100))
            : 0;

        $beCoverageScore = ! $snapshot['break_even_feasible'] ? 0
            : ($snapshot['break_even_units'] <= 0 ? 100
                : min(100, $snapshot['current_occupancy'] / $snapshot['break_even_units'] * 100));

        // Share of each rent dollar left after the unit's variable costs.
        $costEfficiencyScore = $snapshot['avg_rent_per_apartment'] > 0
            ? min(100, max(0, $snapshot['contribution_margin_per_unit'] / $snapshot['avg_rent_per_apartment'] * 100))
            : 0;

        $expectedRent = $snapshot['avg_rent_per_apartment'] * $snapshot['current_occupancy'];
        $collectionScore = $expectedRent > 0
            ? min(100, (float) $selectedIncome['rent_income'] / $expectedRent * 100)
            : 0;

        $scores = [
            'occupancy' => (int) round($occupancyScore),
            'profitability' => (int) round($profitabilityScore),
            'break_even_coverage' => (int) round($beCoverageScore),
            'cost_efficiency' => (int) round($costEfficiencyScore),
            'collection' => (int) round($collectionScore),
        ];
        $scores['overall'] = (int) round(array_sum($scores) / count($scores));

        $revenueMix = array_filter([
            'rent_income' => round((float) $selectedIncome['rent_income'], 2),
            'utilities' => round((float) $selectedIncome['total_utility_income'], 2),
            'late_fees' => round((float) $selectedIncome['late_fees'], 2),
            'deposit' => round((float) $selectedIncome['deposit_income'], 2),
            'other' => round((float) $selectedIncome['other_income'], 2),
        ], fn ($v) => $v > 0);

        $expenseMix = array_filter([
            'fixed_expenses' => round((float) $selectedExpenses['fixed_expenses'], 2),
            'variable_expenses' => round((float) $selectedExpenses['variable_expenses'], 2),
            'utilities' => round((float) $selectedExpenses['utility_expenses'], 2),
            'deposit_refunds' => round((float) $selectedExpenses['deposit_expenses'], 2),
            'other' => round((float) $selectedExpenses['other_expenses'], 2),
        ], fn ($v) => $v > 0);

        return [
            'scores' => $scores,
            'trend' => $trend,
            'revenue_mix' => $revenueMix,
            'expense_mix' => $expenseMix,
        ];
    }

    /**
     * Per-room utility insight for the given month — feeds the utilities donut
     * and the "average per room" / "most expensive apartment" cards.
     *
     * Utilities are billed per rental (Utilities.rental_id → Rentals.apartment_id),
     * so we scope through the owner's apartments and aggregate two ways:
     * by utility_type (donut) and by apartment (to find the heaviest user).
     */
    public function getUtilityAnalysis($apartmentIds, ?int $month = null, ?int $year = null): array
    {
        $month = $month ?: now()->month;
        $year = $year ?: now()->year;

        $base = Utilities::query()
            ->join('rentals', 'utilities.rental_id', '=', 'rentals.id')
            ->where('utilities.billing_month', $month)
            ->where('utilities.billing_year', $year)
            ->whereIn('rentals.apartment_id', $apartmentIds);

        $typeLabels = [
            'electricity' => 'Electricity',
            'water' => 'Water',
            'internet' => 'Internet',
            'trash' => 'Trash',
            'parking' => 'Parking',
            'cleaning' => 'Cleaning',
            'other' => 'Other',
        ];

        $byTypeRaw = (clone $base)
            ->selectRaw('utilities.utility_type as t, SUM(utilities.charge_amount) as total')
            ->groupBy('utilities.utility_type')
            ->orderByDesc('total')
            ->pluck('total', 't')
            ->toArray();

        $byType = [];
        foreach ($byTypeRaw as $type => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $byType[] = [
                'label' => $typeLabels[$type] ?? ucfirst((string) $type),
                'amount' => round((float) $amount, 2),
            ];
        }

        $total = (float) array_sum(array_column($byType, 'amount'));
        $roomsUsed = (int) (clone $base)->distinct()->count('rentals.apartment_id');
        $avgPerRoom = $roomsUsed > 0 ? $total / $roomsUsed : 0;

        $topApt = (clone $base)
            ->join('apartments', 'rentals.apartment_id', '=', 'apartments.id')
            ->selectRaw('apartments.apartment_number as num, SUM(utilities.charge_amount) as total')
            ->groupBy('apartments.apartment_number')
            ->orderByDesc('total')
            ->first();

        return [
            'total' => round($total, 2),
            'avg_per_room' => round($avgPerRoom, 2),
            'rooms_used' => $roomsUsed,
            'by_type' => $byType,
            'top_apartment' => $topApt ? [
                'label' => $topApt->num,
                'amount' => round((float) $topApt->total, 2),
            ] : null,
        ];
    }

    /**
     * The single largest expense line across overhead + variable costs —
     * the best candidate to cut down. Null when nothing is recorded.
     */
    private function biggestExpense(array $businessBreakdown, array $variableBreakdown): ?array
    {
        $all = array_merge($businessBreakdown, $variableBreakdown);
        if (empty($all)) {
            return null;
        }

        usort($all, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $all[0]['amount'] > 0 ? $all[0] : null;
    }

    /**
     * Itemized list of business expenses for the given month — feeds the
     * "Business overhead" panel on the break-even page.
     */
    public function getBusinessExpenseBreakdown(?int $month = null, ?int $year = null): array
    {
        $month = $month ?: now()->month;
        $year = $year ?: now()->year;

        $query = $this->scopeToProperty(BusinessExpense::where('user_id', $this->userId))
            ->where('billing_month', $month)
            ->where('billing_year', $year);

        if ($this->period) {
            $query->where('fiscal_period_id', $this->period->id);
        }

        return $query->get()->map(fn ($e) => [
            'label' => $e->expense_name ?: ($e->category ?: 'Business Expense'),
            'amount' => round((float) $e->amount, 2),
        ])->toArray();
    }

    /**
     * Per-category breakdown of variable costs. Excludes BusinessExpense-backed
     * categories (those are counted as overhead) and refunds.
     */
    public function getVariableCostBreakdown(?int $month = null, ?int $year = null): array
    {
        $month = $month ?: now()->month;
        $year = $year ?: now()->year;

        $query = $this->scopeToProperty(Accounts::expense()->forUser($this->userId))
            ->whereNotIn('category', [
                Accounts::CAT_BUSINESS_FIXED,
                Accounts::CAT_BUSINESS_VARIABLE,
                Accounts::CAT_DEPOSIT_EXPENSE,
            ])
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year);

        if ($this->period) {
            $query->forPeriod($this->period->id);
        }

        $rows = $query->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        $labels = [
            'utilities_expense' => 'Utilities (vendor bills)',
            'maintenance' => 'Maintenance & Repairs',
            'repairs' => 'Repairs',
            'insurance' => 'Insurance',
            'property_tax' => 'Property Tax',
            'management' => 'Property Management',
            'cleaning' => 'Cleaning Services',
            'security' => 'Security',
            'landscaping' => 'Landscaping',
            'supplies' => 'Supplies & Materials',
            'marketing' => 'Marketing & Advertising',
            'legal' => 'Legal & Professional Fees',
            'salaries' => 'Salaries & Wages',
            'taxes' => 'Taxes',
            'other_expense' => 'Other Expense',
            'miscellaneous' => 'Miscellaneous',
        ];

        $breakdown = [];
        foreach ($rows as $cat => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $breakdown[] = [
                'label' => $labels[$cat] ?? ucfirst(str_replace('_', ' ', (string) $cat)),
                'amount' => round((float) $amount, 2),
            ];
        }

        return $breakdown;
    }

    /**
     * Recurring business overhead for the given month — the "fixed costs"
     * input to break-even.
     */
    public function calculateBusinessExpenses(?int $month = null, ?int $year = null): float
    {
        $month = $month ?: now()->month;
        $year = $year ?: now()->year;

        $query = $this->scopeToProperty(BusinessExpense::where('user_id', $this->userId))
            ->where('billing_month', $month)
            ->where('billing_year', $year);

        if ($this->period) {
            $query->where('fiscal_period_id', $this->period->id);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Variable cost per occupied unit for the given month.
     *
     * Pulled from Accounts (vendor-side expenses only): utilities_expense,
     * maintenance, other_expense. Tenant utility CHARGES are income —
     * they must not appear here.
     *
     * NOTE: currently unused by calculate() (it derives variable cost from
     * total_expenses − business_expenses instead, which is more inclusive).
     * Preserved verbatim from the original controller in case external callers
     * are added later.
     */
    public function calculateVariableCostPerUnit(?int $month = null, ?int $year = null): float
    {
        $apartmentIds = $this->apartmentsScope->clone()->pluck('id');
        $month = $month ?: now()->month;
        $year = $year ?: now()->year;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $occupiedCount = $this->activeRentalsQuery($apartmentIds, $monthStart, $monthEnd)->count();
        if ($occupiedCount === 0) {
            return 0;
        }

        $variableCategories = [
            Accounts::CAT_UTILITIES_EXPENSE,
            Accounts::CAT_MAINTENANCE,
            Accounts::CAT_OTHER_EXPENSE,
        ];

        $query = $this->scopeToProperty(Accounts::expense()->forUser($this->userId))
            ->whereIn('category', $variableCategories)
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year);

        if ($this->period) {
            $query->forPeriod($this->period->id);
        }

        return (float) $query->sum('amount') / $occupiedCount;
    }

    /**
     * Rentals overlapping the given month window — used for both avg rent
     * and occupancy count.
     */
    private function activeRentalsQuery($apartmentIds, Carbon $monthStart, Carbon $monthEnd): Builder
    {
        return Rentals::whereIn('apartment_id', $apartmentIds)
            ->where('start_date', '<=', $monthEnd)
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $monthStart);
            });
    }
}
