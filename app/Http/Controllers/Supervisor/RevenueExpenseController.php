<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Concerns\HandlesKhqrCheckout;
use App\Http\Controllers\Concerns\HasFiscalPeriodScope;
use App\Http\Controllers\Concerns\ScopesToSupervisorProperties;
use App\Http\Controllers\Controller;
use App\Http\Requests\RevenueExpense\AddTenantChargeRequest;
use App\Http\Requests\RevenueExpense\CheckoutTenantRequest;
use App\Http\Requests\RevenueExpense\ProcessMonthlyBillsRequest;
use App\Http\Requests\RevenueExpense\RecordBulkIncomeRequest;
use App\Http\Requests\RevenueExpense\RecordIncomeRequest;
use App\Http\Requests\RevenueExpense\StoreBusinessExpenseRequest;
use App\Http\Requests\RevenueExpense\StoreFixedExpenseRequest;
use App\Http\Requests\RevenueExpense\StoreOtherExpenseRequest;
use App\Http\Requests\RevenueExpense\StoreUtilityExpenseRequest;
use App\Models\Accounts;
use App\Models\ApartmentFixedExpense;
use App\Models\Apartments;
use App\Models\BusinessExpense;
use App\Models\FiscalPeriods;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\TenantLeave;
use App\Models\Utilities;
use App\Services\RevenueExpense\BreakEvenService;
use App\Services\RevenueExpense\ExpenseRecordingService;
use App\Services\RevenueExpense\IncomeRecordingService;
use App\Services\RevenueExpense\MonthlyBillingService;
use App\Services\RevenueExpense\RevenueExpenseQueryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class RevenueExpenseController extends Controller
{
    use HandlesKhqrCheckout;
    use HasFiscalPeriodScope;
    use ScopesToSupervisorProperties;

    /**
     * Supervisors read from the admin's fiscal periods (not their own).
     */
    protected function fiscalPeriodsQuery(): Builder
    {
        return FiscalPeriods::whereHas('user', fn ($q) => $q->role('admin'));
    }

    /**
     * Restrict the revenue/expense apartment scope to the supervisor's assigned
     * properties (overrides the all-apartments default in HasFiscalPeriodScope).
     */
    protected function scopeApartments(): Builder
    {
        // Narrow the supervisor's assigned rooms to the globally selected property.
        return $this->supervisorVisibleApartments()->forActiveProperty();
    }

    /** The globally selected property, or null for the consolidated view. */
    protected function activePropertyId(): ?int
    {
        return current_property_id();
    }

    /**
     * Property ids that bound a supervisor's consolidated ("All properties")
     * ledger view — their assigned buildings, so the ledger never spills past
     * the properties they manage. Null for admins previewing the supervisor
     * panel (the account scope already isolates them to the whole account).
     *
     * @return array<int>|null
     */
    protected function ledgerPropertyIds(): ?array
    {
        return $this->seesWholeAccount() ? null : $this->supervisorPropertyIds()->all();
    }

    /**
     * Apply the supervisor's property isolation to an Accounts query: narrow to
     * the active property when one is selected, otherwise to their assigned set.
     */
    private function scopeAccountsToProperty(Builder $query): Builder
    {
        $activeId = $this->activePropertyId();
        if ($activeId !== null) {
            return $query->forProperty($activeId);
        }

        $ids = $this->ledgerPropertyIds();

        return $ids === null ? $query : $query->forProperties($ids);
    }

    protected function khqrRoutePrefix(): string
    {
        return 'supervisor.revenue_expense';
    }

    /**
     * Property-scope guard: a supervisor may only act on a rental that lives
     * under one of their assigned properties. Admin/superadmin previewing the
     * panel see the whole account (no-op via seesWholeAccount()).
     */
    private function authorizeRentalAccess(Rentals $rental): void
    {
        $rental->loadMissing('apartment.floor');
        if ($rental->apartment) {
            $this->authorizeSupervisorApartment($rental->apartment);
        }
    }

    /** KHQR trait hook — route checkout through the supervisor property guard. */
    protected function authorizeRentalForCheckout(Rentals $rental): void
    {
        $this->authorizeRentalAccess($rental);
    }

    /**
     * Keep only the apartment ids the supervisor may act on. Admin/superadmin
     * previewing the panel pass through unfiltered.
     *
     * @param  array<int|string>  $apartmentIds
     * @return array<int>
     */
    private function filterToVisibleApartmentIds(array $apartmentIds): array
    {
        $ids = array_map('intval', $apartmentIds);

        if ($this->seesWholeAccount()) {
            return array_values($ids);
        }

        return array_values(array_intersect($ids, $this->supervisorApartmentIds()));
    }

    /**
     * Keep only the bill rows whose rental lives under one of the supervisor's
     * assigned properties. Admin/superadmin previewing the panel pass through.
     *
     * @param  array<int, array<string, mixed>>  $bills
     * @return array<int, array<string, mixed>>
     */
    private function filterBillsToVisibleRentals(array $bills): array
    {
        if ($this->seesWholeAccount()) {
            return $bills;
        }

        $visibleRentalIds = Rentals::whereIn('apartment_id', $this->supervisorApartmentIds())
            ->pluck('id')
            ->all();

        return array_values(array_filter(
            $bills,
            fn ($bill) => in_array((int) ($bill['rental_id'] ?? 0), $visibleRentalIds, true),
        ));
    }

    /**
     * Supervisors write/read ledger rows under the admin's user_id, resolved
     * from the active fiscal period.
     */
    protected function ledgerUserId(): ?int
    {
        return $this->getActiveFiscalPeriod()?->user_id;
    }

    /**
     * @deprecated Use ledgerUserId() — kept for any remaining inline references.
     */
    private function getAdminUserId(): ?int
    {
        return $this->ledgerUserId();
    }

    private function queryService(): RevenueExpenseQueryService
    {
        return new RevenueExpenseQueryService(
            userId: $this->ledgerUserId(),
            period: $this->getActiveFiscalPeriod(),
            apartmentsScope: $this->scopeApartments(),
            propertyId: $this->activePropertyId(),
            propertyIds: $this->ledgerPropertyIds(),
        );
    }

    private function breakEvenService(): BreakEvenService
    {
        return new BreakEvenService(
            queryService: $this->queryService(),
            userId: $this->ledgerUserId(),
            period: $this->getActiveFiscalPeriod(),
            apartmentsScope: $this->scopeApartments(),
            propertyId: $this->activePropertyId(),
            propertyIds: $this->ledgerPropertyIds(),
        );
    }

    private function expenseService(?FiscalPeriods $period = null): ExpenseRecordingService
    {
        return new ExpenseRecordingService(
            userId: $this->ledgerUserId(),
            period: $period ?? $this->getActiveFiscalPeriod(),
            propertyId: $this->activePropertyId(),
        );
    }

    private function incomeService(FiscalPeriods $period): IncomeRecordingService
    {
        return new IncomeRecordingService(
            userId: $this->ledgerUserId(),
            period: $period,
            propertyId: $this->activePropertyId(),
        );
    }

    private function billingService(FiscalPeriods $period): MonthlyBillingService
    {
        return new MonthlyBillingService(
            userId: $this->ledgerUserId(),
            period: $period,
            propertyId: $this->activePropertyId(),
        );
    }

    public function index()
    {
        // Allow switching fiscal periods via ?period=ID
        $activePeriod = $this->resolveActivePeriod(request('period') ? (int) request('period') : null);

        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required_track'));
        }

        // Monthly filter: ?month=3&year=2026
        $filterMonth = request('month') ? (int) request('month') : null;
        $filterYear = request('year') ? (int) request('year') : null;

        $periodMonths = $this->buildPeriodMonths($activePeriod);

        // When landing on the page without an explicit month filter (e.g. clicking
        // "Revenue & Expense" in the sidebar), default the calendar to the current
        // month — but only if it falls within the active period, so a closed/past
        // period still opens on its whole range instead of clamping oddly.
        if (! $filterMonth && ! $filterYear) {
            $nowMonth = now()->month;
            $nowYear = now()->year;
            $currentInPeriod = collect($periodMonths)->contains(
                fn ($pm) => $pm['month'] === $nowMonth && $pm['year'] === $nowYear
            );

            if ($currentInPeriod) {
                $filterMonth = $nowMonth;
                $filterYear = $nowYear;
            }
        }

        // Get date range (clamped to fiscal period bounds)
        $dateRange = $this->getFilteredDateRange($activePeriod, $filterMonth, $filterYear);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // ===== DASHBOARD DATA =====
        $revenueExpenseData = $this->getRevenueExpenseData($startDate, $endDate);
        $revenueExpenseData['activePeriod'] = $activePeriod;
        $revenueExpenseData['filterMonth'] = $filterMonth;
        $revenueExpenseData['filterYear'] = $filterYear;
        $revenueExpenseData['periodMonths'] = $periodMonths;

        $revenueExpenseData['fiscalPeriods'] = $this->getAllFiscalPeriods();

        // Month/year used by billing + utilities queries (respect filter if provided).
        $currentMonth = $filterMonth ?? now()->month;
        $currentYear = $filterYear ?? now()->year;

        // ONE apartment fetch shared by every dashboard section. The income and
        // "Generate Bills" sides want only ACTIVE rentals (open or not-yet-ended)
        // while the expense and fixed-expense sides want ALL rentals — so we load
        // all rentals once with the superset of nested constraints, then derive
        // the active-only view in PHP. This replaces what used to be two separate
        // (and nearly identical) apartments+rentals+utilities queries.
        $periodOpen = Carbon::parse($activePeriod->opening_date);
        $periodClose = Carbon::parse($activePeriod->closing_date);

        $apartments = $this->scopeApartments()
            ->with(['floor', 'activeFixedExpenses', 'fixedExpenses', 'rentals' => function ($q) use ($activePeriod, $periodOpen, $periodClose, $currentMonth, $currentYear) {
                $q->orderBy('start_date', 'desc')
                    ->with(['tenant', 'payments' => function ($pq) use ($activePeriod) {
                        $pq->where('payment_status', 'paid')
                            ->whereHas('accounts', function ($aq) use ($activePeriod) {
                                $aq->where('fiscal_period_id', $activePeriod->id);
                            });
                    }, 'utilities' => function ($uq) use ($periodOpen, $periodClose, $currentMonth, $currentYear) {
                        // Superset of the expense side (whole fiscal period) and
                        // the income/bills side (current billing month). Wrapped
                        // in a closure so the OR group can't bleed past the
                        // eager-load foreign-key constraint.
                        $uq->where(function ($w) use ($periodOpen, $periodClose, $currentMonth, $currentYear) {
                            $w->whereBetween('paid_at', [$periodOpen->copy()->startOfDay(), $periodClose->copy()->endOfDay()])
                                ->orWhere(function ($q2) use ($periodOpen, $periodClose) {
                                    $q2->where('billing_year', '>=', $periodOpen->year)
                                        ->where('billing_year', '<=', $periodClose->year);
                                })
                                ->orWhere(function ($q3) use ($currentMonth, $currentYear) {
                                    $q3->where('billing_month', $currentMonth)
                                        ->where('billing_year', $currentYear);
                                });
                        });
                    }]);
            }])
            ->get();

        // Income/bills view: active rentals only (open or not yet ended), matching
        // the original per-section query constraint. Cloned so the active filter
        // doesn't disturb the full-rental set the expense sections rely on.
        $incomeApartments = $apartments->map(function ($apartment) {
            $clone = clone $apartment;
            $clone->setRelation('rentals', $apartment->rentals->filter(function ($rental) {
                return is_null($rental->end_date) || $rental->end_date >= now();
            })->values());

            return $clone;
        });

        // Expense + fixed-expense sections use the full set (all rentals).
        $expenseApartments = $apartments;

        $totalApartments = $incomeApartments->count();
        $occupiedCount = $incomeApartments->where('status', 'occupied')->count();
        $revenueExpenseData['totalApartments'] = $totalApartments;
        $revenueExpenseData['occupiedCount'] = $occupiedCount;
        $revenueExpenseData['vacantCount'] = $totalApartments - $occupiedCount;
        $revenueExpenseData['occupancyRate'] = $totalApartments > 0
            ? round(($occupiedCount / $totalApartments) * 100, 1)
            : 0;

        // Expected monthly rent: sum of rents for rentals active during the selected date range
        $rentalQuery = Rentals::whereHas('tenant');

        if ($startDate && $endDate) {
            // include rentals that overlap the selected month/date range
            $rentalQuery->where(function ($q) use ($startDate, $endDate) {
                $q->whereNull('end_date')->where('start_date', '<=', $endDate)
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $endDate)
                            ->where(function ($q3) use ($startDate) {
                                $q3->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
                            });
                    });
            });
        } else {
            $rentalQuery->active();
        }

        // Compute expected monthly rent as the sum of prorated rents for rentals overlapping the selected range
        $expectedMonthlyRent = 0;
        $rentalsForExpect = $rentalQuery->get();
        $expectRangeStart = Carbon::parse($startDate ?: now()->startOfMonth())->startOfDay();
        $expectRangeEnd = Carbon::parse($endDate ?: now()->endOfMonth())->endOfDay();
        foreach ($rentalsForExpect as $rental) {
            $rentStart = Carbon::parse($rental->start_date)->startOfDay();
            $rentEnd = $rental->end_date ? Carbon::parse($rental->end_date)->endOfDay() : null;
            $ovStart = $rentStart->greaterThan($expectRangeStart) ? $rentStart : $expectRangeStart;
            $ovEnd = $rentEnd ? ($rentEnd->lessThan($expectRangeEnd) ? $rentEnd : $expectRangeEnd) : $expectRangeEnd;
            if ($ovStart->lte($ovEnd)) {
                $overlapDays = $ovStart->diffInDays($ovEnd) + 1;
                $daysInRange = $expectRangeStart->diffInDays($expectRangeEnd) + 1;
                $proration = $daysInRange > 0 ? ($overlapDays / $daysInRange) : 0;
                $expectedMonthlyRent += round($rental->rent_amount * $proration, 2);
            }
        }
        $revenueExpenseData['expectedMonthlyRent'] = $expectedMonthlyRent;

        // ===== RECORD INCOME DATA =====
        // $incomeApartments was eager-loaded above (it feeds both this section
        // and the "Generate Bills" section).
        $apartmentSummary = [];
        $totalRentExpected = 0;
        $totalRentCollected = 0;
        // Use the selected date range (clamped to fiscal period) for "this month" calculations.
        $rangeStart = Carbon::parse($startDate ?: now()->startOfMonth())->startOfDay();
        $rangeEnd = Carbon::parse($endDate ?: now()->endOfMonth())->endOfDay();
        // Invariant across all rentals — compute once, not per row.
        $daysInRange = $rangeStart->diffInDays($rangeEnd) + 1;

        foreach ($incomeApartments as $apartment) {
            foreach ($apartment->rentals as $rental) {
                // Sum payments that occurred inside the selected date range
                $monthPayments = $rental->payments->filter(function ($p) use ($rangeStart, $rangeEnd) {
                    return Carbon::parse($p->paid_at)->between($rangeStart, $rangeEnd);
                });
                $collected = $monthPayments->sum('amount');
                $lateFees = $monthPayments->sum('late_fee');
                $totalRentCollected += $collected + $lateFees;

                // Calculate prorated rent due for the selected date range based on rental start/end
                $rentPeriodStart = Carbon::parse($rental->start_date)->startOfDay();
                $rentPeriodEnd = $rental->end_date ? Carbon::parse($rental->end_date)->endOfDay() : null;

                $overlapStart = $rentPeriodStart->greaterThan($rangeStart) ? $rentPeriodStart : $rangeStart;
                $overlapEnd = $rentPeriodEnd ? ($rentPeriodEnd->lessThan($rangeEnd) ? $rentPeriodEnd : $rangeEnd) : $rangeEnd;

                $overlapDays = 0;
                if ($overlapStart->lte($overlapEnd)) {
                    // inclusive days
                    $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
                }

                $proration = $daysInRange > 0 ? ($overlapDays / $daysInRange) : 0;
                $proratedRent = round($rental->rent_amount * $proration, 2);

                $totalRentExpected += $proratedRent;

                // Determine if tenant has paid at least the prorated amount this range
                $paidThisMonth = ($collected + $lateFees) >= $proratedRent && $proratedRent > 0;

                // Last payment date within range
                $lastPaymentDate = $monthPayments->isNotEmpty() ? Carbon::parse($monthPayments->max('paid_at'))->toDateString() : null;

                // occupancy end date for the overlap
                $occupancyEndDate = ($overlapStart->lte($overlapEnd)) ? $overlapEnd->toDateString() : null;

                // days left from today to occupancy end (0 if past)
                $daysLeft = null;
                if ($occupancyEndDate) {
                    $diff = Carbon::parse($occupancyEndDate)->startOfDay()->diffInDays(Carbon::now()->startOfDay());
                    $daysLeft = $diff > 0 ? $diff : 0;
                }

                $apartmentSummary[] = [
                    'apartment' => $apartment,
                    'rental' => $rental,
                    'monthly_rent' => $rental->rent_amount,
                    'prorated_rent' => $proratedRent,
                    'collected' => $collected,
                    'late_fees' => $lateFees,
                    'total_collected' => $collected + $lateFees,
                    'payment_count' => $rental->payments->count(),
                    'paid_this_month' => $paidThisMonth,
                    'occupancy_percent' => round($proration * 100, 1),
                    'last_payment_date' => $lastPaymentDate,
                    'occupancy_end_date' => $occupancyEndDate,
                    'days_left' => $daysLeft,
                ];
            }
        }

        // Paginate apartment summary (array) server-side
        $apartmentPerPage = 20;
        $apartmentPage = (int) request('apartment_page', 1);
        $apartmentTotal = count($apartmentSummary);
        $apartmentSlice = array_slice($apartmentSummary, ($apartmentPage - 1) * $apartmentPerPage, $apartmentPerPage);
        $apartmentSummary = new LengthAwarePaginator($apartmentSlice, $apartmentTotal, $apartmentPerPage, $apartmentPage, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        // Recent income (no pagination) — scoped to the supervisor's properties.
        $recentIncome = $this->scopeAccountsToProperty(
            Accounts::income()->forUser($this->getAdminUserId())
        )
            ->forPeriod($activePeriod->id)
            ->orderBy('transaction_date', 'desc')
            ->take(10)
            ->get();

        $revenueExpenseData['apartments'] = $incomeApartments;
        $revenueExpenseData['apartmentSummary'] = $apartmentSummary;
        $revenueExpenseData['recentIncome'] = $recentIncome;
        $revenueExpenseData['totalRentExpected'] = $totalRentExpected;
        $revenueExpenseData['totalRentCollected'] = $totalRentCollected;
        $revenueExpenseData['expectedTenantCount'] = count($apartmentSummary);
        $revenueExpenseData['paidTenantCount'] = collect($apartmentSummary)->where('paid_this_month', true)->count();

        // ===== RECORD EXPENSE DATA =====
        // $expenseApartments was loaded once at the top of this method (the full
        // apartment set with all rentals + period-scoped utilities + fixed
        // expenses) and also feeds the "Fixed Expenses" section below.

        $apartmentExpenses = [];
        $totalExpensesAmount = 0;

        foreach ($expenseApartments as $apartment) {
            $aptExpense = [
                'apartment' => $apartment,
                'electricity' => 0, 'water' => 0, 'internet' => 0, 'parking' => 0,
                'total' => 0,
                'has_active_rental' => $apartment->rentals->isNotEmpty(),
            ];

            foreach ($apartment->rentals as $rental) {
                foreach ($rental->utilities as $utility) {
                    $type = $utility->utility_type;
                    if (isset($aptExpense[$type])) {
                        $aptExpense[$type] += $utility->charge_amount;
                    }
                    $aptExpense['total'] += $utility->charge_amount;
                }
            }

            $totalExpensesAmount += $aptExpense['total'];
            $apartmentExpenses[] = $aptExpense;
        }

        $recentExpenses = $this->scopeAccountsToProperty(
            Accounts::expense()->forUser($this->getAdminUserId())
        )
            ->forPeriod($activePeriod->id)
            ->orderBy('transaction_date', 'desc')
            ->take(10)
            ->get();

        $revenueExpenseData['expenseApartments'] = $expenseApartments;
        $revenueExpenseData['apartmentExpenses'] = $apartmentExpenses;
        $revenueExpenseData['recentExpenses'] = $recentExpenses;
        $revenueExpenseData['utilityTypes'] = [
            'electricity' => 'Electricity',
            'water' => 'Water',
            'internet' => 'Internet',
            'parking' => 'Parking',
        ];
        $revenueExpenseData['totalExpensesAmount'] = $totalExpensesAmount;

        // ===== FIXED EXPENSES DATA =====
        // Same apartment + all-rentals shape as the expense collection above.
        $revenueExpenseData['fixedApartments'] = $expenseApartments;

        // ===== GENERATE BILLS DATA =====
        // Reuses $incomeApartments — same active-rental constraint, and it
        // already carries activeFixedExpenses + current-month utilities.
        $billSummary = [];
        $totalMonthlyExpenses = 0;

        foreach ($incomeApartments as $apartment) {
            if ($apartment->rentals->isEmpty()) {
                continue;
            }

            foreach ($apartment->rentals as $rental) {
                $fixedExpenses = $apartment->activeFixedExpenses;
                $alreadyBilled = $rental->utilities
                    ->where('billing_month', $currentMonth)
                    ->where('billing_year', $currentYear);
                $billedTypes = $alreadyBilled->pluck('utility_type')->toArray();

                $expenseItems = [];
                $totalForApt = 0;

                foreach ($fixedExpenses as $fe) {
                    $isBilled = in_array($fe->expense_type, $billedTypes);
                    $expenseItems[] = [
                        'id' => $fe->id,
                        'name' => $fe->expense_name,
                        'type' => $fe->expense_type,
                        'amount' => $fe->amount,
                        'is_billed' => $isBilled,
                    ];
                    $totalForApt += $fe->amount;
                }

                $totalMonthlyExpenses += $totalForApt;

                $billSummary[] = [
                    'apartment' => $apartment,
                    'rental' => $rental,
                    'tenant_name' => $rental->tenant->name ?? 'N/A',
                    'monthly_rent' => $rental->rent_amount,
                    'fixed_expenses' => $expenseItems,
                    'total_fixed' => $totalForApt,
                    'total_bill' => $rental->rent_amount + $totalForApt,
                    'has_unbilled' => collect($expenseItems)->contains('is_billed', false),
                ];
            }
        }

        $revenueExpenseData['billSummary'] = $billSummary;
        $revenueExpenseData['totalMonthlyExpenses'] = $totalMonthlyExpenses;
        $revenueExpenseData['currentMonth'] = $currentMonth;
        $revenueExpenseData['currentYear'] = $currentYear;

        // ===== BREAK-EVEN DATA =====
        // Honor the dashboard month/year filter so the break-even tab matches
        // the rest of the dashboard (and the standalone /break_even page).
        // When no filter is set, calculateBreakEvenPoint() falls back to now().
        $revenueExpenseData = array_merge($revenueExpenseData, $this->calculateBreakEvenPoint($filterMonth, $filterYear));

        return view('supervisor.revenue_expense.index', $revenueExpenseData);
    }

    /**
     * Display break-even point analysis scoped to active fiscal period.
     */
    public function breakEvenPoint(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        $periodStart = Carbon::parse($activePeriod->opening_date)->startOfMonth();
        $periodEnd = Carbon::parse($activePeriod->closing_date)->endOfMonth();

        $requested = Carbon::create(
            (int) $request->input('year', now()->year),
            (int) $request->input('month', now()->month),
            1
        )->startOfMonth();

        if ($requested->lt($periodStart)) {
            $requested = $periodStart->copy();
        }
        if ($requested->gt($periodEnd)) {
            $requested = $periodEnd->copy()->startOfMonth();
        }

        $selectedMonth = $requested->month;
        $selectedYear = $requested->year;

        $prev = $requested->copy()->subMonth();
        $next = $requested->copy()->addMonth();
        $hasPrev = $prev->gte($periodStart);
        $hasNext = $next->lte($periodEnd);

        $data = $this->calculateBreakEvenPoint($selectedMonth, $selectedYear);
        $data['health'] = $this->breakEvenService()->getBusinessHealth($data, $selectedMonth, $selectedYear);
        $data['activePeriod'] = $activePeriod;
        $data['selectedMonth'] = $selectedMonth;
        $data['selectedYear'] = $selectedYear;
        $data['selectedDate'] = $requested;
        $data['prevMonth'] = $hasPrev ? $prev->month : null;
        $data['prevYear'] = $hasPrev ? $prev->year : null;
        $data['nextMonth'] = $hasNext ? $next->month : null;
        $data['nextYear'] = $hasNext ? $next->year : null;
        $data['hasPrev'] = $hasPrev;
        $data['hasNext'] = $hasNext;

        return view('supervisor.revenue_expense.break_event', $data);
    }

    private function getRevenueExpenseData($startDate = null, $endDate = null)
    {
        return $this->queryService()->getRevenueExpenseData($startDate, $endDate);
    }

    private function calculatePerApartmentData($startDate = null, $endDate = null)
    {
        return $this->queryService()->calculatePerApartmentData($startDate, $endDate);
    }

    private function calculateBreakEvenPoint(?int $month = null, ?int $year = null)
    {
        return $this->breakEvenService()->calculate($month, $year);
    }

    private function getBusinessExpenseBreakdown(?int $month = null, ?int $year = null): array
    {
        return $this->breakEvenService()->getBusinessExpenseBreakdown($month, $year);
    }

    private function getVariableCostBreakdown(?int $month = null, ?int $year = null): array
    {
        return $this->breakEvenService()->getVariableCostBreakdown($month, $year);
    }

    private function calculateBusinessExpenses(?FiscalPeriods $period = null, ?int $month = null, ?int $year = null)
    {
        return $this->breakEvenService()->calculateBusinessExpenses($month, $year);
    }

    private function calculateVariableCostPerUnit(?FiscalPeriods $period = null, ?int $month = null, ?int $year = null)
    {
        return $this->breakEvenService()->calculateVariableCostPerUnit($month, $year);
    }

    /**
     * Show record income form — tenant billing management.
     * Auto-shows all tenants with due dates, charges, and payment status.
     */
    public function recordIncome(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        // Accept month/year from query params, default to current
        $currentMonth = (int) $request->input('month', now()->month);
        $currentYear = (int) $request->input('year', now()->year);

        // Build a Carbon date for the selected month
        $selectedDate = Carbon::create($currentYear, $currentMonth, 1);

        // Calculate previous and next month
        $prevDate = $selectedDate->copy()->subMonth();
        $nextDate = $selectedDate->copy()->addMonth();

        // Determine if we're viewing the current (real) month, a past month, or a future month
        $isCurrentMonth = ($currentMonth === now()->month && $currentYear === now()->year);
        $isFutureMonth = $selectedDate->copy()->startOfMonth()->gt(now()->copy()->startOfMonth());
        $isPastMonth = ! $isCurrentMonth && ! $isFutureMonth;

        // Reference date for overdue comparison:
        // - Current month: use now()
        // - Past month: use end of that month
        // - Future month: use start of that month (nothing should be overdue yet)
        if ($isCurrentMonth) {
            $referenceNow = now();
        } elseif ($isPastMonth) {
            $referenceNow = $selectedDate->copy()->endOfMonth();
        } else {
            // Future month — use start of month so nothing is overdue
            $referenceNow = $selectedDate->copy()->startOfMonth();
        }

        // Get apartments with active rentals, eager load everything needed for billing
        $apartments = $this->scopeApartments()
            ->when($request->filled('floor'), fn ($q) => $q->where('floor_id', $request->input('floor')))
            ->with(['floor', 'activeFixedExpenses', 'rentals' => function ($q) use ($activePeriod, $currentMonth, $currentYear) {
                $q->where(function ($sq) {
                    $sq->whereNull('end_date')->orWhere('end_date', '>=', now());
                })
                    ->orderBy('start_date', 'desc')
                    ->with(['tenant', 'payments' => function ($pq) use ($activePeriod, $currentMonth, $currentYear) {
                        // Count a payment as "this month's" if it lands inside the
                        // active fiscal period window OR within the month being
                        // viewed. The month fallback guards against an active period
                        // whose window doesn't cover the selected month (e.g. a stale
                        // or short closing_date): without it a confirmed payment is
                        // saved but never marked paid, so the bill can be "confirmed"
                        // again and again. The receipt (printTenantBill) already uses
                        // a plain month/year match — this keeps the list consistent.
                        $selMonthStart = Carbon::create($currentYear, $currentMonth, 1)->startOfMonth();
                        $selMonthEnd = $selMonthStart->copy()->endOfMonth();
                        $pq->where('payment_status', 'paid')
                            ->where(function ($w) use ($activePeriod, $selMonthStart, $selMonthEnd) {
                                $w->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date])
                                    ->orWhereBetween('paid_at', [$selMonthStart, $selMonthEnd]);
                            });
                    }, 'utilities' => function ($uq) use ($currentMonth, $currentYear) {
                        $uq->where('billing_month', $currentMonth)
                            ->where('billing_year', $currentYear);
                    }]);
            }])
            ->get();

        // Floors for the sort/filter dropdown (only floors that have apartments)
        $floors = $this->supervisorVisibleFloors()->whereHas('apartments')->orderBy('id', 'asc')->get();

        // Build tenant billing data
        $tenantBills = [];
        $totalRentExpected = 0;
        $totalRentCollected = 0;
        $totalPending = 0;
        $overdueCount = 0;
        $paidCount = 0;
        $pendingCount = 0;

        foreach ($apartments as $apartment) {
            foreach ($apartment->rentals as $rental) {
                $collected = $rental->payments->sum('amount');
                $lateFees = $rental->payments->sum('late_fee');
                $totalRentCollected += $collected + $lateFees;
                $totalRentExpected += $rental->rent_amount;

                // Check if rent already paid this month
                $paidThisMonth = $rental->payments
                    ->filter(function ($p) use ($currentMonth, $currentYear) {
                        return $p->payment_type === 'rent'
                            && Carbon::parse($p->paid_at)->month === $currentMonth
                            && Carbon::parse($p->paid_at)->year === $currentYear;
                    })->isNotEmpty();

                // Determine if this is the tenant's first month in the selected period
                $isFirstMonth = $rental->start_date
                    && $rental->start_date->month === $currentMonth
                    && $rental->start_date->year === $currentYear;

                // Calculate due date based on tenant start date:
                // - For regular tenants: due on the same day-of-month as their `start_date` within the selected month.
                // - For tenants whose tenancy began in the selected month: due = start_date + 1 month (count 1 month).
                if ($rental->start_date) {
                    $startDay = $rental->start_date->day;
                    if ($isFirstMonth) {
                        $dueDate = Carbon::parse($rental->start_date)->copy()->addMonth()->endOfDay();
                        $dueDay = $dueDate->day;
                    } else {
                        $daysInMonth = Carbon::create($currentYear, $currentMonth)->daysInMonth;
                        $dueDay = min($startDay, $daysInMonth);
                        $dueDate = Carbon::create($currentYear, $currentMonth, $dueDay)->endOfDay();
                    }
                } else {
                    // Fallback: end of selected month
                    $dueDay = Carbon::create($currentYear, $currentMonth)->daysInMonth;
                    $dueDate = Carbon::create($currentYear, $currentMonth, $dueDay)->endOfDay();
                }

                // Determine status
                if ($paidThisMonth) {
                    $status = 'paid';
                    $paidCount++;
                } elseif ($referenceNow->gt($dueDate)) {
                    $status = 'overdue';
                    $overdueCount++;
                } else {
                    $status = 'pending';
                    $pendingCount++;
                }

                // Calculate extra charges (utilities for current month)
                $utilityCharges = $rental->utilities ?? collect();
                $totalUtilities = $utilityCharges->sum('charge_amount');
                $totalUtilityOnly = $utilityCharges->whereIn('utility_type', ['electricity', 'water'])->sum('charge_amount');
                $totalOtherCharges = $utilityCharges->whereIn('utility_type', ['internet', 'parking', 'trash', 'other'])->sum('charge_amount');

                // Fixed expenses for the apartment
                $fixedExpenses = $apartment->activeFixedExpenses ?? collect();
                $totalFixed = $fixedExpenses->sum('amount');

                // Total bill = rent + utilities + fixed expenses + late fee
                $lateFeeAmount = (! $paidThisMonth && $referenceNow->gt($dueDate)) ? ($rental->payments->isEmpty() ? 0 : $lateFees) : 0;
                $totalBill = $rental->rent_amount + $totalUtilities + $totalFixed;

                if (! $paidThisMonth) {
                    $totalPending += $totalBill;
                }

                $tenantBills[] = [
                    'apartment' => $apartment,
                    'rental' => $rental,
                    'tenant' => $rental->tenant,
                    'monthly_rent' => $rental->rent_amount,
                    'due_date' => $dueDate,
                    'due_day' => $dueDay,
                    'is_first_month' => $isFirstMonth,
                    'status' => $status,
                    'paid_this_month' => $paidThisMonth,
                    'utilities' => $utilityCharges,
                    'total_utilities' => $totalUtilities,
                    'total_utility_only' => $totalUtilityOnly,
                    'total_other_charges' => $totalOtherCharges,
                    'fixed_expenses' => $fixedExpenses,
                    'total_fixed' => $totalFixed,
                    'total_bill' => $totalBill,
                    'collected' => $collected,
                    'late_fees' => $lateFees,
                    'total_collected' => $collected + $lateFees,
                    'payment_count' => $rental->payments->count(),
                ];
            }
        }

        // Sort: by floor number, then apartment number within each floor
        usort($tenantBills, function ($a, $b) {
            $floorA = $a['apartment']->floor->floor_number ?? 0;
            $floorB = $b['apartment']->floor->floor_number ?? 0;
            if ($floorA !== $floorB) {
                return $floorA <=> $floorB;
            }

            return ($a['apartment']->apartment_number ?? '') <=> ($b['apartment']->apartment_number ?? '');
        });

        // Also keep the old $apartmentSummary for backward compat
        $apartmentSummary = $tenantBills;

        // Keep a full copy for totals and counts, then paginate the tenant bills (10 per page)
        $tenantBillsAll = $tenantBills;
        $perPage = 10;
        $page = (int) request()->get('page', 1);
        $offset = ($page - 1) * $perPage;
        $tenantBills = new LengthAwarePaginator(
            array_slice($tenantBillsAll, $offset, $perPage),
            count($tenantBillsAll),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        // Recent income records for this fiscal period — scoped to the supervisor's properties.
        $recentIncome = $this->scopeAccountsToProperty(
            Accounts::income()->forUser($this->getAdminUserId())
        )
            ->forPeriod($activePeriod->id)
            ->orderBy('transaction_date', 'desc')
            ->take(10)
            ->get();

        return view('supervisor.revenue_expense.record_income', compact(
            'activePeriod', 'apartments', 'apartmentSummary', 'tenantBills', 'tenantBillsAll', 'recentIncome',
            'totalRentExpected', 'totalRentCollected', 'totalPending',
            'overdueCount', 'paidCount', 'pendingCount',
            'selectedDate', 'prevDate', 'nextDate',
            'isCurrentMonth', 'isFutureMonth', 'isPastMonth',
            'currentMonth', 'currentYear', 'floors'
        ));
    }

    /**
     * Add a charge (utility/expense) to a tenant's current month bill.
     */
    public function addTenantCharge(AddTenantChargeRequest $request)
    {
        $validated = $request->validated();
        $rental = Rentals::with('tenant')->findOrFail($validated['rental_id']);
        $this->authorizeRentalAccess($rental);
        $period = $this->getActiveFiscalPeriod();
        if ($period) {
            $this->incomeService($period)->addTenantCharge($rental, $validated);
        }

        $successMsg = __('messages.flash_charge_added', [
            'type' => ucfirst($validated['charge_type']),
            'amount' => number_format($validated['charge_amount'], 2),
            'name' => $rental->tenant->name ?? __('messages.tenant'),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $successMsg]);
        }

        return redirect()->back()->with('success', $successMsg);
    }

    public function removeTenantCharge($chargeId)
    {
        $charge = Utilities::with('rental.apartment.floor')->findOrFail($chargeId);
        if ($charge->rental) {
            $this->authorizeRentalAccess($charge->rental);
        }
        $period = $this->getActiveFiscalPeriod();

        $removed = $period
            ? $this->incomeService($period)->removeTenantCharge($charge)
            : false;

        if (! $removed) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'Cannot remove a paid charge.'], 422);
            }

            return redirect()->back()->with('error', __('messages.flash_charge_already_paid'));
        }

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', __('messages.flash_charge_removed'));
    }

    public function clearTenantCharges($rentalId)
    {
        $rental = Rentals::findOrFail($rentalId);
        $this->authorizeRentalAccess($rental);
        $period = $this->getActiveFiscalPeriod();

        if ($period) {
            $this->incomeService($period)->clearTenantCharges($rental);
        }

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', __('messages.flash_charges_cleared'));
    }

    public function checkoutTenant(CheckoutTenantRequest $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        $validated = $request->validated();
        $rental = Rentals::with(['apartment.floor', 'tenant'])->findOrFail($validated['rental_id']);
        $this->authorizeRentalAccess($rental);
        $result = $this->incomeService($activePeriod)->checkout($rental, $validated);

        if ($result['total_paid'] === 0.0) {
            return redirect()->back()->with('error', __('messages.flash_no_items_selected'));
        }

        $tenantName = $rental->tenant->name ?? __('messages.tenant');
        $aptNumber = ($rental->apartment?->apartment_number ?? 'N/A');

        return redirect()->back()
            ->with('print_bill_rental', $rental->id)
            ->with(
                'success',
                __('messages.flash_checkout_payment', [
                    'amount' => $result['total_paid'],
                    'name' => $tenantName,
                    'apt' => $aptNumber,
                    'items' => implode(', ', $result['items']),
                ])
            );
    }

    /**
     * Print a tenant's bill for the current month.
     */
    public function printTenantBill($rentalId)
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $rental = Rentals::with(['apartment.floor', 'apartment.activeFixedExpenses', 'tenant'])
            ->findOrFail($rentalId);
        $this->authorizeRentalAccess($rental);

        // Get utilities for current month
        $utilities = Utilities::where('rental_id', $rental->id)
            ->where('billing_month', $currentMonth)
            ->where('billing_year', $currentYear)
            ->get();

        // Get payments for current month
        $payments = Payments::where('rental_id', $rental->id)
            ->where('payment_status', 'paid')
            ->whereMonth('paid_at', $currentMonth)
            ->whereYear('paid_at', $currentYear)
            ->get();

        $paidThisMonth = $payments->where('payment_type', 'rent')->isNotEmpty();

        // Due date
        $dueDay = $rental->start_date ? $rental->start_date->day : 1;
        $dueDay = min($dueDay, Carbon::create($currentYear, $currentMonth)->daysInMonth);
        $dueDate = Carbon::create($currentYear, $currentMonth, $dueDay);

        // Fixed expenses
        $fixedExpenses = $rental->apartment->activeFixedExpenses ?? collect();

        // Calculate totals
        $totalUtilities = $utilities->sum('charge_amount');
        $totalFixed = $fixedExpenses->sum('amount');
        $totalBill = $rental->rent_amount + $totalUtilities + $totalFixed;
        $totalPaid = $payments->sum('amount') + $payments->sum('late_fee');
        $balance = $totalBill - $totalPaid;

        // Bill data
        $billData = [
            'rental' => $rental,
            'apartment' => $rental->apartment,
            'tenant' => $rental->tenant,
            'floor' => $rental->apartment?->floor,
            'dueDate' => $dueDate,
            'monthYear' => now()->format('F Y'),
            'rent_amount' => $rental->rent_amount,
            'utilities' => $utilities,
            'totalUtilities' => $totalUtilities,
            'fixedExpenses' => $fixedExpenses,
            'totalFixed' => $totalFixed,
            'totalBill' => $totalBill,
            'totalPaid' => $totalPaid,
            'balance' => $balance,
            'paidThisMonth' => $paidThisMonth,
            'payments' => $payments,
            'activePeriod' => $activePeriod,
        ];

        return view('supervisor.revenue_expense.tenant_bill_print', $billData);
    }

    public function printReceipt(Request $request, $rentalId)
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $rental = Rentals::with(['apartment.floor.property', 'apartment.activeFixedExpenses', 'tenant'])
            ->findOrFail($rentalId);
        $this->authorizeRentalAccess($rental);

        $utilities = Utilities::where('rental_id', $rental->id)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->get();

        $payments = Payments::where('rental_id', $rental->id)
            ->where('payment_status', 'paid')
            ->whereMonth('paid_at', $month)
            ->whereYear('paid_at', $year)
            ->orderBy('paid_at')
            ->get();

        $fixedExpenses = $rental->apartment->activeFixedExpenses ?? collect();

        $totalUtilities = $utilities->sum('charge_amount');
        $totalFixed = $fixedExpenses->sum('amount');
        $totalBill = $rental->rent_amount + $totalUtilities + $totalFixed;

        $amountPaid = $payments->sum('amount') + $payments->sum('late_fee');
        $latestPayment = $payments->last();
        $isPaid = $payments->where('payment_type', 'rent')->isNotEmpty();
        $paymentDate = $latestPayment?->paid_at ?? now();

        return view('supervisor.revenue_expense.payment_receipt', [
            'rental' => $rental,
            'apartment' => $rental->apartment,
            'tenant' => $rental->tenant,
            'property' => $rental->apartment?->property,
            'monthYear' => Carbon::create($year, $month, 1)->format('F Y'),
            'rentAmount' => $rental->rent_amount,
            'utilities' => $utilities,
            'fixedExpenses' => $fixedExpenses,
            'totalBill' => $totalBill,
            'amountPaid' => $amountPaid,
            'balance' => max($totalBill - $amountPaid, 0),
            'isPaid' => $isPaid,
            'paymentDate' => $paymentDate,
            'paymentMethod' => $latestPayment?->payment_method,
            'note' => $payments->pluck('note')->filter()->implode(' · '),
            'reference' => $latestPayment?->transaction_reference,
            'receiptNumber' => 'RCPT-'.$rental->id.'-'.Carbon::parse($paymentDate)->format('Ymd').($latestPayment ? '-'.$latestPayment->id : ''),
            'billReference' => 'BILL-'.$rental->id.'-'.sprintf('%04d%02d', $year, $month),
            'generatedBy' => auth()->user()?->name ?? '—',
            'generatedAt' => now(),
        ]);
    }

    public function storeIncome(RecordIncomeRequest $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        $validated = $request->validated();
        $rental = Rentals::with('apartment.floor')->findOrFail($validated['rental_id']);
        $this->authorizeRentalAccess($rental);
        $this->incomeService($activePeriod)->recordPayment($rental, $validated);

        return redirect()->back()->with(
            'success',
            __('messages.flash_income_recorded', [
                'type' => ucfirst($validated['payment_type']),
                'amount' => number_format($validated['amount'], 2),
                'apt' => ($rental->apartment?->apartment_number ?? 'N/A'),
            ])
        );
    }

    public function storeBulkIncome(RecordBulkIncomeRequest $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        $validated = $request->validated();
        // Drop any apartment ids outside the supervisor's assigned properties.
        $apartmentIds = $this->filterToVisibleApartmentIds($validated['apartments']);
        $result = $this->incomeService($activePeriod)->recordBulkRent(
            $validated['payment_date'],
            $validated['payment_method'],
            $apartmentIds,
        );

        if ($result['count'] === 0) {
            return redirect()->back()->with('error', __('messages.flash_no_apartments_selected'));
        }

        return redirect()->back()->with(
            'success',
            __('messages.flash_bulk_rent', [
                'count' => $result['count'],
                'total' => number_format($result['total'], 2),
            ])
        );
    }

    /**
     * @deprecated Dead block from refactor; the loop body below will be removed.
     */

    /**
     * Show record expense form — apartment-centric utility expense recording
     * with monthly breakdown, other expense allocation, and business expenses.
     */
    public function recordExpense()
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        // Monthly filter (default to current month)
        $filterMonth = request('month') ? (int) request('month') : now()->month;
        $filterYear = request('year') ? (int) request('year') : now()->year;

        // Date range clamped to fiscal period
        $dateRange = $this->getFilteredDateRange($activePeriod, $filterMonth, $filterYear);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        $periodMonths = $this->buildPeriodMonths($activePeriod);

        // Get all apartments with rentals and their utilities for the selected month.
        // Filter utilities by billing_month/billing_year so tenant-charged utilities
        // (stored with paid_at = null from record_income) are included alongside
        // expense-side utilities that carry a paid_at date.
        $apartments = $this->scopeApartments()
            ->with(['floor', 'activeFixedExpenses', 'rentals' => function ($q) use ($filterMonth, $filterYear) {
                $q->orderBy('start_date', 'desc')
                    ->with(['tenant', 'utilities' => function ($uq) use ($filterMonth, $filterYear) {
                        $uq->where('billing_month', $filterMonth)
                            ->where('billing_year', $filterYear);
                    }]);
            }])
            ->get();

        // Calculate per-apartment expense totals (utilities for selected month)
        $apartmentExpenses = [];
        $totalExpenses = 0;

        foreach ($apartments as $apartment) {
            $aptExpense = [
                'apartment' => $apartment,
                'electricity' => 0,
                'water' => 0,
                'internet' => 0,
                'parking' => 0,
                'trash' => 0,
                'other' => 0,
                'fixed_total' => $apartment->activeFixedExpenses->sum('amount'),
                'fixed_items' => $apartment->activeFixedExpenses,
                'total' => 0,
                'has_active_rental' => $apartment->rentals->isNotEmpty(),
            ];

            foreach ($apartment->rentals as $rental) {
                foreach ($rental->utilities as $utility) {
                    $type = $utility->utility_type;
                    if (isset($aptExpense[$type])) {
                        $aptExpense[$type] += $utility->charge_amount;
                    }
                    $aptExpense['total'] += $utility->charge_amount;
                }
            }

            $aptExpense['grand_total'] = $aptExpense['total'] + $aptExpense['fixed_total'];
            $totalExpenses += $aptExpense['grand_total'];
            $apartmentExpenses[] = $aptExpense;
        }

        // Keep a full copy of the per-apartment expense array for totals and reporting
        $apartmentExpensesAll = $apartmentExpenses;

        // Paginate apartment expenses for listing (10 per page)
        $perPage = 10;
        $page = (int) (request()->get('page', 1));
        $offset = ($page - 1) * $perPage;
        $apartmentExpenses = new LengthAwarePaginator(
            array_slice($apartmentExpensesAll, $offset, $perPage),
            count($apartmentExpensesAll),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        // Recent expense records from Accounts for the selected month — scoped to the supervisor's properties.
        $recentExpenses = $this->scopeAccountsToProperty(
            Accounts::expense()->forUser($this->getAdminUserId())
        )
            ->forPeriod($activePeriod->id)
            ->betweenDates($startDate, $endDate)
            ->orderBy('transaction_date', 'desc')
            ->take(10)
            ->get();

        $utilityTypes = [
            'electricity' => 'Electricity',
            'water' => 'Water',
            'internet' => 'Internet',
            'parking' => 'Parking',
            'trash' => 'Trash',
            'other' => 'Other',
        ];

        // Other expense categories (non-utility)
        $otherExpenseCategories = [
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

        // Other (non-utility) expenses for the selected month — scoped to the supervisor's properties.
        $otherExpenses = $this->scopeAccountsToProperty(
            Accounts::expense()->forUser($this->getAdminUserId())
        )
            ->forPeriod($activePeriod->id)
            ->whereNotIn('category', [
                Accounts::CAT_UTILITIES_EXPENSE,
                Accounts::CAT_BUSINESS_FIXED,
                Accounts::CAT_BUSINESS_VARIABLE,
            ])
            ->betweenDates($startDate, $endDate)
            ->orderBy('transaction_date', 'desc')
            ->get();

        $totalOtherExpenses = $otherExpenses->sum('amount');

        // Business expenses (fixed & variable) for the selected month — scoped to the supervisor's properties.
        $businessExpenses = $this->scopeAccountsToProperty(
            BusinessExpense::where('user_id', $this->getAdminUserId())
        )
            ->where('fiscal_period_id', $activePeriod->id)
            ->where('billing_month', $filterMonth)
            ->where('billing_year', $filterYear)
            ->orderBy('expense_date', 'desc')
            ->get();

        $businessTotal = $businessExpenses->sum('amount');

        // Business expense categories
        $businessCategories = [
            'electricity' => 'Electricity',
            'water' => 'Water',
            'trash' => 'Trash',
            'internet' => 'Internet',
            'legal_fee' => 'Legal Fee',
            'tax' => 'Tax',
            'loan_payment' => 'Loan Payment',
            'salary' => 'Salary',
            'other' => 'Other',
        ];

        // Tenants expense collection (utilities + fixed charges billed to tenants).
        $tenantsExpenseCollected = $totalExpenses;

        // Net of tenant collections against business expenses: negative → remaining
        // business expense, positive → expense revenue (counts toward revenue).
        $expenseNet = $tenantsExpenseCollected - $businessTotal;

        // Grand total of business-side expenses for the selected month — only the
        // portion of business expenses not covered by tenant collections counts.
        $grandTotalExpenses = $totalOtherExpenses + max(0, -$expenseNet);

        $currentMonth = now()->month;
        $currentYear = now()->year;

        return view('supervisor.revenue_expense.record_expense', compact(
            'activePeriod', 'apartments', 'apartmentExpenses', 'apartmentExpensesAll', 'recentExpenses',
            'utilityTypes', 'totalExpenses', 'otherExpenseCategories', 'otherExpenses',
            'totalOtherExpenses', 'businessExpenses', 'businessTotal', 'businessCategories',
            'grandTotalExpenses', 'tenantsExpenseCollected', 'expenseNet', 'currentMonth', 'currentYear',
            'filterMonth', 'filterYear', 'periodMonths'
        ));
    }

    public function storeExpense(StoreUtilityExpenseRequest $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        $validated = $request->validated();
        $rental = Rentals::with('tenant', 'apartment.floor')->findOrFail($validated['rental_id']);
        $this->authorizeRentalAccess($rental);
        $this->expenseService($activePeriod)->recordUtilityExpense($rental, $validated);

        return redirect()->back()->with(
            'success',
            __('messages.flash_utility_expense_recorded', [
                'type' => ucfirst($validated['utility_type']),
                'amount' => number_format($validated['charge_amount'], 2),
                'apt' => ($rental->apartment?->apartment_number ?? 'N/A'),
            ])
        );
    }

    public function storeOtherExpense(StoreOtherExpenseRequest $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        $validated = $request->validated();
        $this->expenseService($activePeriod)->recordOtherExpense($validated);

        return redirect()->back()->with(
            'success',
            __('messages.flash_other_expense_recorded', [
                'amount' => number_format($validated['amount'], 2),
                'description' => $validated['description'],
            ])
        );
    }

    public function deleteOtherExpense(Accounts $expense)
    {
        // Supervisor authorization: row must belong to the active fiscal period.
        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod || $expense->fiscal_period_id !== $activePeriod->id) {
            abort(403);
        }

        $desc = $this->expenseService($activePeriod)->deleteOtherExpense($expense);

        return redirect()->back()->with('success', __('messages.flash_expense_removed', ['desc' => $desc]));
    }

    public function storeBusinessExpense(StoreBusinessExpenseRequest $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        $validated = $request->validated();
        $attachmentPath = $request->hasFile('attachment')
            ? $request->file('attachment')->store('business_expenses', 'public')
            : null;

        $this->expenseService($activePeriod)->recordBusinessExpense($validated, $attachmentPath);

        return redirect()->back()->with(
            'success',
            __('messages.flash_business_expense_recorded', [
                'name' => $validated['expense_name'],
                'amount' => number_format($validated['amount'], 2),
            ])
        );
    }

    public function deleteBusinessExpense(BusinessExpense $businessExpense)
    {
        $name = $this->expenseService()->deleteBusinessExpense($businessExpense);

        return redirect()->back()->with('success', __('messages.flash_business_expense_removed', ['name' => $name]));
    }

    // ===========================================================================
    // FIXED EXPENSE MANAGEMENT
    // ===========================================================================

    /**
     * Show fixed expenses management page — assign recurring costs per apartment.
     */
    public function fixedExpenses()
    {
        $apartments = $this->scopeApartments()
            ->with(['floor', 'fixedExpenses', 'rentals' => function ($q) {
                $q->orderBy('start_date', 'desc')
                    ->with('tenant');
            }])
            ->get();

        return view('supervisor.revenue_expense.fixed_expenses', compact('apartments'));
    }

    public function storeFixedExpense(StoreFixedExpenseRequest $request)
    {
        $validated = $request->validated();
        $apartment = Apartments::with('floor')->findOrFail($validated['apartment_id']);
        $this->authorizeSupervisorApartment($apartment);
        $this->expenseService()->recordFixedExpense($validated);

        return redirect()->back()->with(
            'success',
            __('messages.flash_fixed_expense_assigned', [
                'name' => $validated['expense_name'],
                'amount' => number_format($validated['amount'], 2),
            ])
        );
    }

    public function toggleFixedExpense(ApartmentFixedExpense $fixedExpense)
    {
        $fixedExpense->loadMissing('apartment.floor');
        $this->authorizeSupervisorApartment($fixedExpense->apartment);
        $isActive = $this->expenseService()->toggleFixedExpense($fixedExpense);

        return redirect()->back()->with(
            'success',
            __($isActive ? 'messages.flash_fixed_expense_activated' : 'messages.flash_fixed_expense_deactivated', [
                'name' => $fixedExpense->expense_name,
            ])
        );
    }

    public function deleteFixedExpense(ApartmentFixedExpense $fixedExpense)
    {
        $fixedExpense->loadMissing('apartment.floor');
        $this->authorizeSupervisorApartment($fixedExpense->apartment);
        $name = $this->expenseService()->deleteFixedExpense($fixedExpense);

        return redirect()->back()->with('success', __('messages.flash_item_removed', ['name' => $name]));
    }

    // ===========================================================================
    // MONTHLY EXPENSE BILL GENERATION
    // ===========================================================================

    /**
     * Show the monthly bill generation page.
     */
    public function generateMonthlyBills()
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Get apartments with rentals, fixed expenses, and already-generated bills this month
        $apartments = $this->scopeApartments()
            ->with(['floor', 'activeFixedExpenses', 'rentals' => function ($q) use ($currentMonth, $currentYear) {
                $q->where(function ($q2) {
                    $q2->whereNull('end_date')->orWhere('end_date', '>=', now());
                })
                    ->orderBy('start_date', 'desc')
                    ->with(['tenant', 'utilities' => function ($uq) use ($currentMonth, $currentYear) {
                        $uq->where('billing_month', $currentMonth)
                            ->where('billing_year', $currentYear);
                    }]);
            }])
            ->get();

        // Build per-apartment bill summary
        $billSummary = [];
        $totalMonthlyExpenses = 0;

        foreach ($apartments as $apartment) {
            if ($apartment->rentals->isEmpty()) {
                continue;
            }

            foreach ($apartment->rentals as $rental) {
                $fixedExpenses = $apartment->activeFixedExpenses;
                $alreadyBilled = $rental->utilities
                    ->where('billing_month', $currentMonth)
                    ->where('billing_year', $currentYear);

                // Check which fixed expenses are already billed this month
                $billedTypes = $alreadyBilled->pluck('utility_type')->toArray();

                $expenses = [];
                $totalForApt = 0;

                foreach ($fixedExpenses as $fe) {
                    $isBilled = in_array($fe->expense_type, $billedTypes);
                    $expenses[] = [
                        'id' => $fe->id,
                        'name' => $fe->expense_name,
                        'type' => $fe->expense_type,
                        'amount' => $fe->amount,
                        'is_billed' => $isBilled,
                    ];
                    $totalForApt += $fe->amount;
                }

                // Include rent amount
                $monthlyRent = $rental->rent_amount;

                $totalMonthlyExpenses += $totalForApt;

                $billSummary[] = [
                    'apartment' => $apartment,
                    'rental' => $rental,
                    'tenant_name' => $rental->tenant->name ?? 'N/A',
                    'monthly_rent' => $monthlyRent,
                    'fixed_expenses' => $expenses,
                    'total_fixed' => $totalForApt,
                    'total_bill' => $monthlyRent + $totalForApt,
                    'has_unbilled' => collect($expenses)->contains('is_billed', false),
                ];
            }
        }

        return view('supervisor.revenue_expense.generate_bills', compact(
            'activePeriod', 'billSummary', 'totalMonthlyExpenses', 'currentMonth', 'currentYear'
        ));
    }

    /**
     * Process bulk monthly expense generation for all selected apartments.
     */
    public function processMonthlyBills(ProcessMonthlyBillsRequest $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        $validated = $request->validated();
        // Drop any bills for rentals outside the supervisor's assigned properties.
        $bills = $this->filterBillsToVisibleRentals($validated['bills']);
        $result = $this->billingService($activePeriod)->processSelected(
            $bills,
            Carbon::parse($validated['billing_date']),
        );

        if ($result['count'] === 0) {
            return redirect()->back()->with('error', __('messages.flash_no_new_expenses'));
        }

        return redirect()->back()->with(
            'success',
            __('messages.flash_bills_generated', [
                'count' => $result['count'],
                'total' => number_format($result['total'], 2),
            ])
        );
    }

    public function autoProcessMonthlyBills(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        $billingDate = $request->input('billing_date')
            ? Carbon::parse($request->input('billing_date'))
            : now();

        $result = $this->billingService($activePeriod)->processAll($this->scopeApartments(), $billingDate);

        if ($result['count'] === 0) {
            return redirect()->back()->with('error', __('messages.flash_no_new_expenses'));
        }

        return redirect()->back()->with(
            'success',
            __('messages.flash_bills_generated', [
                'count' => $result['count'],
                'total' => number_format($result['total'], 2),
            ])
        );
    }

    /**
     * Export per-apartment account summary as PDF (or HTML fallback).
     */
    public function apartmentSummaryPdf(Request $request)
    {
        $start = $request->get('start') ?: now()->startOfMonth()->toDateString();
        $end = $request->get('end') ?: now()->endOfMonth()->toDateString();

        $perApartment = $this->calculatePerApartmentData($start, $end);
        $activePeriod = $this->getActiveFiscalPeriod();

        // view flags
        $summaryOnly = (bool) $request->get('summary_only', false);
        $wholeNumbers = (bool) $request->get('whole', false);

        // If Dompdf (barryvdh) is installed, use it. Otherwise return HTML view for manual printing.
        try {
            if (class_exists('\\Barryvdh\\DomPDF\\Facade') || class_exists('\\PDF')) {
                $forPdf = true;
                $pdf = \PDF::loadView('supervisor.revenue_expense.apartment_summary_pdf', compact('perApartment', 'activePeriod', 'start', 'end', 'summaryOnly', 'wholeNumbers', 'forPdf'));
                $filename = 'apartment-summary-'.now()->format('Y-m-d').'.pdf';

                return $pdf->download($filename);
            }
        } catch (\Exception $e) {
            // Fall through to HTML view
        }

        return response()->view('supervisor.revenue_expense.apartment_summary_pdf', compact('perApartment', 'activePeriod', 'start', 'end', 'summaryOnly', 'wholeNumbers'));
    }

    /**
     * Show HTML preview of the apartment summary before exporting to PDF.
     * This returns the same view used for PDF rendering but with a preview toolbar.
     */
    public function apartmentSummaryPreview(Request $request)
    {
        $start = $request->get('start') ?: now()->startOfMonth()->toDateString();
        $end = $request->get('end') ?: now()->endOfMonth()->toDateString();

        $perApartment = $this->calculatePerApartmentData($start, $end);
        $activePeriod = $this->getActiveFiscalPeriod();

        // view flags
        $summaryOnly = (bool) $request->get('summary_only', false);
        $wholeNumbers = (bool) $request->get('whole', false);

        return response()->view('supervisor.revenue_expense.apartment_summary_pdf', compact('perApartment', 'activePeriod', 'start', 'end', 'summaryOnly', 'wholeNumbers'))
            ->header('X-Preview-Mode', '1');
    }

    /**
     * Monthly calendar view showing daily income and expenses.
     */
    public function monthlyCalendar(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        // Determine month/year from query or default to current
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Fetch daily expenses from accounts (single source of truth)
        $dailyAccountExpenses = Accounts::expense()
            ->forUser($this->getAdminUserId())
            ->betweenDates($startOfMonth, $endOfMonth)
            ->selectRaw('DATE(transaction_date) as day, SUM(amount) as total_expense, COUNT(*) as tx_count')
            ->groupByRaw('DATE(transaction_date)')
            ->get()
            ->keyBy('day');

        // Fetch daily income from accounts (single source of truth)
        $dailyAccountIncome = Accounts::income()
            ->forUser($this->getAdminUserId())
            ->betweenDates($startOfMonth, $endOfMonth)
            ->selectRaw('DATE(transaction_date) as day, SUM(amount) as total_income, COUNT(*) as tx_count')
            ->groupByRaw('DATE(transaction_date)')
            ->get()
            ->keyBy('day');

        // Build calendar data
        $daysInMonth = $startOfMonth->daysInMonth;
        $firstDayOfWeek = $startOfMonth->dayOfWeek; // 0=Sun
        $calendarDays = [];
        $monthTotalIncome = 0;
        $monthTotalExpense = 0;
        $bestDay = null;
        $worstDay = null;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = $startOfMonth->copy()->day($d)->toDateString();
            $income = $dailyAccountIncome[$dateStr]->total_income ?? 0;
            $expense = $dailyAccountExpenses[$dateStr]->total_expense ?? 0;
            $net = $income - $expense;
            $txCount = ($dailyAccountIncome[$dateStr]->tx_count ?? 0)
                     + ($dailyAccountExpenses[$dateStr]->tx_count ?? 0);

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

            if ($txCount > 0) {
                if ($bestDay === null || $net > $calendarDays[$bestDay]['net']) {
                    $bestDay = $d;
                }
                if ($worstDay === null || $net < $calendarDays[$worstDay]['net']) {
                    $worstDay = $d;
                }
            }
        }

        $monthNet = $monthTotalIncome - $monthTotalExpense;

        // Previous / next month navigation
        $prevMonth = $startOfMonth->copy()->subMonth();
        $nextMonth = $startOfMonth->copy()->addMonth();

        return view('supervisor.revenue_expense.monthly_calendar', compact(
            'activePeriod', 'startOfMonth', 'endOfMonth', 'month', 'year',
            'firstDayOfWeek', 'daysInMonth', 'calendarDays',
            'monthTotalIncome', 'monthTotalExpense', 'monthNet',
            'bestDay', 'worstDay',
            'prevMonth', 'nextMonth'
        ));
    }

    // ===========================================================================
    // INCOME STATEMENT
    // ===========================================================================

    /**
     * Display Income Statement — the clearest view of profit & loss.
     *
     * STRUCTURE:
     *   Revenue (owner's actual income):
     *     - Monthly Rent collected
     *     - Late Fees
     *     - Early Leave Fees
     *     - Parking Revenue
     *
     *   Gross Revenue (pass-through, NOT owner's profit):
     *     - Electricity collected from tenants
     *     - Water collected from tenants
     *     - Internet collected from tenants
     *
     *   Expenses (owner pays out):
     *     - Security, Electricity, Water, Internet (vendor bills)
     *     - Property Tax
     *     - Other business expenses
     *
     *   Net Income = Revenue − Expenses
     */
    public function incomeStatement(Request $request)
    {
        $activePeriod = $this->resolveActivePeriod($request->integer('period') ?: null);

        if (! $activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.flash_fp_required'));
        }

        // Monthly filter
        $filterMonth = $request->integer('month') ?: null;
        $filterYear = $request->integer('year') ?: null;

        $dateRange = $this->getFilteredDateRange($activePeriod, $filterMonth, $filterYear);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        $periodMonths = $this->buildPeriodMonths($activePeriod);
        $apartmentIds = $this->scopeApartments()->pluck('id');

        // ================================================
        // REVENUE (Owner's actual income)
        // ================================================

        // 1. Monthly Rent
        $rentPayments = Payments::whereHas('rental', function ($q) use ($apartmentIds) {
            $q->whereIn('apartment_id', $apartmentIds);
        })
            ->where('payment_status', 'paid')
            ->where('payment_type', 'rent')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $rentIncome = round($rentPayments->sum('amount'), 2);
        $lateFees = round($rentPayments->sum('late_fee'), 2);

        // 2. Early Leave Fees (balance_due from completed tenant checkouts)
        $earlyLeaveIncome = round(
            TenantLeave::whereIn('apartment_id', $apartmentIds)
                ->where('status', 'completed')
                ->whereBetween('leave_date', [$startDate, $endDate])
                ->sum('balance_due'),
            2
        );

        // 3. Parking Revenue (collected from tenants)
        $parkingRevenue = round(
            Utilities::whereHas('rental', function ($q) use ($apartmentIds) {
                $q->whereIn('apartment_id', $apartmentIds);
            })
                ->where('utility_type', 'parking')
                ->paid()
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->sum('charge_amount'),
            2
        );

        $totalRevenue = $rentIncome + $lateFees + $earlyLeaveIncome + $parkingRevenue;

        // ================================================
        // GROSS REVENUE (Collected from tenants → paid to vendors)
        // These are pass-through: NOT owner's profit
        // ================================================

        $utilityCollected = Utilities::whereHas('rental', function ($q) use ($apartmentIds) {
            $q->whereIn('apartment_id', $apartmentIds);
        })
            ->paid()
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $electricityCollected = round($utilityCollected->where('utility_type', 'electricity')->sum('charge_amount'), 2);
        $waterCollected = round($utilityCollected->where('utility_type', 'water')->sum('charge_amount'), 2);
        $internetCollected = round($utilityCollected->where('utility_type', 'internet')->sum('charge_amount'), 2);
        $totalGrossRevenue = $electricityCollected + $waterCollected + $internetCollected;

        $totalAllCollected = $totalRevenue + $totalGrossRevenue;

        // ================================================
        // EXPENSES (Owner pays out)
        // ================================================

        // Get all business expenses for the period
        $businessExpenses = BusinessExpense::where('user_id', $this->getAdminUserId())
            ->where('fiscal_period_id', $activePeriod->id)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        // Get all utility expense account records for the period
        // (These are created when recording utility expenses via storeExpense())
        $utilityAccountExpenses = Accounts::expense()
            ->forUser($this->getAdminUserId())
            ->forPeriod($activePeriod->id)
            ->category(Accounts::CAT_UTILITIES_EXPENSE)
            ->betweenDates($startDate, $endDate)
            ->get();

        // Security expense (from business expenses)
        $securityExpense = round($businessExpenses->where('category', 'security')->sum('amount'), 2);

        // Vendor utility payments — match by utility_type in the description.
        // The description format is: "[Apt X] Electricity" (set by storeExpense method).
        // We use a reliable keyword match from the description since the utility_type
        // is embedded there when the expense is recorded.
        $electricityExpense = round(
            $businessExpenses->filter(fn ($e) => in_array($e->category, ['electricity', 'utilities_electricity']))->sum('amount')
            + $utilityAccountExpenses->filter(fn ($e) => str_contains(strtolower($e->description), 'electricity'))->sum('amount'),
            2
        );

        $waterExpense = round(
            $businessExpenses->filter(fn ($e) => in_array($e->category, ['water', 'utilities_water']))->sum('amount')
            + $utilityAccountExpenses->filter(fn ($e) => str_contains(strtolower($e->description), 'water'))->sum('amount'),
            2
        );

        $internetExpense = round(
            $businessExpenses->filter(fn ($e) => in_array($e->category, ['internet', 'utilities_internet']))->sum('amount')
            + $utilityAccountExpenses->filter(fn ($e) => str_contains(strtolower($e->description), 'internet'))->sum('amount'),
            2
        );

        // Tax expense
        $taxExpense = round(
            $businessExpenses->filter(fn ($e) => in_array($e->category, ['property_tax', 'tax']))->sum('amount')
            + Accounts::expense()
                ->forUser($this->getAdminUserId())
                ->forPeriod($activePeriod->id)
                ->whereIn('category', [Accounts::CAT_PROPERTY_TAX, 'taxes'])
                ->betweenDates($startDate, $endDate)
                ->sum('amount'),
            2
        );

        // Other expenses (everything not already counted above)
        $countedBusinessCategories = [
            'security', 'electricity', 'utilities_electricity',
            'water', 'utilities_water', 'internet', 'utilities_internet',
            'property_tax', 'tax',
        ];

        $otherBusinessExpense = round(
            $businessExpenses->filter(fn ($e) => ! in_array($e->category, $countedBusinessCategories))->sum('amount'),
            2
        );

        // Other account expenses (not utility, not business fixed/variable, not tax)
        $otherAccountExpense = round(
            Accounts::expense()
                ->forUser($this->getAdminUserId())
                ->forPeriod($activePeriod->id)
                ->whereNotIn('category', [
                    Accounts::CAT_UTILITIES_EXPENSE,
                    Accounts::CAT_BUSINESS_FIXED,
                    Accounts::CAT_BUSINESS_VARIABLE,
                    Accounts::CAT_PROPERTY_TAX,
                    'taxes',
                ])
                ->betweenDates($startDate, $endDate)
                ->sum('amount'),
            2
        );

        $otherExpense = $otherBusinessExpense + $otherAccountExpense;

        $totalExpenses = $securityExpense + $electricityExpense + $waterExpense
            + $internetExpense + $taxExpense + $otherExpense;

        // ================================================
        // NET INCOME
        // ================================================
        $netIncome = round($totalRevenue - $totalExpenses, 2);

        // Vendor balance (collected from tenants minus paid to vendors)
        $vendorBalance = round($totalGrossRevenue - ($electricityExpense + $waterExpense + $internetExpense), 2);

        $fiscalPeriods = $this->getAllFiscalPeriods();

        return view('supervisor.revenue_expense.income_statement', compact(
            'activePeriod', 'fiscalPeriods', 'filterMonth', 'filterYear', 'periodMonths',
            // Revenue
            'rentIncome', 'lateFees', 'earlyLeaveIncome', 'parkingRevenue', 'totalRevenue',
            // Gross Revenue (pass-through)
            'electricityCollected', 'waterCollected', 'internetCollected',
            'totalGrossRevenue', 'totalAllCollected',
            // Expenses
            'securityExpense', 'electricityExpense', 'waterExpense',
            'internetExpense', 'taxExpense', 'otherExpense', 'totalExpenses',
            // Net Income
            'netIncome', 'vendorBalance'
        ));
    }
}
