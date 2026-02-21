<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
     * Display revenue and expense analysis
     */
    public function index()
    {
        $revenueExpenseData = $this->getRevenueExpenseData();
        
        return view('admin.revenue_expense.index', $revenueExpenseData);
    }

    /**
     * Display break-even point analysis
     */
    public function breakEvenPoint()
    {
        $data = $this->calculateBreakEvenPoint();
        
        return view('admin.revenue_expense.break_event', $data);
    }

    /**
     * Get all revenue and expense data
     * 
     * @return array
     */
    public function getRevenueExpenseData()
    {
        $income = $this->calculateIncome();
        $expenses = $this->calculateExpenses();
        $summary = $this->calculateSummary($income, $expenses);

        return compact('income', 'expenses', 'summary');
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
                $q2->where('supervisor_id', Auth::id());
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
                $q2->where('supervisor_id', Auth::id());
            });
        });

        if ($startDate && $endDate) {
            $utilitiesQuery->whereBetween('billing_month', [$startDate, $endDate]);
        }

        $utilities = $utilitiesQuery->get();

        // Calculate electricity expense (meter_reading_out - meter_reading_in) * price_per_unit
        $electricity = $utilities->where('utility_type', 'electricity')->sum(function ($u) {
            $consumption = ($u->meter_reading_out - $u->meter_reading_in);
            return $consumption * ($u->charge_amount / max(($u->meter_reading_out - $u->meter_reading_in), 1));
        });

        // Calculate water expense
        $water = $utilities->where('utility_type', 'water')->sum('charge_amount');

        // Calculate internet expense
        $internet = $utilities->where('utility_type', 'internet')->sum('charge_amount');

        // Calculate parking expense (if stored separately, otherwise included in utilities)
        $parking = $utilities->where('utility_type', 'parking')->sum('charge_amount');

        // Other maintenance expenses can be added from accounts
        $otherExpenses = \App\Models\Accounts::where('user_id', Auth::id())
            ->where('account_type', 'expense')
            ->where('category', '!=', 'utilities');

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
     * Calculate break-even point
     * 
     * @return array
     */
    public function calculateBreakEvenPoint()
    {
        // Get all apartments supervised by current user
        $apartments = Apartments::where('supervisor_id', Auth::id())->get();
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
                $q2->where('supervisor_id', Auth::id());
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
        $apartments = Apartments::where('supervisor_id', Auth::id())->count();
        
        if ($apartments == 0) {
            return 0;
        }

        $monthlyVariableCosts = Utilities::whereHas('rental', function ($q) {
            $q->wherehas('apartment', function ($q2) {
                $q2->where('supervisor_id', Auth::id());
            });
        })
            ->whereIn('utility_type', ['electricity', 'water', 'parking'])
            ->where('paid_status', true)
            ->sum('charge_amount');

        return $monthlyVariableCosts / $apartments;
    }

    /**
     * Get record income view
     */
    public function recordIncome()
    {
        return view('admin.revenue_expense.record_income');
    }

    /**
     * Get record expense view
     */
    public function recordExpense()
    {
        return view('admin.revenue_expense.record_expense');
    }
}
