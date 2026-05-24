<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Concerns\HasFiscalPeriodScope;
use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\ApartmentFixedExpense;
use App\Models\BusinessExpense;
use App\Models\FiscalPeriods;
use App\Models\Payments;
use App\Models\TenantLeave;
use App\Models\Utilities;
use App\Models\Rentals;
use App\Models\Apartments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;


class RevenueExpenseController extends Controller
{
    use HasFiscalPeriodScope;

    /**
     * Supervisors read from the admin's fiscal periods (not their own).
     */
    protected function fiscalPeriodsQuery(): Builder
    {
        return FiscalPeriods::whereHas('user', fn ($q) => $q->role('admin'));
    }

    /**
     * The admin user_id under which all financial records are stored.
     * Supervisors don't own ledger rows; they write under the admin's id.
     */
    private function getAdminUserId(): ?int
    {
        return $this->getActiveFiscalPeriod()?->user_id;
    }

    public function index()
    {
        // Allow switching fiscal periods via ?period=ID
        $activePeriod = $this->resolveActivePeriod(request('period') ? (int) request('period') : null);
        
        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first to track revenue and expenses.');
        }

        // Monthly filter: ?month=3&year=2026
        $filterMonth = request('month') ? (int) request('month') : null;
        $filterYear = request('year') ? (int) request('year') : null;

        // Get date range (clamped to fiscal period bounds)
        $dateRange = $this->getFilteredDateRange($activePeriod, $filterMonth, $filterYear);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        $periodMonths = $this->buildPeriodMonths($activePeriod);

        // ===== DASHBOARD DATA =====
        $revenueExpenseData = $this->getRevenueExpenseData($startDate, $endDate);
        $revenueExpenseData['activePeriod'] = $activePeriod;
        $revenueExpenseData['filterMonth'] = $filterMonth;
        $revenueExpenseData['filterYear'] = $filterYear;
        $revenueExpenseData['periodMonths'] = $periodMonths;
        
        $revenueExpenseData['fiscalPeriods'] = $this->getAllFiscalPeriods();

        $allApartments = $this->scopeApartments()->get();
        $revenueExpenseData['totalApartments'] = $allApartments->count();
        $revenueExpenseData['occupiedCount'] = $allApartments->where('status', 'occupied')->count();
        $revenueExpenseData['vacantCount'] = $allApartments->where('status', '!=', 'occupied')->count();
        $revenueExpenseData['occupancyRate'] = $allApartments->count() > 0
            ? round(($allApartments->where('status', 'occupied')->count() / $allApartments->count()) * 100, 1)
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
        $incomeApartments = $this->scopeApartments()
            ->with(['floor', 'rentals' => function ($q) use ($activePeriod) {
                $q->where(function ($sq) {
                        $sq->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })
                    ->orderBy('start_date', 'desc')
                    ->with(['tenant', 'payments' => function ($pq) use ($activePeriod) {
                        $pq->where('payment_status', 'paid')
                            ->whereHas('accounts', function ($aq) use ($activePeriod) {
                                $aq->where('fiscal_period_id', $activePeriod->id);
                            });
                    }]);
            }])
            ->get();

        $apartmentSummary = [];
        $totalRentExpected = 0;
        $totalRentCollected = 0;
        // Use the selected date range (clamped to fiscal period) for "this month" calculations.
        $rangeStart = Carbon::parse($startDate ?: now()->startOfMonth())->startOfDay();
        $rangeEnd = Carbon::parse($endDate ?: now()->endOfMonth())->endOfDay();

