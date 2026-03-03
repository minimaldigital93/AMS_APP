<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\ApartmentFixedExpense;
use App\Models\BusinessExpense;
use App\Models\FiscalPeriods;
use App\Models\Payments;
use App\Models\Utilities;
use App\Models\Rentals;
use App\Models\Apartments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class RevenueExpenseController extends Controller
{
    /**
     * Display revenue and expense analysis scoped to active fiscal period.
     * All sub-pages (income, expense, fixed costs, bills, break-even) are consolidated here.
     */
    public function index()
    {
        // Allow switching fiscal periods via ?period=ID
        if (request()->has('period')) {
            $activePeriod = FiscalPeriods::where('user_id', Auth::id())
                ->where('id', request('period'))
                ->first();
        }
        if (!isset($activePeriod) || !$activePeriod) {
            $activePeriod = $this->getActiveFiscalPeriod();
        }
        
        if (!$activePeriod) {
            return redirect()->route('admin.fiscalperiod.create')
                ->with('warning', 'Please create a fiscal period first to track revenue and expenses.');
        }

        // Monthly filter: ?month=3&year=2026
        $filterMonth = request('month') ? (int) request('month') : null;
        $filterYear = request('year') ? (int) request('year') : null;

        // Determine date range for calculations
        if ($filterMonth && $filterYear) {
            $filterStart = Carbon::create($filterYear, $filterMonth, 1)->startOfMonth();
            $filterEnd = $filterStart->copy()->endOfMonth();
            // Clamp to fiscal period bounds
            $startDate = $filterStart->lt($activePeriod->opening_date) ? $activePeriod->opening_date : $filterStart;
            $endDate = $filterEnd->gt($activePeriod->closing_date) ? $activePeriod->closing_date : $filterEnd;
        } else {
            $startDate = $activePeriod->opening_date;
            $endDate = $activePeriod->closing_date;
        }

        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Build list of months within fiscal period for the filter dropdown
        $periodMonths = [];
        $mCursor = Carbon::parse($activePeriod->opening_date)->startOfMonth();
        $mEnd = Carbon::parse($activePeriod->closing_date)->endOfMonth();
        while ($mCursor->lte($mEnd)) {
            $periodMonths[] = [
                'month' => $mCursor->month,
                'year' => $mCursor->year,
                'label' => $mCursor->format('F Y'),
            ];
            $mCursor->addMonth();
        }

        // ===== DASHBOARD DATA =====
        $revenueExpenseData = $this->getRevenueExpenseData($startDate, $endDate);
        $revenueExpenseData['activePeriod'] = $activePeriod;
        $revenueExpenseData['filterMonth'] = $filterMonth;
        $revenueExpenseData['filterYear'] = $filterYear;
        $revenueExpenseData['periodMonths'] = $periodMonths;
        
        $revenueExpenseData['fiscalPeriods'] = FiscalPeriods::where('user_id', Auth::id())
            ->orderBy('opening_date', 'desc')
            ->get();

        $allApartments = $this->scopeApartments()->get();
        $revenueExpenseData['totalApartments'] = $allApartments->count();
        $revenueExpenseData['occupiedCount'] = $allApartments->where('status', 'occupied')->count();
        $revenueExpenseData['vacantCount'] = $allApartments->where('status', '!=', 'occupied')->count();
        $revenueExpenseData['occupancyRate'] = $allApartments->count() > 0
            ? round(($allApartments->where('status', 'occupied')->count() / $allApartments->count()) * 100, 1)
            : 0;

        $revenueExpenseData['expectedMonthlyRent'] = Rentals::whereHas('tenant')
            ->whereHas('apartment', function ($q) {
                $q->where(function ($sq) {
                    $sq->where('supervisor_id', Auth::id())->orWhereNull('supervisor_id');
                });
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->sum('rent_amount');

        // ===== RECORD INCOME DATA =====
        $incomeApartments = $this->scopeApartments()
            ->with(['floor', 'rentals' => function ($q) use ($activePeriod) {
                $q->where(function ($sq) {
                        $sq->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })
                    ->orderBy('start_date', 'desc')
                    ->with(['tenant', 'payments' => function ($pq) use ($activePeriod) {
                        $pq->where('payment_status', 'paid')
                            ->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);
                    }]);
            }])
            ->get();

        $apartmentSummary = [];
        $totalRentExpected = 0;
        $totalRentCollected = 0;

        foreach ($incomeApartments as $apartment) {
            foreach ($apartment->rentals as $rental) {
                $collected = $rental->payments->sum('amount');
                $lateFees = $rental->payments->sum('late_fee');
                $totalRentCollected += $collected + $lateFees;
                $totalRentExpected += $rental->rent_amount;

                $paidThisMonth = $rental->payments
                    ->filter(function ($p) use ($currentMonth, $currentYear) {
                        return $p->payment_type === 'rent'
                            && Carbon::parse($p->paid_at)->month === $currentMonth
                            && Carbon::parse($p->paid_at)->year === $currentYear;
                    })->isNotEmpty();

                $apartmentSummary[] = [
                    'apartment' => $apartment,
                    'rental' => $rental,
                    'monthly_rent' => $rental->rent_amount,
                    'collected' => $collected,
                    'late_fees' => $lateFees,
                    'total_collected' => $collected + $lateFees,
                    'payment_count' => $rental->payments->count(),
                    'paid_this_month' => $paidThisMonth,
                ];
            }
        }

        $recentIncome = Accounts::where('user_id', Auth::id())
            ->where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', 'income')
            ->orderBy('transaction_date', 'desc')
            ->take(10)
            ->get();

        $revenueExpenseData['apartments'] = $incomeApartments;
        $revenueExpenseData['apartmentSummary'] = $apartmentSummary;
        $revenueExpenseData['recentIncome'] = $recentIncome;
        $revenueExpenseData['totalRentExpected'] = $totalRentExpected;
        $revenueExpenseData['totalRentCollected'] = $totalRentCollected;

        // ===== RECORD EXPENSE DATA =====
        $expenseApartments = $this->scopeApartments()
            ->with(['floor', 'rentals' => function ($q) use ($activePeriod) {
                $q->orderBy('start_date', 'desc')
                    ->with(['tenant', 'utilities' => function ($uq) use ($activePeriod) {
                        $uq->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);
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

        $recentExpenses = Accounts::where('user_id', Auth::id())
            ->where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', 'expense')
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
        $revenueExpenseData = array_merge($revenueExpenseData, $this->calculateBreakEvenPoint());

        return view('admin.revenue_expense.index', $revenueExpenseData);
    }

    /**
     * Display break-even point analysis scoped to active fiscal period.
     */
    public function breakEvenPoint()
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        
        if (!$activePeriod) {
            return redirect()->route('admin.fiscalperiod.create')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $data = $this->calculateBreakEvenPoint();
        $data['activePeriod'] = $activePeriod;
        
        return view('admin.revenue_expense.break_event', $data);
    }

    /**
     * Get the active (most recent open) fiscal period.
     */
    private function getActiveFiscalPeriod(): ?FiscalPeriods
    {
        return FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'open')
            ->orderBy('opening_date', 'desc')
            ->first();
    }

    /**
     * Scope apartments to current user (supervisor_id matches OR supervisor_id is null).
     */
    private function scopeApartments()
    {
        return Apartments::where(function ($q) {
            $q->where('supervisor_id', Auth::id())
              ->orWhereNull('supervisor_id');
        });
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
     * Calculate total income from tenant payments
     * 
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function calculateIncome($startDate = null, $endDate = null)
    {
        $query = Payments::whereHas('rental', function ($q) {
            $q->whereHas('apartment', function ($q2) {
                $q2->where('supervisor_id', Auth::id())
                    ->orWhereNull('supervisor_id');
            });
        })->where('payment_status', 'paid');

        if ($startDate && $endDate) {
            $query->whereBetween('paid_at', [$startDate, $endDate]);
        }

        $paidPayments = $query->get();
        
        $totalIncome = $paidPayments->sum('amount');
        $lateFeesIncome = $paidPayments->sum('late_fee');
        $totalIncomeWithFees = $totalIncome + $lateFeesIncome;

        return [
            'rent_income' => $totalIncome,
            'late_fees' => $lateFeesIncome,
            'total_income' => $totalIncomeWithFees,
            'payment_count' => $paidPayments->count(),
            'average_payment' => $paidPayments->count() > 0 ? round($totalIncome / $paidPayments->count(), 2) : 0,
        ];
    }

    /**
     * Calculate all expenses including utilities and other costs
     * 
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function calculateExpenses($startDate = null, $endDate = null)
    {
        // Get utilities expenses
        $utilitiesQuery = Utilities::whereHas('rental', function ($q) {
            $q->wherehas('apartment', function ($q2) {
                $q2->where('supervisor_id', Auth::id())
                    ->orWhereNull('supervisor_id');
            });
        });

        if ($startDate && $endDate) {
            $utilitiesQuery->whereBetween('paid_at', [$startDate, $endDate]);
        }

        $utilities = $utilitiesQuery->get();

        // Calculate electricity expense - use charge_amount directly (consistent with per-apartment)
        $electricity = $utilities->where('utility_type', 'electricity')->sum('charge_amount');

        // Calculate water expense
        $water = $utilities->where('utility_type', 'water')->sum('charge_amount');

        // Calculate internet expense
        $internet = $utilities->where('utility_type', 'internet')->sum('charge_amount');

        // Calculate parking expense (if stored separately, otherwise included in utilities)
        $parking = $utilities->where('utility_type', 'parking')->sum('charge_amount');

        // Other maintenance expenses can be added from accounts
        $otherExpenses = \App\Models\Accounts::where('user_id', Auth::id())
            ->where('account_type', 'expense')
            ->where('category', '!=', 'utilities_expense');

        if ($startDate && $endDate) {
            $otherExpenses->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        $otherExpensesTotal = $otherExpenses->sum('amount');

        $totalExpenses = $electricity + $water + $internet + $parking + $otherExpensesTotal;

        return [
            'electricity' => round($electricity, 2),
            'water' => round($water, 2),
            'internet' => round($internet, 2),
            'parking' => round($parking, 2),
            'other_expenses' => round($otherExpensesTotal, 2),
            'total_expenses' => round($totalExpenses, 2),
            'expense_count' => $utilities->count(),
        ];
    }

    /**
     * Calculate summary including profit/loss
     * 
     * @param array $income
     * @param array $expenses
     * @return array
     */
    public function calculateSummary($income, $expenses)
    {
        $netProfit = $income['total_income'] - $expenses['total_expenses'];
        $profitMargin = $income['total_income'] > 0 
            ? round(($netProfit / $income['total_income']) * 100, 2)
            : 0;

        return [
            'total_income' => $income['total_income'],
            'total_expenses' => $expenses['total_expenses'],
            'net_profit' => round($netProfit, 2),
            'profit_margin' => $profitMargin,
            'is_profitable' => $netProfit > 0,
        ];
    }

    /**
     * Calculate per-apartment revenue and expense breakdown.
     */
    private function calculatePerApartmentData($startDate = null, $endDate = null)
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $apartments = $this->scopeApartments()
            ->with(['floor', 'activeFixedExpenses', 'rentals' => function ($q) use ($startDate, $endDate) {
                $q->whereHas('tenant') // Only rentals with non-deleted tenants
                    ->with([
                    'tenant',
                    'payments' => function ($pq) use ($startDate, $endDate) {
                        $pq->where('payment_status', 'paid');
                        if ($startDate && $endDate) {
                            $pq->whereBetween('paid_at', [$startDate, $endDate]);
                        }
                    },
                    'utilities' => function ($uq) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $uq->whereBetween('paid_at', [$startDate, $endDate]);
                        }
                    }
                ]);
            }])
            ->get();

        $perApartment = [];
        foreach ($apartments as $apartment) {
            $income = 0;
            $expenses = 0;
            $expenseBreakdown = ['electricity' => 0, 'water' => 0, 'internet' => 0, 'parking' => 0];
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

                // Current month rent progress
                $monthPayments = $rental->payments->filter(function ($p) use ($currentMonth, $currentYear) {
                    return $p->payment_type === 'rent'
                        && Carbon::parse($p->paid_at)->month === $currentMonth
                        && Carbon::parse($p->paid_at)->year === $currentYear;
                });
                $rentPaid = $monthPayments->sum('amount');
                $rentDue = $rental->rent_amount;
                $rentPercent = $rentDue > 0 ? min(round(($rentPaid / $rentDue) * 100, 1), 100) : 0;
                $rentStatus = $rentPercent >= 100 ? 'paid' : ($rentPercent > 0 ? 'partial' : 'unpaid');

                foreach ($rental->utilities as $utility) {
                    $type = $utility->utility_type;
                    if (isset($expenseBreakdown[$type])) {
                        $expenseBreakdown[$type] += $utility->charge_amount;
                    }
                    $expenses += $utility->charge_amount;
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
                'tenant' => $tenantName ?: 'Vacant',
                'has_active_rental' => $hasActiveRental,
                'monthly_rent' => $apartment->monthly_rent,
                'income' => round($income, 2),
                'expenses' => round($expenses, 2),
                'fixed_expenses' => round($fixedExpTotal, 2),
                'tenant_net' => round($income + $expenses, 2),           // Net: Tenant → Owner (rent + utilities)
                'owner_expenses' => round($fixedExpTotal, 2),            // Owner → Vendor
                'net' => round($income + $expenses - $fixedExpTotal, 2),
                'expense_breakdown' => $expenseBreakdown,
                'status' => $apartment->status,
                'rent_percent' => $rentPercent,
                'rent_paid' => round($rentPaid, 2),
                'rent_due' => round($rentDue, 2),
                'rent_status' => $hasActiveRental ? $rentStatus : 'none',
            ];
        }

        return $perApartment;
    }

    /**
     * Calculate break-even point
     * 
     * @return array
     */
    public function calculateBreakEvenPoint()
    {
        // Get all apartments supervised by current user
        $apartments = $this->scopeApartments()->get();
        $totalApartments = $apartments->count();

        // Get average rent per apartment (avg returns null when no records exist)
        $avgRentPerApartment = Rentals::whereIn('apartment_id', $apartments->pluck('id'))
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->avg('rent_amount') ?? 0;

        // Get fixed costs (monthly)
        $fixedCosts = $this->calculateFixedCosts();

        // Get variable costs per apartment/unit
        $variableCostPerUnit = $this->calculateVariableCostPerUnit();

        // Calculate break-even quantity
        // Break-even point (units) = Fixed Costs / (Price per Unit - Variable Cost per Unit)
        $contributionMarginPerUnit = $avgRentPerApartment - $variableCostPerUnit;
        
        $breakEvenUnits = $contributionMarginPerUnit > 0 
            ? round($fixedCosts / $contributionMarginPerUnit, 2)
            : 0;

        // Break-even revenue
        $breakEvenRevenue = $breakEvenUnits * $avgRentPerApartment;

        // Current occupancy and revenue
        $currentOccupancy = Rentals::whereIn('apartment_id', $apartments->pluck('id'))
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->count();

        $currentRevenue = $currentOccupancy * $avgRentPerApartment;

        // Safety margin
        $safetyMargin = $currentRevenue - $breakEvenRevenue;
        $safetyMarginPercent = $currentRevenue > 0 
            ? round(($safetyMargin / $currentRevenue) * 100, 2)
            : 0;

        return [
            'total_apartments' => $totalApartments,
            'avg_rent_per_apartment' => round($avgRentPerApartment, 2),
            'fixed_costs' => round($fixedCosts, 2),
            'variable_cost_per_unit' => round($variableCostPerUnit, 2),
            'contribution_margin_per_unit' => round($contributionMarginPerUnit, 2),
            'break_even_units' => $breakEvenUnits,
            'break_even_revenue' => round($breakEvenRevenue, 2),
            'current_occupancy' => $currentOccupancy,
            'current_revenue' => round($currentRevenue, 2),
            'safety_margin' => round($safetyMargin, 2),
            'safety_margin_percent' => $safetyMarginPercent,
            'is_above_break_even' => $currentRevenue > $breakEvenRevenue,
        ];
    }

    /**
     * Calculate fixed costs (monthly)
     * Uses ApartmentFixedExpense for recurring monthly fixed costs (internet, parking, trash, etc.)
     * 
     * @return float
     */
    private function calculateFixedCosts()
    {
        $apartmentIds = $this->scopeApartments()->pluck('id');

        // Sum all active fixed expenses across supervised apartments
        $monthlyFixedExpenses = ApartmentFixedExpense::whereIn('apartment_id', $apartmentIds)
            ->where('is_active', true)
            ->sum('amount');

        // Also include any fixed maintenance/insurance expenses from Accounts for current month
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $monthlyOtherFixed = \App\Models\Accounts::where('user_id', Auth::id())
            ->where('account_type', 'expense')
            ->where('category', 'maintenance')
            ->whereMonth('transaction_date', $currentMonth)
            ->whereYear('transaction_date', $currentYear)
            ->sum('amount');

        return $monthlyFixedExpenses + $monthlyOtherFixed;
    }

    /**
     * Calculate variable cost per unit (monthly average)
     * Uses current month utility charges divided by occupied apartments
     * 
     * @return float
     */
    private function calculateVariableCostPerUnit()
    {
        $apartmentIds = $this->scopeApartments()->pluck('id');
        
        // Count occupied apartments (active rentals)
        $occupiedCount = Rentals::whereIn('apartment_id', $apartmentIds)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->count();

        if ($occupiedCount == 0) {
            return 0;
        }

        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Variable costs: electricity, water, parking for current month
        $monthlyVariableCosts = Utilities::whereHas('rental', function ($q) use ($apartmentIds) {
            $q->whereIn('apartment_id', $apartmentIds);
        })
            ->whereIn('utility_type', ['electricity', 'water', 'parking'])
            ->where('billing_month', $currentMonth)
            ->where('billing_year', $currentYear)
            ->sum('charge_amount');

        return $monthlyVariableCosts / $occupiedCount;
    }

    /**
     * Show record income form — tenant billing management.
     * Auto-shows all tenants with due dates, charges, and payment status.
     */
    public function recordIncome(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('admin.fiscalperiod.create')
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

                // Calculate due date: use the day from rental start_date each month
                $dueDay = $rental->start_date ? $rental->start_date->day : 1;
                $dueDay = min($dueDay, Carbon::create($currentYear, $currentMonth)->daysInMonth);
                $dueDate = Carbon::create($currentYear, $currentMonth, $dueDay);

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
                    'status' => $status,
                    'paid_this_month' => $paidThisMonth,
                    'utilities' => $utilityCharges,
                    'total_utilities' => $totalUtilities,
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

        // Recent income records for this fiscal period
        $recentIncome = Accounts::where('user_id', Auth::id())
            ->where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', 'income')
            ->orderBy('transaction_date', 'desc')
            ->take(10)
            ->get();

        return view('admin.revenue_expense.record_income', compact(
            'activePeriod', 'apartments', 'apartmentSummary', 'tenantBills', 'recentIncome',
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
            'charge_type' => 'required|in:electricity,water,internet,parking,cleaning,other',
            'charge_amount' => 'required|numeric|min:0.01',
            'meter_reading_in' => 'nullable|numeric|min:0',
            'meter_reading_out' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        $rental = Rentals::with('tenant')->findOrFail($validated['rental_id']);

        Utilities::create([
            'tenant_id' => $rental->tenant_id,
            'rental_id' => $rental->id,
            'utility_type' => $validated['charge_type'],
            'meter_number' => null,
            'meter_reading_in' => $validated['meter_reading_in'] ?? 0,
            'meter_reading_out' => $validated['meter_reading_out'] ?? 0,
            'charge_amount' => $validated['charge_amount'],
            'billing_month' => now()->month,
            'billing_year' => now()->year,
            'paid_status' => false,
            'paid_at' => null,
        ]);

        return redirect()->back()
            ->with('success', ucfirst($validated['charge_type']) . ' charge of $' . number_format($validated['charge_amount'], 2) . ' added for ' . ($rental->tenant->name ?? 'tenant') . '.');
    }

    /**
     * Remove a charge from a tenant's bill.
     */
    public function removeTenantCharge($chargeId)
    {
        $charge = Utilities::findOrFail($chargeId);

        // Only allow removing unpaid charges
        if ($charge->paid_status) {
            return redirect()->back()->with('error', 'Cannot remove a charge that has already been paid.');
        }

        $charge->delete();

        return redirect()->back()->with('success', 'Charge removed successfully.');
    }

    /**
     * Checkout / Pay a tenant's full bill (rent + all charges).
     */
    public function checkoutTenant(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('admin.fiscalperiod.create')
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
                'user_id' => Auth::id(),
                'account_type' => 'income',
                'category' => 'rent_income',
                'description' => '[Apt ' . $rental->apartment->apartment_number . '] Monthly rent',
                'amount' => $rentAmount + $lateFee,
                'transaction_date' => $paymentDate,
                'reference_number' => $validated['transaction_reference'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            $totalPaid += $rentAmount + $lateFee;
            $items[] = 'Rent: $' . number_format($rentAmount, 2);
        }

        // Pay utilities if selected
        if (!empty($validated['pay_utilities'])) {
            $unpaidUtilities = Utilities::where('rental_id', $rental->id)
                ->where('billing_month', now()->month)
                ->where('billing_year', now()->year)
                ->where('paid_status', false)
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

                Accounts::create([
                    'fiscal_period_id' => $activePeriod->id,
                    'payment_id' => $payment->id,
                    'user_id' => Auth::id(),
                    'account_type' => 'income',
                    'category' => 'utility_income',
                    'description' => '[Apt ' . $rental->apartment->apartment_number . '] Utility charges',
                    'amount' => $utilityTotal,
                    'transaction_date' => $paymentDate,
                    'reference_number' => $validated['transaction_reference'] ?? null,
                    'note' => 'Utilities: ' . $unpaidUtilities->pluck('utility_type')->implode(', '),
                ]);

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

        return view('admin.revenue_expense.tenant_bill_print', $billData);
    }

    /**
     * Store a new income record from apartment rent payment.
     */
    public function storeIncome(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('admin.fiscalperiod.create')
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

        // Map payment_type to account category
        $categoryMap = [
            'rent' => 'rent_income',
            'utilities' => 'utility_income',
            'deposit' => 'deposit_income',
            'other' => 'other_income',
        ];

        // Create the account record linked to fiscal period
        Accounts::create([
            'fiscal_period_id' => $activePeriod->id,
            'payment_id' => $payment->id,
            'user_id' => Auth::id(),
            'account_type' => 'income',
            'category' => $categoryMap[$validated['payment_type']] ?? 'other_income',
            'description' => '[Apt ' . $rental->apartment->apartment_number . '] ' . ucfirst($validated['payment_type']) . ' payment',
            'amount' => $validated['amount'] + ($validated['late_fee'] ?? 0),
            'transaction_date' => $validated['transaction_date'],
            'reference_number' => $validated['transaction_reference'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

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
            return redirect()->route('admin.fiscalperiod.create')
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
                'user_id' => Auth::id(),
                'account_type' => 'income',
                'category' => 'rent_income',
                'description' => '[Apt ' . $rental->apartment->apartment_number . '] Monthly rent',
                'amount' => $amount + $lateFee,
                'transaction_date' => $paymentDate,
                'reference_number' => null,
                'note' => 'Auto-generated monthly rent',
            ]);

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
            return redirect()->route('admin.fiscalperiod.create')
                ->with('warning', 'Please create a fiscal period first.');
        }

        // Monthly filter: ?month=3&year=2026
        $filterMonth = request('month') ? (int) request('month') : null;
        $filterYear = request('year') ? (int) request('year') : null;

        // If no month filter, default to the current month
        if (!$filterMonth || !$filterYear) {
            $filterMonth = now()->month;
            $filterYear = now()->year;
        }

        // Determine date range for the selected month, clamped to fiscal period
        $filterStart = Carbon::create($filterYear, $filterMonth, 1)->startOfMonth();
        $filterEnd = $filterStart->copy()->endOfMonth();
        $startDate = $filterStart->lt(Carbon::parse($activePeriod->opening_date)) ? Carbon::parse($activePeriod->opening_date) : $filterStart;
        $endDate = $filterEnd->gt(Carbon::parse($activePeriod->closing_date)) ? Carbon::parse($activePeriod->closing_date) : $filterEnd;

        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Build list of months within fiscal period for navigation
        $periodMonths = [];
        $mCursor = Carbon::parse($activePeriod->opening_date)->startOfMonth();
        $mEnd = Carbon::parse($activePeriod->closing_date)->endOfMonth();
        while ($mCursor->lte($mEnd)) {
            $periodMonths[] = [
                'month' => $mCursor->month,
                'year' => $mCursor->year,
                'label' => $mCursor->format('F Y'),
            ];
            $mCursor->addMonth();
        }

        // Get all apartments with rentals and their utilities for the selected month
        $apartments = $this->scopeApartments()
            ->with(['floor', 'activeFixedExpenses', 'rentals' => function ($q) use ($startDate, $endDate) {
                $q->orderBy('start_date', 'desc')
                    ->with(['tenant', 'utilities' => function ($uq) use ($startDate, $endDate) {
                        $uq->whereBetween('paid_at', [$startDate, $endDate]);
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

        // Recent expense records from Accounts for the selected month
        $recentExpenses = Accounts::where('user_id', Auth::id())
            ->where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'desc')
            ->take(10)
            ->get();

        $utilityTypes = [
            'electricity' => 'Electricity',
            'water' => 'Water',
            'internet' => 'Internet',
            'parking' => 'Parking',
        ];

        // Other expense categories (non-utility)
        $otherExpenseCategories = [
            'maintenance' => 'Maintenance & Repairs',
            'insurance' => 'Insurance',
            'property_tax' => 'Property Tax',
            'management' => 'Property Management',
            'cleaning' => 'Cleaning Services',
            'security' => 'Security',
            'landscaping' => 'Landscaping',
            'supplies' => 'Supplies & Materials',
            'marketing' => 'Marketing & Advertising',
            'legal' => 'Legal & Professional Fees',
            'miscellaneous' => 'Miscellaneous',
        ];

        // Other (non-utility) expenses for the selected month
        $otherExpenses = Accounts::where('user_id', Auth::id())
            ->where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', 'expense')
            ->where('category', '!=', 'utilities_expense')
            ->where('category', '!=', 'business_fixed')
            ->where('category', '!=', 'business_variable')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'desc')
            ->get();

        $totalOtherExpenses = $otherExpenses->sum('amount');

        // Business expenses (fixed & variable) for the selected month
        $businessExpenses = BusinessExpense::where('user_id', Auth::id())
            ->where('fiscal_period_id', $activePeriod->id)
            ->where('billing_month', $filterMonth)
            ->where('billing_year', $filterYear)
            ->orderBy('expense_date', 'desc')
            ->get();

        $businessFixedTotal = $businessExpenses->where('cost_type', 'fixed')->sum('amount');
        $businessVariableTotal = $businessExpenses->where('cost_type', 'variable')->sum('amount');
        $businessTotal = $businessFixedTotal + $businessVariableTotal;

        // Business expense categories
        $businessCategories = [
            'building_maintenance' => 'Building Maintenance',
            'insurance' => 'Insurance Premium',
            'property_tax' => 'Property Tax',
            'mortgage' => 'Mortgage / Loan Payment',
            'management_fee' => 'Management Fee',
            'security' => 'Security Service',
            'cleaning' => 'Common Area Cleaning',
            'landscaping' => 'Landscaping / Grounds',
            'elevator' => 'Elevator Maintenance',
            'pest_control' => 'Pest Control',
            'accounting' => 'Accounting / Bookkeeping',
            'legal' => 'Legal Fees',
            'marketing' => 'Marketing / Advertising',
            'supplies' => 'Office / Building Supplies',
            'license' => 'License & Permits',
            'depreciation' => 'Depreciation',
            'other' => 'Other',
        ];

        // Grand total of all expenses for the selected month
        $grandTotalExpenses = $totalExpenses + $totalOtherExpenses + $businessTotal;

        return view('admin.revenue_expense.record_expense', compact(
            'activePeriod', 'apartments', 'apartmentExpenses', 'recentExpenses',
            'utilityTypes', 'totalExpenses', 'otherExpenseCategories', 'otherExpenses',
            'totalOtherExpenses', 'businessExpenses', 'businessFixedTotal',
            'businessVariableTotal', 'businessTotal', 'businessCategories',
            'grandTotalExpenses', 'currentMonth', 'currentYear',
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
            return redirect()->route('admin.fiscalperiod.create')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $validated = $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'utility_type' => 'required|in:electricity,water,internet,parking',
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
            'user_id' => Auth::id(),
            'account_type' => 'expense',
            'category' => 'utilities_expense',
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
            return redirect()->route('admin.fiscalperiod.create')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ]);

        Accounts::create([
            'fiscal_period_id' => $activePeriod->id,
            'payment_id' => null,
            'user_id' => Auth::id(),
            'account_type' => 'expense',
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
            return redirect()->route('admin.fiscalperiod.create')
                ->with('warning', 'Please create a fiscal period first.');
        }

        $validated = $request->validate([
            'expense_name' => 'required|string|max:255',
            'cost_type' => 'required|in:fixed,variable',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'is_recurring' => 'nullable|boolean',
            'note' => 'nullable|string|max:1000',
        ]);

        $expenseDate = Carbon::parse($validated['expense_date']);

        BusinessExpense::create([
            'user_id' => Auth::id(),
            'fiscal_period_id' => $activePeriod->id,
            'expense_name' => $validated['expense_name'],
            'cost_type' => $validated['cost_type'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'billing_month' => $expenseDate->month,
            'billing_year' => $expenseDate->year,
            'is_recurring' => $request->boolean('is_recurring'),
            'note' => $validated['note'] ?? null,
        ]);

        // Also record in Accounts for fiscal period tracking
        $costLabel = $validated['cost_type'] === 'fixed' ? 'Fixed' : 'Variable';
        Accounts::create([
            'fiscal_period_id' => $activePeriod->id,
            'payment_id' => null,
            'user_id' => Auth::id(),
            'account_type' => 'expense',
            'category' => 'business_' . $validated['cost_type'],
            'description' => '[Business ' . $costLabel . '] ' . $validated['expense_name'],
            'amount' => $validated['amount'],
            'transaction_date' => $validated['expense_date'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()->back()
            ->with('success', $costLabel . ' business expense "' . $validated['expense_name'] . '" ($' . number_format($validated['amount'], 2) . ') recorded.');
    }

    /**
     * Delete a business expense record.
     */
    public function deleteBusinessExpense(BusinessExpense $businessExpense)
    {
        $name = $businessExpense->expense_name;
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

        return view('admin.revenue_expense.fixed_expenses', compact('apartments'));
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
            return redirect()->route('admin.fiscalperiod.create')
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

        return view('admin.revenue_expense.generate_bills', compact(
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
            return redirect()->route('admin.fiscalperiod.create')
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
                    'user_id' => Auth::id(),
                    'account_type' => 'expense',
                    'category' => 'utilities_expense',
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
     * Monthly calendar view showing daily income and expenses.
     */
    public function monthlyCalendar(Request $request)
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('admin.fiscalperiod.create')
                ->with('warning', 'Please create a fiscal period first.');
        }

        // Determine month/year from query or default to current
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Fetch daily income (payments)
        $dailyIncome = Payments::whereHas('rental', function ($q) {
                $q->whereHas('apartment', function ($q2) {
                    $q2->where('supervisor_id', Auth::id())
                        ->orWhereNull('supervisor_id');
                });
            })
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('DATE(paid_at) as day, SUM(amount) as total_income, SUM(late_fee) as total_late_fee, COUNT(*) as tx_count')
            ->groupByRaw('DATE(paid_at)')
            ->get()
            ->keyBy('day');

        // Fetch daily expenses from utilities
        $dailyUtilities = Utilities::whereHas('rental', function ($q) {
                $q->whereHas('apartment', function ($q2) {
                    $q2->where('supervisor_id', Auth::id())
                        ->orWhereNull('supervisor_id');
                });
            })
            ->where('paid_status', true)
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('DATE(paid_at) as day, SUM(charge_amount) as total_expense, COUNT(*) as tx_count')
            ->groupByRaw('DATE(paid_at)')
            ->get()
            ->keyBy('day');

        // Fetch daily expenses from accounts
        $dailyAccountExpenses = Accounts::where('user_id', Auth::id())
            ->where('account_type', 'expense')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('DATE(transaction_date) as day, SUM(amount) as total_expense, COUNT(*) as tx_count')
            ->groupByRaw('DATE(transaction_date)')
            ->get()
            ->keyBy('day');

        // Fetch daily account income
        $dailyAccountIncome = Accounts::where('user_id', Auth::id())
            ->where('account_type', 'income')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
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
            $income = ($dailyIncome[$dateStr]->total_income ?? 0)
                    + ($dailyIncome[$dateStr]->total_late_fee ?? 0)
                    + ($dailyAccountIncome[$dateStr]->total_income ?? 0);
            $expense = ($dailyUtilities[$dateStr]->total_expense ?? 0)
                     + ($dailyAccountExpenses[$dateStr]->total_expense ?? 0);
            $net = $income - $expense;
            $txCount = ($dailyIncome[$dateStr]->tx_count ?? 0)
                     + ($dailyUtilities[$dateStr]->tx_count ?? 0)
                     + ($dailyAccountExpenses[$dateStr]->tx_count ?? 0)
                     + ($dailyAccountIncome[$dateStr]->tx_count ?? 0);

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

        return view('admin.revenue_expense.monthly_calendar', compact(
            'activePeriod', 'startOfMonth', 'endOfMonth', 'month', 'year',
            'firstDayOfWeek', 'daysInMonth', 'calendarDays',
            'monthTotalIncome', 'monthTotalExpense', 'monthNet',
            'bestDay', 'worstDay',
            'prevMonth', 'nextMonth'
        ));
    }
}
