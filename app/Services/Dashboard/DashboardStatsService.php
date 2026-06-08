<?php

namespace App\Services\Dashboard;

use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\TenantLeave;
use App\Models\Tenants;
use App\Models\Utilities;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Assembles the big stats bundle shown on the admin & supervisor dashboards.
 *
 * Scope rules:
 *   - $userId is used to filter Accounts (the ledger owner — admin uses its
 *     own Auth::id(); supervisor uses the admin's user_id from the period).
 *   - $apartmentIds, when provided, narrows every Apartment/Tenant/Rental/
 *     Utility query to that subset (supervisor scope). When null, everything
 *     is unscoped (admin sees the full property).
 *
 * Return shape is identical for both roles — the views render off the same
 * keys regardless of who's asking.
 */
class DashboardStatsService
{
    public function __construct(
        private int $userId,
        private ?array $apartmentIds = null,
    ) {}

    /**
     * Build the full stats array for the given date window.
     *
     * @param  Carbon  $startDate  inclusive start of the window
     * @param  Carbon  $endDate  inclusive end of the window
     * @param  Carbon  $referenceMonth  the "selected" month — drives the
     *                                  paid/pending/overdue rent classification
     */
    public function build(Carbon $startDate, Carbon $endDate, Carbon $referenceMonth): array
    {
        $referenceDate = $this->resolveReferenceDate($referenceMonth, $endDate);

        [$paidCount, $pendingCount, $overdueCount, $totalPendingAmount] =
            $this->countRentPaymentStatus($startDate, $endDate, $referenceMonth, $referenceDate);

        $monthlyRevenueAccounts = $this->scopedIncomeAccountsInRange($startDate, $endDate)->get();
        $monthlyExpenseAccounts = $this->scopedExpenseAccountsInRange($startDate, $endDate)->get();

        $monthlyCollected = $monthlyRevenueAccounts->where('category', '!=', Accounts::CAT_LATE_FEE_INCOME)->sum('amount');
        $monthlyLateFees = $monthlyRevenueAccounts->where('category', Accounts::CAT_LATE_FEE_INCOME)->sum('amount');
        $monthlyTotalRevenue = $monthlyCollected + $monthlyLateFees;

        $monthlyUtilities = $monthlyExpenseAccounts->where('category', Accounts::CAT_UTILITIES_EXPENSE)->sum('amount');
        $monthlyAccountExpenses = $monthlyExpenseAccounts->where('category', '!=', Accounts::CAT_UTILITIES_EXPENSE)->sum('amount');
        $monthlyExpensesTotal = $monthlyExpenseAccounts->sum('amount');

        $utilityBreakdown = $this->utilityBreakdown($startDate, $endDate);

        [$floorLabels, $floorOccupancy, $floorsCount] = $this->floorOccupancy();
        $expiringSoon = $this->expiringSoonRentals();

        $apartmentCounts = $this->countByStatus(
            $this->scopedApartmentQuery(),
            ['available', 'occupied']
        );
        $apartmentCounts['total'] = $this->apartmentIds !== null
            ? count($this->apartmentIds)
            : Apartments::count();

        $tenantCounts = $this->countByStatus(
            $this->scopedTenantQuery(),
            ['active', 'inactive', 'pending']
        );
        $tenantCounts['total'] = $this->scopedTenantQuery()->count();

        return [
            'floors_count' => $floorsCount,
            'apartments' => $apartmentCounts,
            'tenants' => $tenantCounts,
            'rentals' => [
                'total' => $this->scopedRentalQuery()->count(),
                'active' => $this->scopedRentalQuery()
                    ->where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })
                    ->count(),
            ],
            'leases' => ['expiring_soon' => $expiringSoon],
            'payments' => [
                'paid' => $paidCount,
                'pending' => $pendingCount,
                'overdue' => $overdueCount,
                'total_collected' => $this->collectedTotal($startDate, $endDate),
                'total_pending' => $totalPendingAmount,
            ],
            'revenue' => [
                'total_monthly' => round($monthlyTotalRevenue, 2),
                'total_monthly_rent' => $this->scopedApartmentQuery()->where('status', 'occupied')->sum('monthly_rent'),
                'collected_this_month' => round($monthlyCollected, 2),
                'late_fees_this_month' => round($monthlyLateFees, 2),
                'by_type' => [
                    'rent' => round($monthlyRevenueAccounts->where('category', Accounts::CAT_RENT_INCOME)->sum('amount'), 2),
                    'deposit' => round($monthlyRevenueAccounts->where('category', Accounts::CAT_DEPOSIT_INCOME)->sum('amount'), 2),
                    'utilities' => round($monthlyRevenueAccounts->where('category', Accounts::CAT_UTILITY_INCOME)->sum('amount'), 2),
                    'other' => round($monthlyRevenueAccounts->where('category', Accounts::CAT_OTHER_INCOME)->sum('amount'), 2),
                ],
                'archived_deposits' => 0,
            ],
            'expenses' => [
                'monthly_total' => round($monthlyExpensesTotal, 2),
                'utilities_total' => round($monthlyUtilities, 2),
                'account_total' => round($monthlyAccountExpenses, 2),
                'deposit_refunds' => round($monthlyExpenseAccounts->where('category', Accounts::CAT_DEPOSIT_EXPENSE)->sum('amount'), 2),
                'utility_breakdown' => $utilityBreakdown,
                'account_breakdown' => $monthlyExpenseAccounts
                    ->where('category', '!=', Accounts::CAT_UTILITIES_EXPENSE)
                    ->groupBy('category')
                    ->map(fn ($items) => round($items->sum('amount'), 2))
                    ->toArray(),
            ],
            'floor_labels' => $floorLabels,
            'floor_occupancy' => $floorOccupancy,
            'tenants_on_leave' => $this->tenantsOnLeaveCount(),
        ];
    }

    /**
     * Reference date used to classify rent as paid/pending/overdue:
     *   - Current month     → use now() (don't roll forward to end-of-month
     *                         or rents whose due date hasn't arrived yet
     *                         would be wrongly flagged as overdue)
     *   - Future month      → use start of that month (nothing overdue yet)
     *   - Past month        → use end of that month
     */
    private function resolveReferenceDate(Carbon $referenceMonth, Carbon $endDate): Carbon
    {
        $isCurrentMonth = $referenceMonth->year === now()->year && $referenceMonth->month === now()->month;
        $isFutureMonth = $referenceMonth->copy()->startOfMonth()->gt(now()->copy()->startOfMonth());

        return match (true) {
            $isCurrentMonth => now(),
            $isFutureMonth => $referenceMonth->copy()->startOfMonth(),
            default => $endDate->copy()->endOfDay(),
        };
    }

    /**
     * Walk active rentals in the window and classify each as paid/pending/overdue.
     *
     * @return array{0:int,1:int,2:int,3:float} [paid, pending, overdue, totalPending]
     */
    private function countRentPaymentStatus(Carbon $startDate, Carbon $endDate, Carbon $referenceMonth, Carbon $referenceDate): array
    {
        $currentMonth = $referenceMonth->month;
        $currentYear = $referenceMonth->year;

        $paidCount = $pendingCount = $overdueCount = 0;
        $totalPendingAmount = 0.0;

        $activeRentals = $this->scopedRentalQuery()
            ->with(['payments' => fn ($pq) => $pq->where('payment_status', 'paid'), 'apartment'])
            ->where('start_date', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
            })
            ->get();

        foreach ($activeRentals as $rental) {
            $paidThisMonth = $rental->payments
                ->filter(fn ($p) => $p->payment_type === 'rent'
                    && Carbon::parse($p->paid_at)->month === $currentMonth
                    && Carbon::parse($p->paid_at)->year === $currentYear)
                ->isNotEmpty();

            $start = $rental->start_date ? Carbon::parse($rental->start_date) : null;
            $dueDay = $start ? $start->day : 1;
            $dueDay = min($dueDay, Carbon::create($currentYear, $currentMonth)->daysInMonth);
            $dueDate = Carbon::create($currentYear, $currentMonth, $dueDay)->endOfDay();

            // If the rental started in the reference month and hasn't paid yet,
            // treat the first partial month as pending (do not mark overdue).
            if ($start && $start->month === $currentMonth && $start->year === $currentYear && ! $paidThisMonth) {
                $pendingCount++;
                $totalPendingAmount += $rental->rent_amount;

                continue;
            }

            if ($paidThisMonth) {
                $paidCount++;
            } elseif ($referenceDate->gt($dueDate)) {
                $overdueCount++;
                $totalPendingAmount += $rental->rent_amount;
            } else {
                $pendingCount++;
                $totalPendingAmount += $rental->rent_amount;
            }
        }

        return [$paidCount, $pendingCount, $overdueCount, $totalPendingAmount];
    }

    /**
     * Per-utility-type sum for the window. Includes any charge whose paid_at
     * falls in the range OR whose billing_month/year is within range — keeps
     * us aligned with the revenue/expense page's utility breakdown card.
     */
    private function utilityBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $query = Utilities::query();

        if ($this->apartmentIds !== null) {
            $query->whereHas('rental', fn ($q) => $q->whereIn('apartment_id', $this->apartmentIds));
        }

        return $query
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('paid_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereRaw('(billing_year * 100 + billing_month) >= ?', [$startDate->year * 100 + $startDate->month])
                            ->whereRaw('(billing_year * 100 + billing_month) <= ?', [$endDate->year * 100 + $endDate->month]);
                    });
            })
            ->selectRaw('utility_type, SUM(charge_amount) as total')
            ->groupBy('utility_type')
            ->pluck('total', 'utility_type')
            ->toArray();
    }

    /**
     * Occupancy % per floor. Supervisor scope skips floors with no apartments
     * in scope (so an empty floor doesn't show up as "0%").
     *
     * @return array{0: list<string>, 1: list<float>, 2: int}
     */
    private function floorOccupancy(): array
    {
        $floorsQuery = Floors::orderBy('id');
        if ($this->apartmentIds !== null) {
            $floorsQuery->with(['apartments' => fn ($q) => $q->whereIn('id', $this->apartmentIds)]);
        } else {
            $floorsQuery->with('apartments');
        }
        $floors = $floorsQuery->get();

        $floorLabels = [];
        $floorOccupancy = [];
        $floorsWithApartments = 0;
        foreach ($floors as $floor) {
            $total = $floor->apartments->count();
            if ($total === 0) {
                continue;
            }
            $floorsWithApartments++;
            $occupied = $floor->apartments->where('status', 'occupied')->count();
            $floorLabels[] = $floor->floor_name ?? 'Floor '.$floor->id;
            $floorOccupancy[] = round(($occupied / $total) * 100, 1);
        }

        // Admin reports total floors_count (matches legacy Floors::count());
        // supervisor reports only floors that contain in-scope apartments.
        $floorsCount = $this->apartmentIds === null ? Floors::count() : $floorsWithApartments;

        return [$floorLabels, $floorOccupancy, $floorsCount];
    }

    private function expiringSoonRentals()
    {
        $query = $this->scopedRentalQuery()
            ->with(['tenant', 'apartment'])
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->orderBy('end_date');

        return $query->get();
    }

    private function collectedTotal(Carbon $startDate, Carbon $endDate): float
    {
        $query = Payments::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);

        if ($this->apartmentIds !== null) {
            $query->whereHas('rental', fn ($q) => $q->whereIn('apartment_id', $this->apartmentIds));
        }

        return (float) $query->sum('amount');
    }

    private function tenantsOnLeaveCount(): int
    {
        $query = TenantLeave::query();
        if ($this->apartmentIds !== null) {
            $query->whereIn('apartment_id', $this->apartmentIds);
        }

        return $query->count();
    }

    /**
     * Income Accounts query, scoped to user + apartment + date.
     * Admin path filters by user_id; supervisor path additionally filters
     * by payment->rental->apartment_id (since the admin owns the ledger but
     * the supervisor only sees rows tied to its apartments).
     */
    private function scopedIncomeAccountsInRange(Carbon $startDate, Carbon $endDate): Builder
    {
        $query = Accounts::where('account_type', Accounts::TYPE_INCOME)
            ->whereBetween('transaction_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);

        if ($this->apartmentIds === null) {
            $query->where('user_id', $this->userId);
        } else {
            $query->whereHas('payment.rental', fn ($q) => $q->whereIn('apartment_id', $this->apartmentIds));
        }

        return $query;
    }

    /**
     * Expense Accounts query. Supervisor includes both apartment-linked
     * expenses (via payment->rental) AND null-payment expenses (manual
     * business expenses recorded against the period as a whole).
     */
    private function scopedExpenseAccountsInRange(Carbon $startDate, Carbon $endDate): Builder
    {
        $query = Accounts::where('account_type', Accounts::TYPE_EXPENSE)
            ->whereBetween('transaction_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);

        if ($this->apartmentIds === null) {
            $query->where('user_id', $this->userId);
        } else {
            $apartmentIds = $this->apartmentIds;
            $query->where(function ($q) use ($apartmentIds) {
                $q->whereHas('payment.rental', fn ($r) => $r->whereIn('apartment_id', $apartmentIds))
                    ->orWhereNull('payment_id');
            });
        }

        return $query;
    }

    private function scopedApartmentQuery(): Builder
    {
        $query = Apartments::query();
        if ($this->apartmentIds !== null) {
            $query->whereIn('id', $this->apartmentIds);
        }

        return $query;
    }

    private function scopedTenantQuery(): Builder
    {
        $query = Tenants::query();
        if ($this->apartmentIds !== null) {
            $query->whereIn('apartment_id', $this->apartmentIds);
        }

        return $query;
    }

    private function scopedRentalQuery(): Builder
    {
        $query = Rentals::query();
        if ($this->apartmentIds !== null) {
            $query->whereIn('apartment_id', $this->apartmentIds);
        }

        return $query;
    }

    /**
     * Count() per status value, returning a keyed array.
     *
     * @param  list<string>  $statuses
     * @return array<string, int>
     */
    private function countByStatus(Builder $base, array $statuses): array
    {
        $result = [];
        foreach ($statuses as $status) {
            $result[$status] = (clone $base)->where('status', $status)->count();
        }

        return $result;
    }
}