        foreach ($incomeApartments as $apartment) {
            foreach ($apartment->rentals as $rental) {
                // Sum payments that occurred inside the selected date range
                $monthPayments = $rental->payments->filter(function($p) use ($rangeStart, $rangeEnd) {
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

                $daysInRange = $rangeStart->diffInDays($rangeEnd) + 1;
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

        // Recent income (no pagination)
        $recentIncome = Accounts::income()
            ->forUser($this->getAdminUserId())
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

        // Current month/year used by billing and utilities queries (respect filter if provided)
        $currentMonth = $filterMonth ?? now()->month;
        $currentYear = $filterYear ?? now()->year;

        // ===== RECORD EXPENSE DATA =====
        $expenseApartments = $this->scopeApartments()
            ->with(['floor', 'rentals' => function ($q) use ($activePeriod) {
                $q->orderBy('start_date', 'desc')
                    ->with(['tenant', 'utilities' => function ($uq) use ($activePeriod) {
                        // Filter utilities by billing period within the fiscal year
                        $start = Carbon::parse($activePeriod->opening_date);
                        $end = Carbon::parse($activePeriod->closing_date);
                        $uq->where(function ($q) use ($start, $end) {
                            $q->whereBetween('paid_at', [$start->startOfDay(), $end->copy()->endOfDay()])
                              ->orWhere(function ($q2) use ($start, $end) {
                                  $q2->where('billing_year', '>=', $start->year)
                                      ->where('billing_year', '<=', $end->year);
                              });
                        });
                    }]);
            }])
            ->get();

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

        $recentExpenses = Accounts::expense()
            ->forUser($this->getAdminUserId())
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
        $revenueExpenseData['fixedApartments'] = $this->scopeApartments()
            ->with(['floor', 'fixedExpenses', 'rentals' => function ($q) {
                $q->orderBy('start_date', 'desc')->with('tenant');
            }])
            ->get();

        // ===== GENERATE BILLS DATA =====
        $billApartments = $this->scopeApartments()
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

        $billSummary = [];
        $totalMonthlyExpenses = 0;

        foreach ($billApartments as $apartment) {
            if ($apartment->rentals->isEmpty()) continue;

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

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $periodStart = Carbon::parse($activePeriod->opening_date)->startOfMonth();
        $periodEnd   = Carbon::parse($activePeriod->closing_date)->endOfMonth();

        $requested = Carbon::create(
            (int) $request->input('year', now()->year),
            (int) $request->input('month', now()->month),
            1
        )->startOfMonth();

        if ($requested->lt($periodStart)) $requested = $periodStart->copy();
        if ($requested->gt($periodEnd))   $requested = $periodEnd->copy()->startOfMonth();

        $selectedMonth = $requested->month;
        $selectedYear  = $requested->year;

        $prev = $requested->copy()->subMonth();
        $next = $requested->copy()->addMonth();
        $hasPrev = $prev->gte($periodStart);
        $hasNext = $next->lte($periodEnd);

        $data = $this->calculateBreakEvenPoint($selectedMonth, $selectedYear);
        $data['activePeriod']  = $activePeriod;
        $data['selectedMonth'] = $selectedMonth;
        $data['selectedYear']  = $selectedYear;
        $data['selectedDate']  = $requested;
        $data['prevMonth']     = $hasPrev ? $prev->month : null;
        $data['prevYear']      = $hasPrev ? $prev->year  : null;
        $data['nextMonth']     = $hasNext ? $next->month : null;
        $data['nextYear']      = $hasNext ? $next->year  : null;
        $data['hasPrev']       = $hasPrev;
        $data['hasNext']       = $hasNext;

        return view('supervisor.revenue_expense.break_event', $data);
    }

    /**
     * Get all revenue and expense data, scoped to fiscal period dates.
     * 
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getRevenueExpenseData($startDate = null, $endDate = null)
    {
        // If no dates provided, use the active fiscal period dates
        if (!$startDate || !$endDate) {
            $activePeriod = $this->getActiveFiscalPeriod();
            if ($activePeriod) {
                $startDate = $activePeriod->opening_date;
                $endDate = $activePeriod->closing_date;
            }
        }

        $income = $this->calculateIncome($startDate, $endDate);
        $expenses = $this->calculateExpenses($startDate, $endDate);
        $summary = $this->calculateSummary($income, $expenses);
        $perApartment = $this->calculatePerApartmentData($startDate, $endDate);

        return compact('income', 'expenses', 'summary', 'perApartment');
    }

    /**
     * Calculate total income from the Accounts table.
     *
     * Income categories:
     *   rent_income     = Monthly rent paid by tenants
     *   utility_income  = Electricity + Water collected from tenants
     *   other_income    = Internet + Parking + Trash + generic other charges
     *   deposit_income  = Security deposits
     *   (late_fee)      = Pulled from linked Payments records
     *
     * Utility income split:
     *   Utilities income → electricity, water
     *   Other income     → internet, parking, trash, other (generic charges)
     */
    public function calculateIncome($startDate = null, $endDate = null)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        $query = Accounts::income()->forUser($this->getAdminUserId());

        if ($activePeriod) {
            $query->forPeriod($activePeriod->id);
        }

        if ($startDate && $endDate) {
            $query->betweenDates($startDate, $endDate);
        }

        $records = $query->get();

        $rentIncome    = $records->where('category', Accounts::CAT_RENT_INCOME)->sum('amount');
        $depositIncome = $records->where('category', Accounts::CAT_DEPOSIT_INCOME)->sum('amount');
        $lateFeesIncome = $records->where('category', Accounts::CAT_LATE_FEE_INCOME)->sum('amount');
        $utilityIncomeFromAccts = $records->where('category', Accounts::CAT_UTILITY_INCOME)->sum('amount');
        $otherIncomeFromAccts   = $records->where('category', Accounts::CAT_OTHER_INCOME)->sum('amount');

        $totalIncome = $records->sum('amount');
        $paymentCount = $records->whereNotNull('payment_id')->pluck('payment_id')->unique()->count();

        $rangeStart = $startDate ? Carbon::parse($startDate) : ($activePeriod ? Carbon::parse($activePeriod->opening_date) : null);
        $rangeEnd   = $endDate   ? Carbon::parse($endDate)   : ($activePeriod ? Carbon::parse($activePeriod->closing_date) : null);

        $byType = [];
        if ($rangeStart && $rangeEnd) {
            $apartmentIds = $this->scopeApartments()->pluck('id');
            $byType = Utilities::whereHas('rental', function ($q) use ($apartmentIds) {
                    $q->whereIn('apartment_id', $apartmentIds);
                })
                ->where('paid_status', true)
                ->whereBetween('paid_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
                ->selectRaw('utility_type, SUM(charge_amount) as total')
                ->groupBy('utility_type')
                ->pluck('total', 'utility_type')
                ->toArray();
        }

        $utilityBreakdown = [
            'electricity' => round($byType['electricity'] ?? 0, 2),
            'water'       => round($byType['water']       ?? 0, 2),
        ];

        $otherIncomeBreakdown = [
            'internet' => round($byType['internet'] ?? 0, 2),
            'parking'  => round($byType['parking']  ?? 0, 2),
            'trash'    => round($byType['trash']     ?? 0, 2),
            'other'    => max(0, round($otherIncomeFromAccts
                - ($byType['internet'] ?? 0)
                - ($byType['parking']  ?? 0)
                - ($byType['trash']    ?? 0), 2)),
        ];

        return [
            'rent_income'             => round($rentIncome, 2),
            'late_fees'               => round($lateFeesIncome, 2),
            'total_utility_income'    => round($utilityIncomeFromAccts, 2),
            'utility_breakdown'       => $utilityBreakdown,
            'other_income'            => round($otherIncomeFromAccts, 2),
            'other_income_breakdown'  => $otherIncomeBreakdown,
            'deposit_income'          => round($depositIncome, 2),
            'total_income'            => round($totalIncome, 2),
            'payment_count'           => $paymentCount,
            'average_payment'         => $paymentCount > 0 ? round($rentIncome / $paymentCount, 2) : 0,
        ];
    }

    /**
     * Calculate all expenses from the Accounts table.
     *
     * Expense categories:
     *   business_fixed    = Recurring business costs (insurance, management fee)
     *   business_variable = One-time business costs (repairs, supplies)
     *   utilities_expense = Electricity, water, internet paid to vendors
     *   (everything else) = maintenance, property_tax, etc.
     */
    public function calculateExpenses($startDate = null, $endDate = null)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        $query = Accounts::expense()->forUser($this->getAdminUserId());

        if ($activePeriod) {
            $query->forPeriod($activePeriod->id);
        }

        if ($startDate && $endDate) {
            $query->betweenDates($startDate, $endDate);
        }

        $records = $query->get();

        $fixedExpenses    = $records->where('category', Accounts::CAT_BUSINESS_FIXED)->sum('amount');
        $variableExpenses = $records->where('category', Accounts::CAT_BUSINESS_VARIABLE)->sum('amount');
        $utilityExpenses  = $records->where('category', Accounts::CAT_UTILITIES_EXPENSE)->sum('amount');
        $depositExpenses  = $records->where('category', Accounts::CAT_DEPOSIT_EXPENSE)->sum('amount');
        $otherExpenses    = $records->whereNotIn('category', [
            Accounts::CAT_BUSINESS_FIXED,
            Accounts::CAT_BUSINESS_VARIABLE,
            Accounts::CAT_UTILITIES_EXPENSE,
            Accounts::CAT_DEPOSIT_EXPENSE,
        ])->sum('amount');
        $totalExpenses = $fixedExpenses + $variableExpenses + $utilityExpenses + $depositExpenses + $otherExpenses;

        // Group by category for detailed breakdown
        $byCategory = $records->groupBy('category')->map(fn($items) => round($items->sum('amount'), 2))->toArray();

        return [
            'fixed_expenses'    => round($fixedExpenses, 2),
            'variable_expenses' => round($variableExpenses, 2),
            'utility_expenses'  => round($utilityExpenses, 2),
            'deposit_expenses'  => round($depositExpenses, 2),
            'other_expenses'    => round($otherExpenses, 2),
            'by_category'       => $byCategory,
            'total_expenses'    => round($totalExpenses, 2),
            'expense_count'     => $records->count(),
        ];
    }

    /**
     * Calculate profit/loss summary from income and expense arrays.
     *
     * Net Profit = Total Income − Total Expenses
     * Profit Margin = (Net Profit / Total Income) × 100
     */
    public function calculateSummary($income, $expenses)
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
     * Calculate per-apartment revenue and expense breakdown.
     * Uses Accounts linkage for fiscal period filtering.
     */
    private function calculatePerApartmentData($startDate = null, $endDate = null)
    {
        // Determine the date range used for per-apartment "this period" calculations
        $rangeStart = Carbon::parse($startDate ?: now()->startOfMonth())->startOfDay();
        $rangeEnd = Carbon::parse($endDate ?: now()->endOfMonth())->endOfDay();
        $activePeriod = $this->getActiveFiscalPeriod();

        $apartments = $this->scopeApartments()
            ->with(['floor', 'activeFixedExpenses', 'rentals' => function ($q) use ($activePeriod, $startDate, $endDate) {
                $q->with([
                    'tenant',
                    'payments' => function ($pq) use ($activePeriod, $startDate, $endDate) {
                        $pq->where('payment_status', 'paid');
                        // Filter by fiscal period via Accounts linkage
                        if ($activePeriod) {
                            $pq->whereHas('accounts', function ($aq) use ($activePeriod, $startDate, $endDate) {
                                $aq->where('fiscal_period_id', $activePeriod->id);
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
                    }
                ]);
            }])
            ->get();

        // Preload "other" income charges (type='other') that have NO Utilities row —
        // they are stored only in Accounts with reference_number 'tenant_charge:rental:{id}:t...'
        $otherAccountsQuery = Accounts::where('account_type', Accounts::TYPE_INCOME)
            ->where('category', Accounts::CAT_OTHER_INCOME)
            ->where('reference_number', 'LIKE', 'tenant_charge:rental:%');
        if ($activePeriod) {
            $otherAccountsQuery->where('fiscal_period_id', $activePeriod->id);
        } elseif ($startDate && $endDate) {
            $otherAccountsQuery->whereBetween('transaction_date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }
        // Group by rental_id (extracted from reference_number: tenant_charge:rental:{rental_id}:t...)
        $otherAccountsByRental = [];
        foreach ($otherAccountsQuery->get() as $acct) {
            $parts = explode(':', $acct->reference_number);
            // format: tenant_charge:rental:{rental_id}:t{timestamp}
            if (isset($parts[2]) && is_numeric($parts[2])) {
                $rentalId = (int) $parts[2];
                $otherAccountsByRental[$rentalId] = ($otherAccountsByRental[$rentalId] ?? 0) + $acct->amount;
            }
        }

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

            foreach ($apartment->rentals as $rental) {
                $income += $rental->payments->sum('amount') + $rental->payments->sum('late_fee');
                $hasActiveRental = true;
                if ($rental->tenant) {
                    $tenantName = $rental->tenant->name ?? 'N/A';
                }

                // Rent progress for the selected date range, prorated by rental occupancy within the range
                $monthPayments = $rental->payments->filter(function ($p) use ($rangeStart, $rangeEnd) {
                    return $p->payment_type === 'rent' && Carbon::parse($p->paid_at)->between($rangeStart, $rangeEnd);
                });
                $rentPaid = $monthPayments->sum('amount');

                // Prorate rent due by overlap of rental start/end with the selected range
                $rentPeriodStart = Carbon::parse($rental->start_date)->startOfDay();
                $rentPeriodEnd = $rental->end_date ? Carbon::parse($rental->end_date)->endOfDay() : null;

                $overlapStart = $rentPeriodStart->greaterThan($rangeStart) ? $rentPeriodStart : $rangeStart;
                $overlapEnd = $rentPeriodEnd ? ($rentPeriodEnd->lessThan($rangeEnd) ? $rentPeriodEnd : $rangeEnd) : $rangeEnd;

                $overlapDays = 0;
                if ($overlapStart->lte($overlapEnd)) {
                    $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
                }

                $daysInRange = $rangeStart->diffInDays($rangeEnd) + 1;
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
                        $rentStatus = (now()->gt($dueDate) && !$isFirstMonth) ? 'overdue' : 'unpaid';
                    }
                }

                // Occupancy percent (how many days in the selected range the rental was occupied)
                $occupancyPercent = round($proration * 100, 1);

                // Last payment date within the selected range (if any)
                $lastPaymentDate = null;
                if ($monthPayments->isNotEmpty()) {
                    $lastPaymentDate = Carbon::parse($monthPayments->max('paid_at'))->toDateString();
                }

                // Occupancy end date for the overlap (if any)
                $occupancyEndDate = $overlapDays > 0 ? $overlapEnd->toDateString() : null;

                // days left for this rental overlap
                $daysLeft = null;
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
                    if (in_array($type, ['internet', 'parking', 'trash', 'other'])) {
                        $otherIncome += $utility->charge_amount;
                    }
                    if (in_array($type, ['electricity', 'water'])) {
                        $utilitiesIncome += $utility->charge_amount;
                    }
                }

                // Add "other" charges stored only in Accounts (no Utilities row)
                $rentalOtherFromAccounts = $otherAccountsByRental[$rental->id] ?? 0;
                if ($rentalOtherFromAccounts > 0) {
                    $expenseBreakdown['other'] += $rentalOtherFromAccounts;
                    $otherIncome  += $rentalOtherFromAccounts;
                    $expenses     += $rentalOtherFromAccounts;
                    $income       += $rentalOtherFromAccounts;
                }
            }

            // Add fixed expenses to the total 
            $fixedExpTotal = $apartment->activeFixedExpenses->sum('amount');

            // Get the active rental id and tenant id
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
                // explicit floor_number for view grouping
                'floor_number' => $apartment->floor->floor_number ?? 'N/A',
                'tenant' => $tenantName ?: 'Vacant',
                'has_active_rental' => $hasActiveRental,
                'monthly_rent' => $apartment->monthly_rent,
                'income' => round($income, 2),
                'expenses' => round($expenses, 2),
                'utilities_income' => round($utilitiesIncome, 2),
                'other_income' => round($otherIncome, 2),
                'fixed_expenses' => round($fixedExpTotal, 2),
                'tenant_net' => round($income - $expenses, 2),           // Net: Income minus utility costs
                'owner_expenses' => round($fixedExpTotal, 2),            // Owner → Vendor
                'net' => round($income - $expenses - $fixedExpTotal, 2),
                'expense_breakdown' => $expenseBreakdown,
                'status' => $apartment->status,
                'rent_percent' => $rentPercent,
                'rent_paid' => round($rentPaid, 2),
                'rent_due' => round($rentDue, 2),
                'occupancy_percent' => $occupancyPercent ?? 0,
                'last_payment_date' => $lastPaymentDate,
                'occupancy_end_date' => $occupancyEndDate,
                'days_left' => $daysLeft,
                'rent_status' => $hasActiveRental ? $rentStatus : 'none',
            ];
        }

        return $perApartment;
    }

    /**
     * Calculate break-even point with full financial context.
     *
     * Connects break-even analysis to the balance sheet and overall
     * financial health of the business. Uses ALL cost sources.
     * 
     * @return array
     */
    public function calculateBreakEvenPoint(?int $month = null, ?int $year = null)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        $month = $month ?: now()->month;
        $year  = $year  ?: now()->year;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd   = $monthStart->copy()->endOfMonth();

        $apartments = $this->scopeApartments()->get();
        $totalApartments = $apartments->count();

        // Pull income + expenses from the SAME source as the dashboard so the
        // two views can never disagree. total_income and total_expenses match
        // the numbers shown on /revenue_expense exactly.
        $income   = $this->calculateIncome($monthStart, $monthEnd);
        $expenses = $this->calculateExpenses($monthStart, $monthEnd);

        $totalRevenue  = (float) $income['total_income'];
        $totalExpenses = (float) $expenses['total_expenses'];

        // Average rent across rentals active during the selected month
        $avgRentPerApartment = Rentals::whereIn('apartment_id', $apartments->pluck('id'))
            ->where('start_date', '<=', $monthEnd)
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $monthStart);
            })
            ->avg('rent_amount') ?? 0;

