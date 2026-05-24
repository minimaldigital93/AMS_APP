<?php

namespace App\Services\RevenueExpense;

use App\Models\Accounts;
use App\Models\BusinessExpense;
use App\Models\FiscalPeriods;
use App\Models\Rentals;
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
    ) {}

    /**
     * Full break-even snapshot for the given month. Falls back to now().
     */
    public function calculate(?int $month = null, ?int $year = null): array
    {
        $month = $month ?: now()->month;
        $year  = $year  ?: now()->year;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd   = $monthStart->copy()->endOfMonth();

        $apartments      = $this->apartmentsScope->clone()->get();
        $apartmentIds    = $apartments->pluck('id');
        $totalApartments = $apartments->count();

        $income   = $this->queryService->calculateIncome($monthStart, $monthEnd);
        $expenses = $this->queryService->calculateExpenses($monthStart, $monthEnd);

        $totalRevenue  = (float) $income['total_income'];
        $totalExpenses = (float) $expenses['total_expenses'];

        $avgRentPerApartment = (float) ($this->activeRentalsQuery($apartmentIds, $monthStart, $monthEnd)
            ->avg('rent_amount') ?? 0);

        $currentOccupancy = $this->activeRentalsQuery($apartmentIds, $monthStart, $monthEnd)->count();

        $businessExpenses    = $this->calculateBusinessExpenses($month, $year);
        $variableTotal       = max(0, $totalExpenses - $businessExpenses);
        $variableCostPerUnit = $currentOccupancy > 0 ? $variableTotal / $currentOccupancy : 0;

        $contributionMarginPerUnit = $avgRentPerApartment - $variableCostPerUnit;

        // Standard break-even: Fixed Costs / Contribution Margin per Unit.
        // If CM ≤ 0, every new tenant loses money — no occupancy level breaks even.
        $breakEvenFeasible = $contributionMarginPerUnit > 0 || $businessExpenses <= 0;
        $breakEvenUnits    = $breakEvenFeasible && $contributionMarginPerUnit > 0
            ? round($businessExpenses / $contributionMarginPerUnit, 2)
            : 0;

        $breakEvenRevenue = $breakEvenFeasible
            ? round($breakEvenUnits * $avgRentPerApartment, 2)
            : 0;

        $safetyMargin        = $totalRevenue - $totalExpenses;
        $safetyMarginPercent = $totalRevenue > 0
            ? round(($safetyMargin / $totalRevenue) * 100, 2)
            : 0;

        $isAboveBreakEven = $safetyMargin >= 0;
        $amountNeeded     = max(0, -$safetyMargin);
        $unitsNeeded      = $breakEvenFeasible
            ? max(0, (int) ceil($breakEvenUnits) - $currentOccupancy)
            : 0;

        return [
            'total_apartments'             => $totalApartments,
            'avg_rent_per_apartment'       => round($avgRentPerApartment, 2),
            'business_expenses'            => round($businessExpenses, 2),
            'variable_cost_per_unit'       => round($variableCostPerUnit, 2),
            'contribution_margin_per_unit' => round($contributionMarginPerUnit, 2),
            'break_even_units'             => $breakEvenUnits,
            'break_even_revenue'           => round($breakEvenRevenue, 2),
            'current_occupancy'            => $currentOccupancy,
            'current_revenue'              => round($totalRevenue, 2),
            'total_expenses'               => round($totalExpenses, 2),
            'safety_margin'                => round($safetyMargin, 2),
            'safety_margin_percent'        => $safetyMarginPercent,
            'is_above_break_even'          => $isAboveBreakEven,
            'amount_needed'                => round($amountNeeded, 2),
            'units_needed'                 => $unitsNeeded,
            'business_expense_breakdown'   => $this->getBusinessExpenseBreakdown($month, $year),
            'variable_cost_breakdown'      => $this->getVariableCostBreakdown($month, $year),
            'break_even_feasible'          => $breakEvenFeasible,
        ];
    }

    /**
     * Itemized list of business expenses for the given month — feeds the
     * "Business overhead" panel on the break-even page.
     */
    public function getBusinessExpenseBreakdown(?int $month = null, ?int $year = null): array
    {
        $month = $month ?: now()->month;
        $year  = $year  ?: now()->year;

        $query = BusinessExpense::where('user_id', $this->userId)
            ->where('billing_month', $month)
            ->where('billing_year', $year);

        if ($this->period) {
            $query->where('fiscal_period_id', $this->period->id);
        }

        return $query->get()->map(fn ($e) => [
            'label'  => $e->expense_name ?: ($e->category ?: 'Business Expense'),
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
        $year  = $year  ?: now()->year;

        $query = Accounts::expense()
            ->forUser($this->userId)
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
            'maintenance'       => 'Maintenance & Repairs',
            'repairs'           => 'Repairs',
            'insurance'         => 'Insurance',
            'property_tax'      => 'Property Tax',
            'management'        => 'Property Management',
            'cleaning'          => 'Cleaning Services',
            'security'          => 'Security',
            'landscaping'       => 'Landscaping',
            'supplies'          => 'Supplies & Materials',
            'marketing'         => 'Marketing & Advertising',
            'legal'             => 'Legal & Professional Fees',
            'salaries'          => 'Salaries & Wages',
            'taxes'             => 'Taxes',
            'other_expense'     => 'Other Expense',
            'miscellaneous'     => 'Miscellaneous',
        ];

        $breakdown = [];
        foreach ($rows as $cat => $amount) {
            if ($amount <= 0) continue;
            $breakdown[] = [
                'label'  => $labels[$cat] ?? ucfirst(str_replace('_', ' ', (string) $cat)),
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
        $year  = $year  ?: now()->year;

        $query = BusinessExpense::where('user_id', $this->userId)
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
        $year  = $year  ?: now()->year;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd   = $monthStart->copy()->endOfMonth();

        $occupiedCount = $this->activeRentalsQuery($apartmentIds, $monthStart, $monthEnd)->count();
        if ($occupiedCount === 0) {
            return 0;
        }

        $variableCategories = [
            Accounts::CAT_UTILITIES_EXPENSE,
            Accounts::CAT_MAINTENANCE,
            Accounts::CAT_OTHER_EXPENSE,
        ];

        $query = Accounts::expense()
            ->forUser($this->userId)
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
