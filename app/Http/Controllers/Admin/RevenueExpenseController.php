<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\ApartmentFixedExpense;
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

        // Get average rent per apartment
        $avgRentPerApartment = Rentals::whereIn('apartment_id', $apartments->pluck('id'))
            ->avg('rent_amount');

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
     * 
     * @return float
     */
    private function calculateFixedCosts()
    {
        // Fixed costs include: internet, maintenance costs, insurance, etc.
        // For now, we'll calculate from utilities and other fixed expenses
        
        $monthlyUtilities = Utilities::whereHas('rental', function ($q) {
            $q->wherehas('apartment', function ($q2) {
                $q2->where('supervisor_id', Auth::id())
                    ->orWhereNull('supervisor_id');
            });
        })
            ->where('utility_type', 'internet')
            ->where('paid_status', true)
            ->sum('charge_amount');

        $monthlyOtherFixed = \App\Models\Accounts::where('user_id', Auth::id())
            ->where('account_type', 'expense')
            ->where('category', 'maintenance')
            ->sum('amount');

        return $monthlyUtilities + $monthlyOtherFixed;
    }

    /**
     * Calculate variable cost per unit
     * 
     * @return float
     */
    private function calculateVariableCostPerUnit()
    {
        // Variable costs include: electricity, water, parking per unit
        $apartments = $this->scopeApartments()->count();
        
        if ($apartments == 0) {
            return 0;
        }

        $monthlyVariableCosts = Utilities::whereHas('rental', function ($q) {
            $q->wherehas('apartment', function ($q2) {
                $q2->where('supervisor_id', Auth::id())
                    ->orWhereNull('supervisor_id');
            });
        })
            ->whereIn('utility_type', ['electricity', 'water', 'parking'])
            ->where('paid_status', true)
            ->sum('charge_amount');

        return $monthlyVariableCosts / $apartments;
    }

    /**
     * Show record income form — apartment-centric rent recording.
     */
    public function recordIncome()
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('admin.fiscalperiod.create')
                ->with('warning', 'Please create a fiscal period first.');
        }

        // Get apartments with active rentals only, eager load payments within fiscal period
        $apartments = $this->scopeApartments()
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

        // Calculate per-apartment income summary
        $apartmentSummary = [];
        $totalRentExpected = 0;
        $totalRentCollected = 0;
        $currentMonth = now()->month;
        $currentYear = now()->year;

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

        // Recent income records for this fiscal period
        $recentIncome = Accounts::where('user_id', Auth::id())
            ->where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', 'income')
            ->orderBy('transaction_date', 'desc')
            ->take(10)
            ->get();

        return view('admin.revenue_expense.record_income', compact(
            'activePeriod', 'apartments', 'apartmentSummary', 'recentIncome',
            'totalRentExpected', 'totalRentCollected'
        ));
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
     * Show record expense form — apartment-centric utility expense recording.
     */
    public function recordExpense()
    {
        $activePeriod = $this->getActiveFiscalPeriod();

        if (!$activePeriod) {
            return redirect()->route('admin.fiscalperiod.create')
                ->with('warning', 'Please create a fiscal period first.');
        }

        // Get all apartments with rentals and their utilities within fiscal period
        $apartments = $this->scopeApartments()
            ->with(['floor', 'rentals' => function ($q) use ($activePeriod) {
                $q->orderBy('start_date', 'desc')
                    ->with(['tenant', 'utilities' => function ($uq) use ($activePeriod) {
                        $uq->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);
                    }]);
            }])
            ->get();

        // Calculate per-apartment expense totals
        $apartmentExpenses = [];
        $totalExpenses = 0;

        foreach ($apartments as $apartment) {
            $aptExpense = [
                'apartment' => $apartment,
                'electricity' => 0,
                'water' => 0,
                'internet' => 0,
                'parking' => 0,
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

            $totalExpenses += $aptExpense['total'];
            $apartmentExpenses[] = $aptExpense;
        }

        // Recent expense records
        $recentExpenses = Accounts::where('user_id', Auth::id())
            ->where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', 'expense')
            ->orderBy('transaction_date', 'desc')
            ->take(10)
            ->get();

        $utilityTypes = [
            'electricity' => 'Electricity',
            'water' => 'Water',
            'internet' => 'Internet',
            'parking' => 'Parking',
        ];

        return view('admin.revenue_expense.record_expense', compact(
            'activePeriod', 'apartments', 'apartmentExpenses', 'recentExpenses',
            'utilityTypes', 'totalExpenses'
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
