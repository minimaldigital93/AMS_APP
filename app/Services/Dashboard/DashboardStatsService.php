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
use App\Services\Billing\BillingCycleService;
use App\Services\Billing\BillingPeriod;
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
        private ?int $propertyId = null,
        private ?int $fiscalPeriodId = null,
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
            $this->countRentPaymentStatus($startDate, $referenceMonth, $referenceDate);

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

        // Units under maintenance are out of the rentable stock: they are not
        // vacant rooms the owner failed to rent, so they must not land in
        // 'available' nor in the 'total' that occupancy is measured against.
        // 'maintenance' reports them separately and 'total_all' keeps the raw
        // room count for anything that needs the physical inventory.
        $apartmentCounts = $this->countByStatus(
            $this->scopedApartmentQuery()->rentable(),
            ['available', 'occupied']
        );
        $apartmentCounts['maintenance'] = $this->scopedApartmentQuery()->underMaintenance()->count();
        $apartmentCounts['total'] = $this->scopedApartmentQuery()->rentable()->count();
        $apartmentCounts['total_all'] = $apartmentCounts['total'] + $apartmentCounts['maintenance'];

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
                // Denominator for the three donuts: the bills actually
                // classified for the reference month. Never the room count —
                // occupancy is today's state while these are the month's, so a
                // tenant who moved out mid-month (room now available), two
                // tenancies on one room in a month, or a room mothballed after
                // it was let all made the tile read 29/28.
                'bills_total' => $paidCount + $pendingCount + $overdueCount,
                'total_collected' => $this->collectedTotal($startDate, $endDate),
                'total_pending' => $totalPendingAmount,
            ],
            'revenue' => [
                'total_monthly' => round($monthlyTotalRevenue, 2),
                'total_monthly_rent' => $this->scopedApartmentQuery()->rentable()->where('status', 'occupied')->sum('monthly_rent'),
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
            'tenants_on_leave' => $this->tenantsOnLeaveCount($startDate, $endDate),
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
     * These three tiles link straight to the rent collection page's three
     * filter chips, so they must count the same way it does: **paid means the
     * whole bill is settled**, rent and charges both. A tenant whose rent is in
     * but whose utility charges are still owed — or whose meters haven't been
     * read yet in a month still running — is pending, not paid. Counting rent
     * alone made the tile disagree with the page it opens.
     *
     * Which is also why the due date and the rent owed come from
     * BillingCycleService and settings('billing_overdue_days') rather than
     * being re-derived here. This counted rent as due on each tenant's own
     * move-in day with no grace at all until 2026-09, so on an account with a
     * collection day set the tile and the page it opens disagreed in both
     * directions — a tenant inside the grace period read Overdue on the
     * dashboard and Pending on the page, and one past a collection day earlier
     * than their move-in day read Pending on the dashboard while the page
     * (correctly) called them overdue.
     *
     * @return array{0:int,1:int,2:int,3:float} [paid, pending, overdue, totalPending]
     */
    private function countRentPaymentStatus(Carbon $startDate, Carbon $referenceMonth, Carbon $referenceDate): array
    {
        $currentMonth = $referenceMonth->month;
        $currentYear = $referenceMonth->year;

        // Only the month still running keeps an unbilled charges side open —
        // the meters are read at the turn of the month. Any other month settles
        // a bill nobody ever charged for: accounts whose rent includes
        // utilities never write a charge row, and their closed months must not
        // sit in pending forever. Mirrors the rent collection page.
        $isRunningMonth = $referenceMonth->year === now()->year && $referenceMonth->month === now()->month;

        $paidCount = $pendingCount = $overdueCount = 0;
        $totalPendingAmount = 0.0;

        // Rent collection day and its grace, read exactly once. A null period
        // means the account has no collection day set, so the lease keeps
        // billing on its own move-in day as it always has.
        $cycles = app(BillingCycleService::class);
        $graceDays = $cycles->overdueDays();

        // A tenancy that only begins after the reference month has nothing
        // owed yet — it is the page's "upcoming" row, which lands in the
        // pending bucket for filtering but contributes no money to it.
        $referenceMonthEnd = $referenceMonth->copy()->endOfMonth();

        $activeRentals = $this->scopedRentalQuery()
            ->with([
                'payments' => fn ($pq) => $pq->where('payment_status', 'paid'),
                // The charges side of the reference month's bill.
                'utilities' => fn ($uq) => $uq->where('billing_month', $currentMonth)
                    ->where('billing_year', $currentYear),
                'apartment',
            ])
            // No upper bound on start_date, deliberately: an empty room whose
            // next tenancy begins later still gets a row on the collection page
            // these tiles link to, so it has to be represented here too or the
            // Pending chip lists a bill the Pending tile never counted.
            ->where(function ($q) use ($startDate) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            // A room is single-occupancy, so it gets exactly one bill per month
            // — the same rule the rent collection page these tiles link to
            // applies. A leaving tenant's move-out date can be any day of the
            // month and the room is freed for reassignment the moment the leave
            // is processed, so the outgoing and incoming tenancies overlap:
            // walking rentals counted that room twice and the tile read 29 of
            // 28. Rentals arrive newest-first; take the newest tenancy that has
            // begun by month end (the occupant), else the earliest future one
            // so an empty room awaiting its next tenant is still represented.
            ->groupBy('apartment_id')
            ->map(fn ($rentals) => $rentals->first(
                fn ($r) => ! $r->start_date || Carbon::parse($r->start_date)->lte($referenceMonthEnd)
            ) ?? $rentals->last())
            ->values();

        foreach ($activeRentals as $rental) {
            $start = $rental->start_date ? Carbon::parse($rental->start_date) : null;

            // Not begun by month end: the page's "upcoming" row. Pending for
            // counting (it is a bill row on the page's Pending chip), but no
            // rent and no charge is owed for a month the tenancy never touched.
            $notStartedYet = $start && $start->gt($referenceMonthEnd);

            $paidThisMonth = $rental->payments
                ->filter(fn ($p) => $p->payment_type === 'rent'
                    && Carbon::parse($p->paid_at)->month === $currentMonth
                    && Carbon::parse($p->paid_at)->year === $currentYear)
                ->isNotEmpty();

            // What the month actually owes: prorated to the collection day in
            // a move-in month, the full rent thereafter, and the raw rent when
            // the account has nominated no collection day.
            $period = $cycles->periodFor($rental, $currentMonth, $currentYear);
            $rentDue = $period ? $period->amount : (float) $rental->rent_amount;
            $dueDate = $this->rentDueDate($period, $start, $currentMonth, $currentYear);

            // Rent isn't late until the grace period has run out — the same
            // grace the printed contract promises (ប្រការ៥).
            $overdueAfter = $dueDate->copy()->addDays($graceDays);

            // The charges side. No rows is not the same as settled while the
            // month is still running — it means the meters haven't been read.
            $unpaidCharges = (float) $rental->utilities->where('paid_status', false)->sum('charge_amount');
            $chargesSettled = $rental->utilities->isEmpty()
                ? ! $isRunningMonth
                : $unpaidCharges <= 0;

            // Nothing is collectable on a tenancy that has not begun.
            if ($notStartedYet) {
                $unpaidCharges = 0.0;
                $rentDue = 0.0;
            }

            if ($paidThisMonth && $chargesSettled) {
                $paidCount++;
            } elseif ($paidThisMonth) {
                // Rent in, charges still open — not settled, so not paid.
                $pendingCount++;
                $totalPendingAmount += $unpaidCharges;
            } elseif (! $notStartedYet && $referenceDate->gt($overdueAfter)) {
                $overdueCount++;
                $totalPendingAmount += $rentDue + $unpaidCharges;
            } else {
                $pendingCount++;
                $totalPendingAmount += $rentDue + $unpaidCharges;
            }
        }

        return [$paidCount, $pendingCount, $overdueCount, $totalPendingAmount];
    }

    /**
     * The day the reference month's rent falls due, derived exactly as the rent
     * collection page derives it:
     *   - a collection day is set  → the period's own due date (the collection
     *     day, or for a move-in month the day the prorated period runs up to);
     *   - none set, move-in month  → one month after moving in;
     *   - none set, later month    → the move-in day-of-month, clamped to the
     *     month's length;
     *   - no move-in date at all   → the end of the month.
     */
    private function rentDueDate(?BillingPeriod $period, ?Carbon $start, int $month, int $year): Carbon
    {
        if ($period) {
            return $period->dueDate->copy()->endOfDay();
        }

        if (! $start) {
            return Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        }

        if ($start->month === $month && $start->year === $year) {
            return $start->copy()->addMonth()->endOfDay();
        }

        $dueDay = min($start->day, Carbon::create($year, $month, 1)->daysInMonth);

        return Carbon::create($year, $month, $dueDay)->endOfDay();
    }

    /**
     * Per-utility-type sum for the window. Includes any charge whose paid_at
     * falls in the range OR whose billing_month/year is within range — keeps
     * us aligned with the revenue/expense page's utility breakdown card.
     */
    private function utilityBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $query = Utilities::query()->forProperty($this->propertyId);

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
     * Units under maintenance are left out of the eager load entirely, so they
     * count towards neither the numerator nor the denominator — a floor whose
     * only empty room is under maintenance reads 100% occupied, not 50%. A
     * floor with nothing BUT maintenance rooms drops out like an empty floor.
     *
     * @return array{0: list<string>, 1: list<float>, 2: int}
     */
    private function floorOccupancy(): array
    {
        $floorsQuery = Floors::query()->forProperty($this->propertyId)->orderBy('id');
        if ($this->apartmentIds !== null) {
            $floorsQuery->with(['apartments' => fn ($q) => $q->rentable()->whereIn('id', $this->apartmentIds)]);
        } else {
            $floorsQuery->with(['apartments' => fn ($q) => $q->rentable()]);
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

        // Unscoped admin reports total floors_count (legacy Floors::count());
        // once narrowed to a property/supervisor scope, report only floors that
        // contain in-scope apartments.
        $scoped = $this->apartmentIds !== null || $this->propertyId !== null;
        $floorsCount = $scoped ? $floorsWithApartments : Floors::count();

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
            ->whereBetween('paid_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->forProperty($this->propertyId);

        if ($this->apartmentIds !== null) {
            $query->whereHas('rental', fn ($q) => $q->whereIn('apartment_id', $this->apartmentIds));
        }

        return (float) $query->sum('amount');
    }

    /**
     * Count tenants who left within the viewed window (by leave_date), so the
     * dashboard reports "this month's leaves" rather than every leave on record.
     */
    private function tenantsOnLeaveCount(Carbon $startDate, Carbon $endDate): int
    {
        $query = TenantLeave::query()
            ->forProperty($this->propertyId)
            ->whereBetween('leave_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);
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
            ->whereBetween('transaction_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->forProperty($this->propertyId);

        if ($this->fiscalPeriodId !== null) {
            $query->where('fiscal_period_id', $this->fiscalPeriodId);
        }

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
            ->whereBetween('transaction_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->forProperty($this->propertyId);

        if ($this->fiscalPeriodId !== null) {
            $query->where('fiscal_period_id', $this->fiscalPeriodId);
        }

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
        $query = Apartments::query()->forProperty($this->propertyId);
        if ($this->apartmentIds !== null) {
            $query->whereIn('id', $this->apartmentIds);
        }

        return $query;
    }

    private function scopedTenantQuery(): Builder
    {
        $query = Tenants::query()->forProperty($this->propertyId);
        if ($this->apartmentIds !== null) {
            $query->whereIn('apartment_id', $this->apartmentIds);
        }

        return $query;
    }

    private function scopedRentalQuery(): Builder
    {
        $query = Rentals::query()->forProperty($this->propertyId);
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