        $currentOccupancy = Rentals::whereIn('apartment_id', $apartments->pluck('id'))
            ->where('start_date', '<=', $monthEnd)
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $monthStart);
            })
            ->count();

        // Business expenses = recurring overhead from BusinessExpense table.
        // Everything else in total_expenses is treated as per-unit variable cost,
        // so the breakdown automatically picks up whatever custom categories
        // the user records (utilities, maintenance, salaries, taxes, etc.).
        $businessExpenses    = (float) $this->calculateBusinessExpenses($activePeriod, $month, $year);
        $variableTotal       = max(0, $totalExpenses - $businessExpenses);
        $variableCostPerUnit = $currentOccupancy > 0 ? $variableTotal / $currentOccupancy : 0;

        $contributionMarginPerUnit = $avgRentPerApartment - $variableCostPerUnit;

        // Standard break-even: Fixed Costs / Contribution Margin per Unit.
        // Each rented unit contributes (rent − variable cost) toward fixed costs;
        // we need enough units for that contribution to equal business overhead.
        // If CM ≤ 0, every new tenant loses money — no occupancy level breaks even.
        $breakEvenFeasible = $contributionMarginPerUnit > 0 || $businessExpenses <= 0;
        $breakEvenUnits    = $breakEvenFeasible && $contributionMarginPerUnit > 0
            ? round($businessExpenses / $contributionMarginPerUnit, 2)
            : 0;

        // Break-even revenue = the rent collected at break-even occupancy.
        $breakEvenRevenue = $breakEvenFeasible
            ? round($breakEvenUnits * $avgRentPerApartment, 2)
            : 0;

        $currentRevenue = $totalRevenue;
        $safetyMargin   = $currentRevenue - $totalExpenses; // = net profit / loss
        $safetyMarginPercent = $currentRevenue > 0
            ? round(($safetyMargin / $currentRevenue) * 100, 2)
            : 0;

        $isAboveBreakEven = $safetyMargin >= 0;
        $amountNeeded     = max(0, -$safetyMargin);
        $unitsNeeded      = $breakEvenFeasible
            ? max(0, (int) ceil($breakEvenUnits) - $currentOccupancy)
            : 0;

        $businessExpenseBreakdown = $this->getBusinessExpenseBreakdown($month, $year);
        $variableCostBreakdown    = $this->getVariableCostBreakdown($month, $year);

        return [
            'total_apartments' => $totalApartments,
            'avg_rent_per_apartment' => round($avgRentPerApartment, 2),
            'business_expenses' => round($businessExpenses, 2),
            'variable_cost_per_unit' => round($variableCostPerUnit, 2),
            'contribution_margin_per_unit' => round($contributionMarginPerUnit, 2),
            'break_even_units' => $breakEvenUnits,
            'break_even_revenue' => round($breakEvenRevenue, 2),
            'current_occupancy' => $currentOccupancy,
            'current_revenue' => round($currentRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'safety_margin' => round($safetyMargin, 2),
            'safety_margin_percent' => $safetyMarginPercent,
            'is_above_break_even' => $isAboveBreakEven,
            'amount_needed' => round($amountNeeded, 2),
            'units_needed' => $unitsNeeded,
            'business_expense_breakdown' => $businessExpenseBreakdown,
            'variable_cost_breakdown' => $variableCostBreakdown,
            'break_even_feasible' => $breakEvenFeasible,
        ];
    }

    /**
     * Get business expense items for the current month (break-even breakdown).
     */
    private function getBusinessExpenseBreakdown(?int $month = null, ?int $year = null): array
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        $month = $month ?: now()->month;
        $year  = $year  ?: now()->year;

        $query = BusinessExpense::where('user_id', $this->getAdminUserId())
            ->where('billing_month', $month)
            ->where('billing_year', $year);

        if ($activePeriod) {
            $query->where('fiscal_period_id', $activePeriod->id);
        }

        return $query->get()->map(fn ($e) => [
            'label'  => $e->expense_name ?: ($e->category ?: 'Business Expense'),
            'amount' => round((float) $e->amount, 2),
        ])->toArray();
    }

    /**
     * Get detailed breakdown of variable costs by source.
     */
    private function getVariableCostBreakdown(?int $month = null, ?int $year = null): array
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        $month = $month ?: now()->month;
        $year  = $year  ?: now()->year;

        // Everything in Accounts.expense EXCEPT entries that already appear in
        // BusinessExpense (those are counted as business overhead) and refunds.
        $query = Accounts::expense()
            ->forUser($this->getAdminUserId())
            ->whereNotIn('category', [
                Accounts::CAT_BUSINESS_FIXED,
                Accounts::CAT_BUSINESS_VARIABLE,
                Accounts::CAT_DEPOSIT_EXPENSE,
            ])
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year);

        if ($activePeriod) {
            $query->forPeriod($activePeriod->id);
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
     * Total business expenses for the current month (used as the recurring
     * monthly cost in break-even analysis).
     */
    private function calculateBusinessExpenses(?FiscalPeriods $period = null, ?int $month = null, ?int $year = null)
    {
        $activePeriod = $period ?? $this->getActiveFiscalPeriod();
        $month = $month ?: now()->month;
        $year  = $year  ?: now()->year;

        $query = BusinessExpense::where('user_id', $this->getAdminUserId())
            ->where('billing_month', $month)
            ->where('billing_year', $year);

        if ($activePeriod) {
            $query->where('fiscal_period_id', $activePeriod->id);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Variable cost per occupied unit for the selected month.
     *
     * Pulled from Accounts (vendor-side expenses only): utilities_expense,
     * maintenance, other_expense. Tenant utility CHARGES (Utilities.charge_amount)
     * are income — they must not appear here.
     */
    private function calculateVariableCostPerUnit(?FiscalPeriods $period = null, ?int $month = null, ?int $year = null)
    {
        $apartmentIds = $this->scopeApartments()->pluck('id');
        $activePeriod = $period ?? $this->getActiveFiscalPeriod();
        $month = $month ?: now()->month;
        $year  = $year  ?: now()->year;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd   = $monthStart->copy()->endOfMonth();

        $occupiedCount = Rentals::whereIn('apartment_id', $apartmentIds)
            ->where('start_date', '<=', $monthEnd)
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $monthStart);
            })
            ->count();

        if ($occupiedCount === 0) {
            return 0;
        }

        $variableCategories = [
            Accounts::CAT_UTILITIES_EXPENSE,
            Accounts::CAT_MAINTENANCE,
            Accounts::CAT_OTHER_EXPENSE,
        ];

        $query = Accounts::expense()
            ->forUser($this->getAdminUserId())
            ->whereIn('category', $variableCategories)
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year);

        if ($activePeriod) {
            $query->forPeriod($activePeriod->id);
        }

        $variableTotal = (float) $query->sum('amount');

        return $variableTotal / $occupiedCount;
    }

    /**
     * Show record income form — tenant billing management.
     * Auto-shows all tenants with due dates, charges, and payment status.
     */
    public function recordIncome(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
        }

        // Accept month/year from query params, default to current
        $currentMonth = (int) $request->input('month', now()->month);
        $currentYear  = (int) $request->input('year', now()->year);

        // Build a Carbon date for the selected month
        $selectedDate = Carbon::create($currentYear, $currentMonth, 1);

        // Calculate previous and next month
        $prevDate = $selectedDate->copy()->subMonth();
        $nextDate = $selectedDate->copy()->addMonth();

        // Determine if we're viewing the current (real) month, a past month, or a future month
        $isCurrentMonth = ($currentMonth === now()->month && $currentYear === now()->year);
        $isFutureMonth  = $selectedDate->copy()->startOfMonth()->gt(now()->copy()->startOfMonth());
        $isPastMonth    = !$isCurrentMonth && !$isFutureMonth;

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
            ->with(['floor', 'activeFixedExpenses', 'rentals' => function ($q) use ($activePeriod, $currentMonth, $currentYear) {
                $q->where(function ($sq) {
                        $sq->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })
                    ->orderBy('start_date', 'desc')
                    ->with(['tenant', 'payments' => function ($pq) use ($activePeriod) {
                        $pq->where('payment_status', 'paid')
                            ->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);
                    }, 'utilities' => function ($uq) use ($currentMonth, $currentYear) {
                        $uq->where('billing_month', $currentMonth)
                            ->where('billing_year', $currentYear);
                    }]);
            }])
            ->get();

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
                    && $rental->start_date->year  === $currentYear;

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
                    $dueDay  = Carbon::create($currentYear, $currentMonth)->daysInMonth;
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
                $lateFeeAmount = (!$paidThisMonth && $referenceNow->gt($dueDate)) ? ($rental->payments->isEmpty() ? 0 : $lateFees) : 0;
                $totalBill = $rental->rent_amount + $totalUtilities + $totalFixed;

                if (!$paidThisMonth) {
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

        // Recent income records for this fiscal period
        $recentIncome = Accounts::income()
            ->forUser($this->getAdminUserId())
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
            'currentMonth', 'currentYear'
        ));
    }

    /**
     * Add a charge (utility/expense) to a tenant's current month bill.
     */
    public function addTenantCharge(Request $request)
    {
        $validated = $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'charge_type' => 'required|in:electricity,water,internet,parking,trash,other',
            'charge_amount' => 'required|numeric|min:0.01',
            'meter_reading_in' => 'nullable|numeric|min:0',
            'meter_reading_out' => 'nullable|numeric|min:0',
            'billing_month' => 'nullable|integer|min:1|max:12',
            'billing_year' => 'nullable|integer|min:2000|max:2100',
            'note' => 'nullable|string|max:500',
        ]);

        $rental = Rentals::with('tenant')->findOrFail($validated['rental_id']);

        $billingMonth = $validated['billing_month'] ?? now()->month;
        $billingYear = $validated['billing_year'] ?? now()->year;

        // Tenant charges are tracked only in the Utilities table until paid.
        // The Accounts ledger entry is created on payment (checkoutTenant / storeIncome)
        // to avoid double-counting income at both charge and payment time.
        $utilityTypes = ['electricity', 'water', 'internet', 'trash', 'parking', 'other'];

        if (in_array($validated['charge_type'], $utilityTypes, true)) {
            Utilities::create([
                'tenant_id' => $rental->tenant_id,
                'rental_id' => $rental->id,
                'utility_type' => $validated['charge_type'],
                'meter_number' => null,
                'meter_reading_in' => $validated['meter_reading_in'] ?? 0,
                'meter_reading_out' => $validated['meter_reading_out'] ?? 0,
                'charge_amount' => $validated['charge_amount'],
                'billing_month' => $billingMonth,
                'billing_year' => $billingYear,
                'paid_status' => false,
                'paid_at' => null,
            ]);
        }

        $successMsg = ucfirst($validated['charge_type']) . ' charge of $' . number_format($validated['charge_amount'], 2) . ' added for ' . ($rental->tenant->name ?? 'tenant') . '.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $successMsg]);
        }

        return redirect()->back()->with('success', $successMsg);
    }

    /**
     * Remove a charge from a tenant's bill.
     */
    public function removeTenantCharge($chargeId)
    {
        $charge = Utilities::findOrFail($chargeId);

        // Only allow removing unpaid charges
        if ($charge->paid_status) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'Cannot remove a paid charge.'], 422);
            }
            return redirect()->back()->with('error', 'Cannot remove a charge that has already been paid.');
        }

        // Remove any Accounts entry created for this tenant charge
        try {
            \App\Models\Accounts::where('reference_number', 'tenant_charge:' . $charge->id)
                ->whereNull('payment_id')
                ->where('user_id', $this->getAdminUserId())
                ->delete();
        } catch (\Exception $e) {
            // not fatal
        }

        $charge->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->back()->with('success', 'Charge removed successfully.');
    }

    /**
     * Clear all unpaid charges for a rental.
     */
    public function clearTenantCharges($rentalId)
    {
        $rental = Rentals::findOrFail($rentalId);
        $charges = Utilities::where('rental_id', $rentalId)->where('paid_status', false)->get();

        foreach ($charges as $charge) {
            try {
                \App\Models\Accounts::where('reference_number', 'tenant_charge:' . $charge->id)
                    ->whereNull('payment_id')
                    ->where('user_id', $this->getAdminUserId())
                    ->delete();
            } catch (\Exception $e) {}
            $charge->delete();
        }

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->back()->with('success', 'All unpaid charges cleared.');
    }

    /**
     * Checkout / Pay a tenant's full bill (rent + all charges).
     */
    public function checkoutTenant(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $validated = $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'payment_method' => 'required|in:cash,bank',
            'payment_date' => 'required|date',
            'rent_amount' => 'required|numeric|min:0',
            'late_fee' => 'nullable|numeric|min:0',
            'pay_rent' => 'nullable|boolean',
            'pay_utilities' => 'nullable|boolean',
            'transaction_reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        $rental = Rentals::with(['apartment', 'tenant'])->findOrFail($validated['rental_id']);
        $paymentDate = $validated['payment_date'];
        $paymentMethod = $validated['payment_method'];
        $lateFee = $validated['late_fee'] ?? 0;
        $totalPaid = 0;
        $items = [];

        // Pay rent if selected
        if (!empty($validated['pay_rent'])) {
            $rentAmount = $validated['rent_amount'];

            $payment = Payments::create([
                'rental_id' => $rental->id,
                'amount' => $rentAmount,
                'due_date' => $paymentDate,
                'paid_at' => $paymentDate,
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'payment_type' => 'rent',
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'late_fee' => $lateFee,
                'note' => $validated['note'] ?? 'Monthly rent payment',
            ]);

            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => $payment->id,
                'user_id' => $this->getAdminUserId(),
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_RENT_INCOME,
                'description' => '[Apt ' . $rental->apartment->apartment_number . '] Monthly rent',
                'amount' => $rentAmount,
                'transaction_date' => $paymentDate,
                'reference_number' => $validated['transaction_reference'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            // Record late fee as a separate Accounts entry if applicable
            if ($lateFee > 0) {
                Accounts::create([
                    'fiscal_period_id' => $activePeriod->id,
                    'payment_id' => $payment->id,
                    'user_id' => $this->getAdminUserId(),
                    'account_type' => Accounts::TYPE_INCOME,
                    'category' => Accounts::CAT_LATE_FEE_INCOME,
                    'description' => '[Apt ' . $rental->apartment->apartment_number . '] Late fee',
                    'amount' => $lateFee,
                    'transaction_date' => $paymentDate,
                    'reference_number' => $validated['transaction_reference'] ?? null,
                    'note' => 'Late fee',
                ]);
            }

            $totalPaid += $rentAmount + $lateFee;
            $items[] = 'Rent: $' . number_format($rentAmount, 2);
        }

        // Pay utilities if selected
        if (!empty($validated['pay_utilities'])) {
            $unpaidUtilities = Utilities::where('rental_id', $rental->id)
                ->forMonth(now()->month, now()->year)
                ->unpaid()
                ->get();

            if ($unpaidUtilities->isNotEmpty()) {
                $utilityTotal = $unpaidUtilities->sum('charge_amount');

                $payment = Payments::create([
                    'rental_id' => $rental->id,
                    'amount' => $utilityTotal,
                    'due_date' => $paymentDate,
                    'paid_at' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'payment_type' => 'utilities',
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'late_fee' => 0,
                    'note' => 'Utility charges: ' . $unpaidUtilities->pluck('utility_type')->implode(', '),
                ]);

                // Split Accounts entries: electricity+water → utility_income, rest → other_income
                $utilityIncomeTypes = ['electricity', 'water'];
                $otherIncomeTypes   = ['internet', 'parking', 'trash', 'other'];
                $utilIncomeAmt  = $unpaidUtilities->whereIn('utility_type', $utilityIncomeTypes)->sum('charge_amount');
                $otherIncomeAmt = $unpaidUtilities->whereIn('utility_type', $otherIncomeTypes)->sum('charge_amount');

                if ($utilIncomeAmt > 0) {
                    $utilTypes = $unpaidUtilities->whereIn('utility_type', $utilityIncomeTypes)->pluck('utility_type')->unique()->implode(', ');
                    Accounts::create([
                        'fiscal_period_id' => $activePeriod->id,
                        'payment_id'       => $payment->id,
                        'user_id'          => $this->getAdminUserId(),
                        'account_type'     => Accounts::TYPE_INCOME,
                        'category'         => Accounts::CAT_UTILITY_INCOME,
                        'description'      => '[Apt ' . $rental->apartment->apartment_number . '] ' . ucwords($utilTypes),
                        'amount'           => $utilIncomeAmt,
                        'transaction_date' => $paymentDate,
                        'reference_number' => $validated['transaction_reference'] ?? null,
                        'note'             => 'Utilities (electricity/water): ' . $utilTypes,
                    ]);
                }

                if ($otherIncomeAmt > 0) {
                    $otherTypes = $unpaidUtilities->whereIn('utility_type', $otherIncomeTypes)->pluck('utility_type')->unique()->implode(', ');
                    Accounts::create([
                        'fiscal_period_id' => $activePeriod->id,
                        'payment_id'       => $payment->id,
                        'user_id'          => $this->getAdminUserId(),
                        'account_type'     => Accounts::TYPE_INCOME,
                        'category'         => Accounts::CAT_OTHER_INCOME,
                        'description'      => '[Apt ' . $rental->apartment->apartment_number . '] ' . ucwords($otherTypes),
                        'amount'           => $otherIncomeAmt,
                        'transaction_date' => $paymentDate,
                        'reference_number' => $validated['transaction_reference'] ?? null,
                        'note'             => 'Other charges (internet/parking/trash): ' . $otherTypes,
                    ]);
                }

                // Fallback: if neither matched (shouldn't happen), record all as utility_income
                if ($utilIncomeAmt <= 0 && $otherIncomeAmt <= 0 && $utilityTotal > 0) {
                    Accounts::create([
                        'fiscal_period_id' => $activePeriod->id,
                        'payment_id'       => $payment->id,
                        'user_id'          => $this->getAdminUserId(),
                        'account_type'     => Accounts::TYPE_INCOME,
                        'category'         => Accounts::CAT_UTILITY_INCOME,
                        'description'      => '[Apt ' . $rental->apartment->apartment_number . '] Utility charges',
                        'amount'           => $utilityTotal,
                        'transaction_date' => $paymentDate,
                        'reference_number' => $validated['transaction_reference'] ?? null,
                        'note'             => 'Utilities: ' . $unpaidUtilities->pluck('utility_type')->implode(', '),
                    ]);
                }

                // Mark utilities as paid
                foreach ($unpaidUtilities as $utility) {
                    $utility->update([
                        'paid_status' => true,
                        'paid_at' => now(),
                    ]);
                }

                $totalPaid += $utilityTotal;
                $items[] = 'Utilities: $' . number_format($utilityTotal, 2);
            }
        }

        if ($totalPaid === 0) {
            return redirect()->back()->with('error', 'No items selected for payment.');
        }

        $tenantName = $rental->tenant->name ?? 'Tenant';
        $aptNumber = $rental->apartment->apartment_number;

        return redirect()->back()
            ->with('success', "Payment of \${$totalPaid} recorded for {$tenantName} (Apt {$aptNumber}). Items: " . implode(', ', $items));
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
            'floor' => $rental->apartment->floor,
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

    /**
     * Store a new income record from apartment rent payment.
     */
    public function storeIncome(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $validated = $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank',
            'payment_type' => 'required|in:rent,utilities,deposit,other',
            'transaction_date' => 'required|date',
            'transaction_reference' => 'nullable|string|max:255',
            'late_fee' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        $rental = Rentals::with('apartment')->findOrFail($validated['rental_id']);

        // Create the payment record
        $payment = Payments::create([
            'rental_id' => $validated['rental_id'],
            'amount' => $validated['amount'],
            'due_date' => $validated['transaction_date'],
            'paid_at' => $validated['transaction_date'],
            'payment_method' => $validated['payment_method'],
            'payment_status' => 'paid',
            'payment_type' => $validated['payment_type'],
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'late_fee' => $validated['late_fee'] ?? 0,
            'note' => $validated['note'] ?? null,
        ]);

        // Map payment_type to account category (using constants for consistency)
        $category = Accounts::PAYMENT_TYPE_TO_CATEGORY[$validated['payment_type']] ?? Accounts::CAT_OTHER_INCOME;

        // Create the account record linked to fiscal period
        Accounts::create([
            'fiscal_period_id' => $activePeriod->id,
            'payment_id' => $payment->id,
            'user_id' => $this->getAdminUserId(),
            'account_type' => Accounts::TYPE_INCOME,
            'category' => $category,
            'description' => '[Apt ' . $rental->apartment->apartment_number . '] ' . ucfirst($validated['payment_type']) . ' payment',
            'amount' => $validated['amount'],
            'transaction_date' => $validated['transaction_date'],
            'reference_number' => $validated['transaction_reference'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        // Record late fee as a separate Accounts entry if applicable
        $lateFeeAmount = $validated['late_fee'] ?? 0;
        if ($lateFeeAmount > 0) {
            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => $payment->id,
                'user_id' => $this->getAdminUserId(),
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_LATE_FEE_INCOME,
                'description' => '[Apt ' . $rental->apartment->apartment_number . '] Late fee',
                'amount' => $lateFeeAmount,
                'transaction_date' => $validated['transaction_date'],
                'reference_number' => $validated['transaction_reference'] ?? null,
                'note' => 'Late fee for ' . ucfirst($validated['payment_type']),
            ]);
        }

        return redirect()->back()
            ->with('success', ucfirst($validated['payment_type']) . ' income of $' . number_format($validated['amount'], 2) . ' recorded for apartment ' . $rental->apartment->apartment_number . '.');
    }

    /**
     * Store bulk monthly rent income for all selected apartments at once.
     */
    public function storeBulkIncome(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank',
            'apartments' => 'required|array|min:1',
            'apartments.*.rental_id' => 'required|exists:rentals,id',
            'apartments.*.amount' => 'required|numeric|min:0.01',
            'apartments.*.late_fee' => 'nullable|numeric|min:0',
            'apartments.*.selected' => 'nullable|boolean',
        ]);

        $paymentDate = $validated['payment_date'];
        $paymentMethod = $validated['payment_method'];
        $recordedCount = 0;
        $totalAmount = 0;

        foreach ($validated['apartments'] as $aptData) {
            // Only process selected apartments
            if (empty($aptData['selected'])) {
                continue;
            }

            $rental = Rentals::with('apartment')->findOrFail($aptData['rental_id']);
            $amount = $aptData['amount'];
            $lateFee = $aptData['late_fee'] ?? 0;

            // Create the payment record
            $payment = Payments::create([
                'rental_id' => $rental->id,
                'amount' => $amount,
                'due_date' => $paymentDate,
                'paid_at' => $paymentDate,
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'payment_type' => 'rent',
                'transaction_reference' => null,
                'late_fee' => $lateFee,
                'note' => 'Auto-generated monthly rent',
            ]);

            // Create the account record linked to fiscal period
            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => $payment->id,
                'user_id' => $this->getAdminUserId(),
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_RENT_INCOME,
                'description' => '[Apt ' . $rental->apartment->apartment_number . '] Monthly rent',
                'amount' => $amount,
                'transaction_date' => $paymentDate,
                'reference_number' => null,
                'note' => 'Auto-generated monthly rent',
            ]);

            // Record late fee as a separate Accounts entry if applicable
            if ($lateFee > 0) {
                Accounts::create([
                    'fiscal_period_id' => $activePeriod->id,
                    'payment_id' => $payment->id,
                    'user_id' => $this->getAdminUserId(),
                    'account_type' => Accounts::TYPE_INCOME,
                    'category' => Accounts::CAT_LATE_FEE_INCOME,
                    'description' => '[Apt ' . $rental->apartment->apartment_number . '] Late fee',
                    'amount' => $lateFee,
                    'transaction_date' => $paymentDate,
                    'reference_number' => null,
                    'note' => 'Auto-generated late fee',
                ]);
            }

            $recordedCount++;
            $totalAmount += $amount + $lateFee;
        }

        if ($recordedCount === 0) {
            return redirect()->back()
                ->with('error', 'No apartments were selected. Please check at least one apartment.');
        }

        return redirect()->back()
            ->with('success', 'Monthly rent recorded for ' . $recordedCount . ' apartment(s). Total: $' . number_format($totalAmount, 2));
    }

    /**
     * Show record expense form — apartment-centric utility expense recording
     * with monthly breakdown, other expense allocation, and business expenses.
     */
    public function recordExpense()
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
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

        // Recent expense records from Accounts for the selected month
        $recentExpenses = Accounts::expense()
            ->forUser($this->getAdminUserId())
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

        // Other (non-utility) expenses for the selected month
        $otherExpenses = Accounts::expense()
            ->forUser($this->getAdminUserId())
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

        // Business expenses (fixed & variable) for the selected month
        $businessExpenses = BusinessExpense::where('user_id', $this->getAdminUserId())
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

        // Tenants expense collection (utilities + fixed charges billed to tenants) — tracked
        // separately and intentionally excluded from grand total business expenses.
        $tenantsExpenseCollected = $totalExpenses;

        // Grand total of business-side expenses for the selected month
        $grandTotalExpenses = $totalOtherExpenses + $businessTotal;

        $currentMonth = now()->month;
        $currentYear = now()->year;

        return view('supervisor.revenue_expense.record_expense', compact(
            'activePeriod', 'apartments', 'apartmentExpenses', 'apartmentExpensesAll', 'recentExpenses',
            'utilityTypes', 'totalExpenses', 'otherExpenseCategories', 'otherExpenses',
            'totalOtherExpenses', 'businessExpenses', 'businessTotal', 'businessCategories',
            'grandTotalExpenses', 'tenantsExpenseCollected', 'currentMonth', 'currentYear',
            'filterMonth', 'filterYear', 'periodMonths'
        ));
    }

    /**
     * Store a new expense record — saves to both Utilities and Accounts tables.
     */
    public function storeExpense(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $validated = $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'utility_type' => 'required|in:electricity,water,internet,parking,trash,other',
            'charge_amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'meter_reading_in' => 'nullable|numeric|min:0',
            'meter_reading_out' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        $rental = Rentals::with('tenant', 'apartment')->findOrFail($validated['rental_id']);
        $transactionDate = Carbon::parse($validated['transaction_date']);

        // Create Utilities record (operational tracking)
        Utilities::create([
            'tenant_id' => $rental->tenant_id,
            'rental_id' => $rental->id,
            'utility_type' => $validated['utility_type'],
            'meter_reading_in' => $validated['meter_reading_in'] ?? 0,
            'meter_reading_out' => $validated['meter_reading_out'] ?? 0,
            'charge_amount' => $validated['charge_amount'],
            'billing_month' => $transactionDate->month,
            'billing_year' => $transactionDate->year,
            'paid_status' => true,
            'paid_at' => $validated['transaction_date'],
        ]);

        // Create Accounts record (fiscal period financial tracking)
        Accounts::create([
            'fiscal_period_id' => $activePeriod->id,
            'payment_id' => null,
            'user_id' => $this->getAdminUserId(),
            'account_type' => Accounts::TYPE_EXPENSE,
            'category' => Accounts::CAT_UTILITIES_EXPENSE,
            'description' => '[Apt ' . $rental->apartment->apartment_number . '] ' . ucfirst($validated['utility_type']),
            'amount' => $validated['charge_amount'],
            'transaction_date' => $validated['transaction_date'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()->back()
            ->with('success', ucfirst($validated['utility_type']) . ' expense of $' . number_format($validated['charge_amount'], 2) . ' recorded for apartment ' . $rental->apartment->apartment_number . '.');
    }

    /**
     * Store an "other" expense (non-utility, non-business) — saves to Accounts.
     */
    public function storeOtherExpense(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $allowedCategories = [
            'maintenance', 'repairs', 'insurance', 'property_tax', 'management',
            'cleaning', 'security', 'landscaping', 'supplies', 'marketing',
            'legal', 'miscellaneous', 'salaries', 'taxes', 'other_expense',
        ];

        $validated = $request->validate([
            'category' => 'required|string|in:' . implode(',', $allowedCategories),
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ]);

        Accounts::create([
            'fiscal_period_id' => $activePeriod->id,
            'payment_id' => null,
            'user_id' => $this->getAdminUserId(),
            'account_type' => Accounts::TYPE_EXPENSE,
            'category' => $validated['category'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'transaction_date' => $validated['transaction_date'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()->back()
            ->with('success', 'Other expense of $' . number_format($validated['amount'], 2) . ' recorded (' . $validated['description'] . ').');
    }

    /**
     * Delete an other expense record from Accounts.
     */
    public function deleteOtherExpense(Accounts $expense)
    {
        // Verify ownership
        $activePeriod = $this->getActiveFiscalPeriod();
        if (!$activePeriod || $expense->fiscal_period_id !== $activePeriod->id) {
            abort(403);
        }

        $desc = $expense->description;
        $expense->delete();

        return redirect()->back()
            ->with('success', 'Expense "' . $desc . '" has been removed.');
    }

    /**
     * Store a business-level fixed or variable expense.
     */
    public function storeBusinessExpense(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $validated = $request->validate([
            'expense_name' => 'required|string|max:255',
            'category' => 'required|in:electricity,water,trash,internet,legal_fee,tax,loan_payment,salary,other',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'is_recurring' => 'nullable|boolean',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $expenseDate = Carbon::parse($validated['expense_date']);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('business_expenses', 'public');
        }

        BusinessExpense::create([
            'user_id' => $this->getAdminUserId(),
            'fiscal_period_id' => $activePeriod->id,
            'expense_name' => $validated['expense_name'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'billing_month' => $expenseDate->month,
            'billing_year' => $expenseDate->year,
            'is_recurring' => $request->boolean('is_recurring'),
            'note' => $validated['note'] ?? null,
            'attachment' => $attachmentPath,
        ]);

        // Also record in Accounts for fiscal period tracking
        Accounts::create([
            'fiscal_period_id' => $activePeriod->id,
            'payment_id' => null,
            'user_id' => $this->getAdminUserId(),
            'account_type' => Accounts::TYPE_EXPENSE,
            'category' => Accounts::CAT_BUSINESS_VARIABLE,
            'description' => '[Business] ' . $validated['expense_name'],
            'amount' => $validated['amount'],
            'transaction_date' => $validated['expense_date'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()->back()
            ->with('success', 'Business expense "' . $validated['expense_name'] . '" ($' . number_format($validated['amount'], 2) . ') recorded.');
    }

    /**
     * Delete a business expense record.
     */
    public function deleteBusinessExpense(BusinessExpense $businessExpense)
    {
        $name = $businessExpense->expense_name;

        // Remove the corresponding Accounts record
        Accounts::where('user_id', $this->getAdminUserId())
            ->where('fiscal_period_id', $businessExpense->fiscal_period_id)
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->where('category', Accounts::CAT_BUSINESS_VARIABLE)
            ->where('amount', $businessExpense->amount)
            ->where('transaction_date', $businessExpense->expense_date)
            ->where('description', '[Business] ' . $businessExpense->expense_name)
            ->limit(1)
            ->delete();

        if ($businessExpense->attachment) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($businessExpense->attachment);
        }

        $businessExpense->delete();

        return redirect()->back()
            ->with('success', 'Business expense "' . $name . '" has been removed.');
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

    /**
     * Store a new fixed expense for an apartment.
     */
    public function storeFixedExpense(Request $request)
    {
        $validated = $request->validate([
            'apartment_id' => 'required|exists:apartments,id',
            'expense_name' => 'required|string|max:255',
            'expense_type' => 'required|in:parking,internet,trash,other',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:1000',
        ]);

        ApartmentFixedExpense::create([
            'apartment_id' => $validated['apartment_id'],
            'expense_name' => $validated['expense_name'],
            'expense_type' => $validated['expense_type'],
            'amount' => $validated['amount'],
            'is_active' => true,
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()->back()
            ->with('success', $validated['expense_name'] . ' ($' . number_format($validated['amount'], 2) . ') assigned to apartment.');
    }

    /**
     * Toggle a fixed expense on/off.
     */
    public function toggleFixedExpense(ApartmentFixedExpense $fixedExpense)
    {
        $fixedExpense->update(['is_active' => !$fixedExpense->is_active]);

        return redirect()->back()
            ->with('success', $fixedExpense->expense_name . ' has been ' . ($fixedExpense->is_active ? 'activated' : 'deactivated') . '.');
    }

    /**
     * Delete a fixed expense.
     */
    public function deleteFixedExpense(ApartmentFixedExpense $fixedExpense)
    {
        $name = $fixedExpense->expense_name;
        $fixedExpense->delete();

        return redirect()->back()
            ->with('success', $name . ' has been removed.');
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

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
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
            if ($apartment->rentals->isEmpty()) continue;

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
    public function processMonthlyBills(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $validated = $request->validate([
            'billing_date' => 'required|date',
            'bills' => 'required|array|min:1',
            'bills.*.rental_id' => 'required|exists:rentals,id',
            'bills.*.selected' => 'nullable|boolean',
            'bills.*.expenses' => 'nullable|array',
            'bills.*.expenses.*.expense_id' => 'required|exists:apartment_fixed_expenses,id',
            'bills.*.expenses.*.amount' => 'required|numeric|min:0',
            'bills.*.expenses.*.selected' => 'nullable|boolean',
        ]);

        $billingDate = Carbon::parse($validated['billing_date']);
        $recordedCount = 0;
        $totalAmount = 0;

        foreach ($validated['bills'] as $billData) {
            if (empty($billData['selected'])) {
                continue;
            }

            $rental = Rentals::with(['tenant', 'apartment'])->findOrFail($billData['rental_id']);

            if (!isset($billData['expenses'])) continue;

            foreach ($billData['expenses'] as $expData) {
                if (empty($expData['selected'])) continue;

                $fixedExpense = ApartmentFixedExpense::findOrFail($expData['expense_id']);
                $amount = $expData['amount'];

                // Map expense_type to utility_type
                $utilityType = $fixedExpense->expense_type;

                // Check if already billed this month
                $exists = Utilities::where('rental_id', $rental->id)
                    ->where('utility_type', $utilityType)
                    ->where('billing_month', $billingDate->month)
                    ->where('billing_year', $billingDate->year)
                    ->exists();

                if ($exists) continue; // Skip if already billed

                // Create Utilities record
                Utilities::create([
                    'tenant_id' => $rental->tenant_id,
                    'rental_id' => $rental->id,
                    'utility_type' => $utilityType,
                    'meter_reading_in' => 0,
                    'meter_reading_out' => 0,
                    'charge_amount' => $amount,
                    'billing_month' => $billingDate->month,
                    'billing_year' => $billingDate->year,
                    'paid_status' => false,
                    'paid_at' => null,
                ]);

                // Create Accounts record for fiscal tracking
                Accounts::create([
                    'fiscal_period_id' => $activePeriod->id,
                    'payment_id' => null,
                    'user_id' => $this->getAdminUserId(),
                    'account_type' => Accounts::TYPE_EXPENSE,
                    'category' => Accounts::CAT_BUSINESS_FIXED,
                    'description' => '[Apt ' . $rental->apartment->apartment_number . '] ' . $fixedExpense->expense_name . ' (monthly)',
                    'amount' => $amount,
                    'transaction_date' => $billingDate->toDateString(),
                    'note' => 'Auto-generated monthly fixed expense',
                ]);

                $recordedCount++;
                $totalAmount += $amount;
            }
        }

        if ($recordedCount === 0) {
            return redirect()->back()
                ->with('error', 'No new expenses were generated. Expenses may already be billed for this month.');
        }

        return redirect()->back()
            ->with('success', $recordedCount . ' expense(s) generated totaling $' . number_format($totalAmount, 2) . ' for tenants to pay.');
    }

    /**
     * Auto-generate monthly bills for all apartments (quick action).
     * This will create Utilities + Accounts records for any active fixed expense
     * that has not yet been billed for the billing month/year.
     */
    public function autoProcessMonthlyBills(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $billingDate = $request->input('billing_date') ? Carbon::parse($request->input('billing_date')) : now();
        $month = $billingDate->month;
        $year = $billingDate->year;

        $recordedCount = 0;
        $totalAmount = 0;

        $apartments = $this->scopeApartments()
            ->with(['activeFixedExpenses', 'rentals' => function ($q) {
                $q->where(function ($q2) {
                        $q2->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })->with('tenant');
            }])->get();

        foreach ($apartments as $apartment) {
            if ($apartment->rentals->isEmpty()) continue;

            foreach ($apartment->rentals as $rental) {
                foreach ($apartment->activeFixedExpenses as $fe) {
                    $exists = Utilities::where('rental_id', $rental->id)
                        ->where('utility_type', $fe->expense_type)
                        ->where('billing_month', $month)
                        ->where('billing_year', $year)
                        ->exists();

                    if ($exists) continue;

                    Utilities::create([
                        'tenant_id' => $rental->tenant_id,
                        'rental_id' => $rental->id,
                        'utility_type' => $fe->expense_type,
                        'meter_reading_in' => 0,
                        'meter_reading_out' => 0,
                        'charge_amount' => $fe->amount,
                        'billing_month' => $month,
                        'billing_year' => $year,
                        'paid_status' => false,
                        'paid_at' => null,
                    ]);

                    Accounts::create([
                        'fiscal_period_id' => $activePeriod->id,
                        'payment_id' => null,
                        'user_id' => $this->getAdminUserId() ?? null,
                        'account_type' => Accounts::TYPE_EXPENSE,
                        'category' => Accounts::CAT_BUSINESS_FIXED,
                        'description' => '[Apt ' . $apartment->apartment_number . '] ' . $fe->expense_name . ' (monthly)',
                        'amount' => $fe->amount,
                        'transaction_date' => $billingDate->toDateString(),
                        'note' => 'Auto-generated monthly fixed expense',
                    ]);

                    $recordedCount++;
                    $totalAmount += $fe->amount;
                }
            }
        }

        if ($recordedCount === 0) {
            return redirect()->back()
                ->with('error', 'No new expenses were generated. Expenses may already be billed for this month.');
        }

        return redirect()->back()
            ->with('success', $recordedCount . ' expense(s) generated totaling $' . number_format($totalAmount, 2) . ' for tenants to pay.');
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
                $pdf = \PDF::loadView('supervisor.revenue_expense.apartment_summary_pdf', compact('perApartment', 'activePeriod', 'start', 'end', 'summaryOnly', 'wholeNumbers'));
                $filename = 'apartment-summary-' . now()->format('Y-m-d') . '.pdf';
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

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
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
                if ($bestDay === null || $net > $calendarDays[$bestDay]['net']) $bestDay = $d;
                if ($worstDay === null || $net < $calendarDays[$worstDay]['net']) $worstDay = $d;
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

        if (!$activePeriod) {
            return redirect()->route('supervisor.dashboard')
                ->with('warning', 'Please create a fiscal period first.');
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
            $businessExpenses->filter(fn($e) => in_array($e->category, ['electricity', 'utilities_electricity']))->sum('amount')
            + $utilityAccountExpenses->filter(fn($e) => str_contains(strtolower($e->description), 'electricity'))->sum('amount'),
            2
        );

        $waterExpense = round(
            $businessExpenses->filter(fn($e) => in_array($e->category, ['water', 'utilities_water']))->sum('amount')
            + $utilityAccountExpenses->filter(fn($e) => str_contains(strtolower($e->description), 'water'))->sum('amount'),
            2
        );

        $internetExpense = round(
            $businessExpenses->filter(fn($e) => in_array($e->category, ['internet', 'utilities_internet']))->sum('amount')
            + $utilityAccountExpenses->filter(fn($e) => str_contains(strtolower($e->description), 'internet'))->sum('amount'),
            2
        );

        // Tax expense
        $taxExpense = round(
            $businessExpenses->filter(fn($e) => in_array($e->category, ['property_tax', 'tax']))->sum('amount')
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
            $businessExpenses->filter(fn($e) => !in_array($e->category, $countedBusinessCategories))->sum('amount'),
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
