<?php

namespace App\Services\RevenueExpense;

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\Utilities;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only revenue/expense queries shared by admin + supervisor controllers.
 *
 * Stateful per-request: the controller resolves the ledger owner (admin uses
 * Auth::id(), supervisor uses the admin's user_id), the active fiscal period,
 * and the apartment scope, then hands them to this service. All numbers the
 * dashboard, break-even page, and per-apartment table render flow from here.
 */
class RevenueExpenseQueryService
{
    public function __construct(
        private ?int $userId,
        private ?FiscalPeriods $period,
        private Builder $apartmentsScope,
        private ?int $propertyId = null,
    ) {}

    /**
     * One-shot bundle used by the controllers' index() method.
     *
     * @return array{income: array, expenses: array, summary: array, perApartment: array}
     */
    public function getRevenueExpenseData($startDate = null, $endDate = null): array
    {
        if ((! $startDate || ! $endDate) && $this->period) {
            $startDate = $this->period->opening_date;
            $endDate = $this->period->closing_date;
        }

        $income = $this->calculateIncome($startDate, $endDate);
        $expenses = $this->calculateExpenses($startDate, $endDate);
        $summary = $this->calculateSummary($income, $expenses);
        $perApartment = $this->calculatePerApartmentData($startDate, $endDate);

        return compact('income', 'expenses', 'summary', 'perApartment');
    }

    /**
     * Total income from Accounts (single source of truth).
     *
     * The per-type breakdown (electricity, water, internet, parking, trash)
     * is derived from Utilities so the dashboard can split utility/other
     * buckets by tenant-facing charge type — the *total* always uses Accounts.
     */
    public function calculateIncome($startDate = null, $endDate = null): array
    {
        $query = Accounts::income()->forUser($this->userId)->forProperty($this->propertyId);

        if ($this->period) {
            $query->forPeriod($this->period->id);
        }
        if ($startDate && $endDate) {
            $query->betweenDates($startDate, $endDate);
        }

        $records = $query->get();

        $rentIncome = $records->where('category', Accounts::CAT_RENT_INCOME)->sum('amount');
        $depositIncome = $records->where('category', Accounts::CAT_DEPOSIT_INCOME)->sum('amount');
        $lateFeesIncome = $records->where('category', Accounts::CAT_LATE_FEE_INCOME)->sum('amount');
        $utilityIncomeFromAccts = $records->where('category', Accounts::CAT_UTILITY_INCOME)->sum('amount');
        $otherIncomeFromAccts = $records->where('category', Accounts::CAT_OTHER_INCOME)->sum('amount');

        $totalIncome = $records->sum('amount');
        $paymentCount = $records->whereNotNull('payment_id')->pluck('payment_id')->unique()->count();

        $rangeStart = $startDate ? Carbon::parse($startDate) : ($this->period ? Carbon::parse($this->period->opening_date) : null);
        $rangeEnd = $endDate ? Carbon::parse($endDate) : ($this->period ? Carbon::parse($this->period->closing_date) : null);

        $byType = [];
        if ($rangeStart && $rangeEnd) {
            $apartmentIds = $this->apartmentsScope->clone()->pluck('id');
            $byType = Utilities::whereHas('rental', fn ($q) => $q->whereIn('apartment_id', $apartmentIds))
                ->where('paid_status', true)
                ->whereBetween('paid_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
                ->selectRaw('utility_type, SUM(charge_amount) as total')
                ->groupBy('utility_type')
                ->pluck('total', 'utility_type')
                ->toArray();
        }

        $utilityBreakdown = [
            'electricity' => round($byType['electricity'] ?? 0, 2),
            'water' => round($byType['water'] ?? 0, 2),
        ];

        $otherIncomeBreakdown = [
            'internet' => round($byType['internet'] ?? 0, 2),
            'parking' => round($byType['parking'] ?? 0, 2),
            'trash' => round($byType['trash'] ?? 0, 2),
            'other' => max(0, round($otherIncomeFromAccts
                - ($byType['internet'] ?? 0)
                - ($byType['parking'] ?? 0)
                - ($byType['trash'] ?? 0), 2)),
        ];

        return [
            'rent_income' => round($rentIncome, 2),
            'late_fees' => round($lateFeesIncome, 2),
            'total_utility_income' => round($utilityIncomeFromAccts, 2),
            'utility_breakdown' => $utilityBreakdown,
            'other_income' => round($otherIncomeFromAccts, 2),
            'other_income_breakdown' => $otherIncomeBreakdown,
            'deposit_income' => round($depositIncome, 2),
            'total_income' => round($totalIncome, 2),
            'payment_count' => $paymentCount,
            'average_payment' => $paymentCount > 0 ? round($rentIncome / $paymentCount, 2) : 0,
        ];
    }

    /**
     * Total expenses from Accounts. Splits by category constants.
     */
    public function calculateExpenses($startDate = null, $endDate = null): array
    {
        $query = Accounts::expense()->forUser($this->userId)->forProperty($this->propertyId);

        if ($this->period) {
            $query->forPeriod($this->period->id);
        }
        if ($startDate && $endDate) {
            $query->betweenDates($startDate, $endDate);
        }

        $records = $query->get();

        $fixedExpenses = $records->where('category', Accounts::CAT_BUSINESS_FIXED)->sum('amount');
        $variableExpenses = $records->where('category', Accounts::CAT_BUSINESS_VARIABLE)->sum('amount');
        $utilityExpenses = $records->where('category', Accounts::CAT_UTILITIES_EXPENSE)->sum('amount');
        $depositExpenses = $records->where('category', Accounts::CAT_DEPOSIT_EXPENSE)->sum('amount');
        $otherExpenses = $records->whereNotIn('category', [
            Accounts::CAT_BUSINESS_FIXED,
            Accounts::CAT_BUSINESS_VARIABLE,
            Accounts::CAT_UTILITIES_EXPENSE,
            Accounts::CAT_DEPOSIT_EXPENSE,
        ])->sum('amount');

        $totalExpenses = $fixedExpenses + $variableExpenses + $utilityExpenses + $depositExpenses + $otherExpenses;
        $byCategory = $records->groupBy('category')->map(fn ($items) => round($items->sum('amount'), 2))->toArray();

        return [
            'fixed_expenses' => round($fixedExpenses, 2),
            'variable_expenses' => round($variableExpenses, 2),
            'utility_expenses' => round($utilityExpenses, 2),
            'deposit_expenses' => round($depositExpenses, 2),
            'other_expenses' => round($otherExpenses, 2),
            'by_category' => $byCategory,
            'total_expenses' => round($totalExpenses, 2),
            'expense_count' => $records->count(),
        ];
    }

    /**
     * Profit/loss roll-up from the two arrays above.
     *
     *   net_profit    = total_income − total_expenses
     *   profit_margin = (net_profit / total_income) × 100
     */
    public function calculateSummary(array $income, array $expenses): array
    {
        $netProfit = $income['total_income'] - $expenses['total_expenses'];
        $profitMargin = $income['total_income'] > 0
            ? round(($netProfit / $income['total_income']) * 100, 2)
            : 0;

        return [
            'total_income' => $income['total_income'],
            'rent_income' => $income['rent_income'],
            'total_expenses' => $expenses['total_expenses'],
            'net_profit' => round($netProfit, 2),
            'profit_margin' => $profitMargin,
            'is_profitable' => $netProfit > 0,
        ];
    }

    /**
     * Per-apartment revenue + expense breakdown, prorated by occupancy within
     * the selected date range. Feeds the apartment table on the dashboard.
     */
    public function calculatePerApartmentData($startDate = null, $endDate = null): array
    {
        $rangeStart = Carbon::parse($startDate ?: now()->startOfMonth())->startOfDay();
        $rangeEnd = Carbon::parse($endDate ?: now()->endOfMonth())->endOfDay();
        // Invariant across every rental — compute once.
        $daysInRange = $rangeStart->diffInDays($rangeEnd) + 1;
        $period = $this->period;

        $apartments = $this->apartmentsScope->clone()
            ->with(['floor', 'activeFixedExpenses', 'rentals' => function ($q) use ($period, $startDate, $endDate) {
                $q->with([
                    'tenant',
                    'payments' => function ($pq) use ($period, $startDate, $endDate) {
                        $pq->where('payment_status', 'paid');
                        if ($period) {
                            $pq->whereHas('accounts', function ($aq) use ($period, $startDate, $endDate) {
                                $aq->where('fiscal_period_id', $period->id);
                                if ($startDate && $endDate) {
                                    $aq->whereBetween('transaction_date', [
                                        Carbon::parse($startDate)->startOfDay(),
                                        Carbon::parse($endDate)->endOfDay(),
                                    ]);
                                }
                            });
                        }
                    },
                    'utilities' => function ($uq) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $start = Carbon::parse($startDate);
                            $end = Carbon::parse($endDate);
                            $uq->where(function ($q) use ($start, $end) {
                                $q->whereBetween('paid_at', [$start->startOfDay(), $end->copy()->endOfDay()])
                                    ->orWhere(function ($q2) use ($start, $end) {
                                        $q2->where('billing_year', '>=', $start->year)
                                            ->where('billing_year', '<=', $end->year)
                                            ->where('billing_month', '>=', $start->month)
                                            ->where('billing_month', '<=', $end->month);
                                    });
                            });
                        }
                    },
                ]);
            }])
            ->get();

        // "Other" charges that live only in Accounts (no Utilities row), keyed
        // by reference_number 'tenant_charge:rental:{rental_id}:t{timestamp}'.
        $otherAccountsByRental = $this->loadOtherTenantChargesByRental($startDate, $endDate);

        $perApartment = [];
        foreach ($apartments as $apartment) {
            $income = 0;
            $expenses = 0;
            $otherIncome = 0;
            $utilitiesIncome = 0;
            $expenseBreakdown = ['electricity' => 0, 'water' => 0, 'internet' => 0, 'parking' => 0, 'trash' => 0, 'other' => 0];
            $tenantName = 'Vacant';
            $hasActiveRental = false;
            $rentPercent = 0;
            $rentPaid = 0;
            $rentStatus = 'none';
            $rentDue = $apartment->monthly_rent;
            $occupancyPercent = 0;
            $lastPaymentDate = null;
            $occupancyEndDate = null;
            $daysLeft = null;

            foreach ($apartment->rentals as $rental) {
                $income += $rental->payments->sum('amount') + $rental->payments->sum('late_fee');
                $hasActiveRental = true;
                if ($rental->tenant) {
                    $tenantName = $rental->tenant->name ?? 'N/A';
                }

                $monthPayments = $rental->payments->filter(
                    fn ($p) => $p->payment_type === 'rent' && Carbon::parse($p->paid_at)->between($rangeStart, $rangeEnd)
                );
                $rentPaid = $monthPayments->sum('amount');

                $rentPeriodStart = Carbon::parse($rental->start_date)->startOfDay();
                $rentPeriodEnd = $rental->end_date ? Carbon::parse($rental->end_date)->endOfDay() : null;

                $overlapStart = $rentPeriodStart->greaterThan($rangeStart) ? $rentPeriodStart : $rangeStart;
                $overlapEnd = $rentPeriodEnd
                    ? ($rentPeriodEnd->lessThan($rangeEnd) ? $rentPeriodEnd : $rangeEnd)
                    : $rangeEnd;

                $overlapDays = $overlapStart->lte($overlapEnd) ? $overlapStart->diffInDays($overlapEnd) + 1 : 0;
                $proration = $daysInRange > 0 ? ($overlapDays / $daysInRange) : 0;
                $rentDue = round($rental->rent_amount * $proration, 2);

                if ($proration <= 0) {
                    $rentPercent = 0;
                    $rentStatus = 'none';
                } else {
                    $rentPercent = $rentDue > 0 ? min(round(($rentPaid / $rentDue) * 100, 1), 100) : 0;
                    if ($rentPaid >= $rentDue) {
                        $rentStatus = 'paid';
                    } elseif ($rentPercent > 0) {
                        $rentStatus = 'partial';
                    } else {
                        $dueDay = min($rentPeriodStart->day, $rangeStart->copy()->daysInMonth);
                        $dueDate = Carbon::create($rangeStart->year, $rangeStart->month, $dueDay)->endOfDay();
                        $isFirstMonth = ($rentPeriodStart->month === $rangeStart->month && $rentPeriodStart->year === $rangeStart->year);
                        $rentStatus = (now()->gt($dueDate) && ! $isFirstMonth) ? 'overdue' : 'unpaid';
                    }
                }

                $occupancyPercent = round($proration * 100, 1);
                $lastPaymentDate = $monthPayments->isNotEmpty()
                    ? Carbon::parse($monthPayments->max('paid_at'))->toDateString()
                    : null;
                $occupancyEndDate = $overlapDays > 0 ? $overlapEnd->toDateString() : null;
                if ($occupancyEndDate) {
                    $diff = Carbon::parse($occupancyEndDate)->startOfDay()->diffInDays(Carbon::now()->startOfDay());
                    $daysLeft = $diff > 0 ? $diff : 0;
                }

                foreach ($rental->utilities as $utility) {
                    $type = $utility->utility_type;
                    if (isset($expenseBreakdown[$type])) {
                        $expenseBreakdown[$type] += $utility->charge_amount;
                    }
                    $expenses += $utility->charge_amount;
                    if (in_array($type, ['internet', 'parking', 'trash', 'other'], true)) {
                        $otherIncome += $utility->charge_amount;
                    }
                    if (in_array($type, ['electricity', 'water'], true)) {
                        $utilitiesIncome += $utility->charge_amount;
                    }
                }

                $rentalOtherFromAccounts = $otherAccountsByRental[$rental->id] ?? 0;
                if ($rentalOtherFromAccounts > 0) {
                    $expenseBreakdown['other'] += $rentalOtherFromAccounts;
                    $otherIncome += $rentalOtherFromAccounts;
                    $expenses += $rentalOtherFromAccounts;
                    $income += $rentalOtherFromAccounts;
                }
            }

            $fixedExpTotal = $apartment->activeFixedExpenses->sum('amount');
            $activeRentalId = null;
            $activeTenantId = null;
            foreach ($apartment->rentals as $r) {
                $activeRentalId = $r->id;
                $activeTenantId = $r->tenant_id;
            }

            $perApartment[] = [
                'apartment_id' => $apartment->id,
                'rental_id' => $activeRentalId,
                'tenant_id' => $activeTenantId,
                'apartment_number' => $apartment->apartment_number,
                'floor' => $apartment->floor->floor_number ?? 'N/A',
                'floor_number' => $apartment->floor->floor_number ?? 'N/A',
                'tenant' => $tenantName ?: 'Vacant',
                'has_active_rental' => $hasActiveRental,
                'monthly_rent' => $apartment->monthly_rent,
                'income' => round($income, 2),
                'expenses' => round($expenses, 2),
                'utilities_income' => round($utilitiesIncome, 2),
                'other_income' => round($otherIncome, 2),
                'fixed_expenses' => round($fixedExpTotal, 2),
                'tenant_net' => round($income - $expenses, 2),
                'owner_expenses' => round($fixedExpTotal, 2),
                'net' => round($income - $expenses - $fixedExpTotal, 2),
                'expense_breakdown' => $expenseBreakdown,
                'status' => $apartment->status,
                'rent_percent' => $rentPercent,
                'rent_paid' => round($rentPaid, 2),
                'rent_due' => round($rentDue, 2),
                'occupancy_percent' => $occupancyPercent,
                'last_payment_date' => $lastPaymentDate,
                'occupancy_end_date' => $occupancyEndDate,
                'days_left' => $daysLeft,
                'rent_status' => $hasActiveRental ? $rentStatus : 'none',
            ];
        }

        return $perApartment;
    }

    /**
     * Sum tenant-charge "other" income rows that live only in Accounts (no
     * Utilities row), grouped by the rental_id parsed from reference_number.
     *
     * @return array<int, float>
     */
    private function loadOtherTenantChargesByRental($startDate, $endDate): array
    {
        $query = Accounts::where('account_type', Accounts::TYPE_INCOME)
            ->where('category', Accounts::CAT_OTHER_INCOME)
            ->forProperty($this->propertyId)
            ->where('reference_number', 'LIKE', 'tenant_charge:rental:%');

        if ($this->period) {
            $query->where('fiscal_period_id', $this->period->id);
        } elseif ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }

        $byRental = [];
        foreach ($query->get() as $acct) {
            $parts = explode(':', $acct->reference_number);
            // format: tenant_charge:rental:{rental_id}:t{timestamp}
            if (isset($parts[2]) && is_numeric($parts[2])) {
                $rentalId = (int) $parts[2];
                $byRental[$rentalId] = ($byRental[$rentalId] ?? 0) + $acct->amount;
            }
        }

        return $byRental;
    }
}
