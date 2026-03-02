<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\FiscalPeriods;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\Utilities;
use App\Models\Accounts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display admin dashboard with statistics.
     */
    public function index(): View
    {
        $stats = $this->getStats();
        $fiscalData = $this->getActiveFiscalPeriodData();
        $monthlyChartData = $this->getMonthlyChartData();
        return view('admin.dashboard', ['stats' => $stats, 'fiscalData' => $fiscalData, 'monthlyChartData' => $monthlyChartData]);
    }

    /**
     * Get active fiscal period financial data for dashboard.
     */
    private function getActiveFiscalPeriodData(): array
    {
        $activePeriod = FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'open')
            ->orderBy('opening_date', 'desc')
            ->first();

        if (!$activePeriod) {
            return [
                'has_active_period' => false,
                'period' => null,
                'revenue' => 0,
                'late_fees' => 0,
                'total_income' => 0,
                'expenses' => [],
                'total_expenses' => 0,
                'net_profit' => 0,
                'is_profitable' => false,
                'profit_margin' => 0,
                'opening_balance' => 0,
                'current_balance' => 0,
                'balance_sheet' => ['total_assets' => 0, 'total_liabilities' => 0, 'total_equity' => 0],
                'recent_periods' => [],
                'monthly_revenue' => [],
                'monthly_expenses' => [],
            ];
        }

        // Calculate Revenue from Payments (Rent Income) within fiscal period
        $revenueQuery = Payments::whereHas('rental', function ($query) use ($activePeriod) {
            $query->whereHas('apartment', function ($subQuery) use ($activePeriod) {
                $subQuery->where('supervisor_id', $activePeriod->user_id);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);

        $revenue = $revenueQuery->sum('amount');
        $lateFees = $revenueQuery->sum('late_fee');
        $totalIncome = $revenue + $lateFees;

        // Calculate Expenses from Utilities within fiscal period
        $utilitiesData = Utilities::whereHas('rental', function ($query) use ($activePeriod) {
            $query->whereHas('apartment', function ($subQuery) use ($activePeriod) {
                $subQuery->where('supervisor_id', $activePeriod->user_id);
            });
        })
        ->where('paid_status', true)
        ->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date])
        ->get();

        $expenses = [];
        $totalExpenses = 0;
        foreach ($utilitiesData->groupBy('utility_type') as $type => $items) {
            $typeTotal = $items->sum('charge_amount');
            $expenses[$type] = $typeTotal;
            $totalExpenses += $typeTotal;
        }

        // Add account-based expenses
        $accountExpenses = Accounts::where('user_id', Auth::id())
            ->where('account_type', 'expense')
            ->where('fiscal_period_id', $activePeriod->id)
            ->sum('amount');
        $totalExpenses += $accountExpenses;
        if ($accountExpenses > 0) {
            $expenses['other'] = $accountExpenses;
        }

        // Net profit
        $netProfit = $totalIncome - $totalExpenses;
        $profitMargin = $totalIncome > 0 ? round(($netProfit / $totalIncome) * 100, 2) : 0;

        // Balance sheet summary
        $totalAssets = $activePeriod->balanceSheets()->where('item_type', 'asset')->sum('amount');
        $totalLiabilities = $activePeriod->balanceSheets()->where('item_type', 'liability')->sum('amount');
        $totalEquity = $activePeriod->balanceSheets()->where('item_type', 'equity')->sum('amount');

        // Current balance = opening balance + revenue - expenses
        $currentBalance = $activePeriod->opening_balance + $netProfit;

        // Monthly breakdown for charts (within the fiscal period)
        $monthlyRevenue = $this->getMonthlyRevenue($activePeriod);
        $monthlyExpenses = $this->getMonthlyExpenses($activePeriod);

        // Recent closed periods for comparison
        $recentPeriods = FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'closed')
            ->orderBy('closing_date', 'desc')
            ->take(5)
            ->get();

        return [
            'has_active_period' => true,
            'period' => $activePeriod,
            'revenue' => $revenue,
            'late_fees' => $lateFees,
            'total_income' => $totalIncome,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'is_profitable' => $netProfit > 0,
            'profit_margin' => $profitMargin,
            'opening_balance' => $activePeriod->opening_balance,
            'current_balance' => $currentBalance,
            'balance_sheet' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
            ],
            'recent_periods' => $recentPeriods,
            'monthly_revenue' => $monthlyRevenue,
            'monthly_expenses' => $monthlyExpenses,
        ];
    }

    /**
     * Get monthly revenue breakdown within fiscal period.
     */
    private function getMonthlyRevenue(FiscalPeriods $period): array
    {
        // Direct payment-based revenue
        $payments = Payments::whereHas('rental', function ($query) use ($period) {
            $query->whereHas('apartment', function ($subQuery) use ($period) {
                $subQuery->where('supervisor_id', $period->user_id);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$period->opening_date, $period->closing_date])
        ->selectRaw('YEAR(paid_at) as year, MONTH(paid_at) as month, SUM(amount + late_fee) as total')
        ->groupByRaw('YEAR(paid_at), MONTH(paid_at)')
        ->orderByRaw('YEAR(paid_at), MONTH(paid_at)')
        ->get();

        $result = [];
        foreach ($payments as $payment) {
            $label = date('M Y', mktime(0, 0, 0, $payment->month, 1, $payment->year));
            $result[$label] = round($payment->total, 2);
        }

        return $result;
    }

    /**
     * Get monthly expenses breakdown within fiscal period.
     */
    private function getMonthlyExpenses(FiscalPeriods $period): array
    {
        // Utilities expenses
        $utilities = Utilities::whereHas('rental', function ($query) use ($period) {
            $query->whereHas('apartment', function ($subQuery) use ($period) {
                $subQuery->where('supervisor_id', $period->user_id);
            });
        })
        ->where('paid_status', true)
        ->whereBetween('paid_at', [$period->opening_date, $period->closing_date])
        ->selectRaw('YEAR(paid_at) as year, MONTH(paid_at) as month, SUM(charge_amount) as total')
        ->groupByRaw('YEAR(paid_at), MONTH(paid_at)')
        ->orderByRaw('YEAR(paid_at), MONTH(paid_at)')
        ->get();

        $result = [];
        foreach ($utilities as $utility) {
            $label = date('M Y', mktime(0, 0, 0, $utility->month, 1, $utility->year));
            $result[$label] = round($utility->total, 2);
        }

        // Account-based expenses (recorded via Record Expense form)
        $accountExpenses = Accounts::where('user_id', $period->user_id)
            ->where('fiscal_period_id', $period->id)
            ->where('account_type', 'expense')
            ->selectRaw('YEAR(transaction_date) as year, MONTH(transaction_date) as month, SUM(amount) as total')
            ->groupByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->orderByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->get();

        foreach ($accountExpenses as $expense) {
            $label = date('M Y', mktime(0, 0, 0, $expense->month, 1, $expense->year));
            $result[$label] = round(($result[$label] ?? 0) + $expense->total, 2);
        }

        return $result;
    }

    /**
     * Get last 6 months of revenue & expenses for dashboard charts (independent of fiscal period).
     */
    private function getMonthlyChartData(): array
    {
        $labels = [];
        $revenue = [];
        $expenses = [];
        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();

        // Build 6-month labels
        for ($i = 0; $i < 6; $i++) {
            $d = $sixMonthsAgo->copy()->addMonths($i);
            $labels[] = $d->format('M Y');
            $revenue[$d->format('Y-m')] = 0;
            $expenses[$d->format('Y-m')] = 0;
        }

        // Revenue from actual payments (collected)
        $payments = Payments::where('payment_status', 'paid')
            ->where('paid_at', '>=', $sixMonthsAgo)
            ->selectRaw('DATE_FORMAT(paid_at, "%Y-%m") as ym, SUM(amount) as total_amount, SUM(late_fee) as total_late')
            ->groupByRaw('DATE_FORMAT(paid_at, "%Y-%m")')
            ->get();

        foreach ($payments as $p) {
            if (isset($revenue[$p->ym])) {
                $revenue[$p->ym] = round($p->total_amount + $p->total_late, 2);
            }
        }

        // Account income
        $accountIncome = Accounts::where('user_id', Auth::id())
            ->where('account_type', 'income')
            ->where('transaction_date', '>=', $sixMonthsAgo)
            ->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as ym, SUM(amount) as total')
            ->groupByRaw('DATE_FORMAT(transaction_date, "%Y-%m")')
            ->get();

        foreach ($accountIncome as $ai) {
            if (isset($revenue[$ai->ym])) {
                $revenue[$ai->ym] = round($revenue[$ai->ym] + $ai->total, 2);
            }
        }

        // Expenses from utilities
        $utilities = Utilities::where('paid_status', true)
            ->where('paid_at', '>=', $sixMonthsAgo)
            ->selectRaw('DATE_FORMAT(paid_at, "%Y-%m") as ym, SUM(charge_amount) as total')
            ->groupByRaw('DATE_FORMAT(paid_at, "%Y-%m")')
            ->get();

        foreach ($utilities as $u) {
            if (isset($expenses[$u->ym])) {
                $expenses[$u->ym] = round($u->total, 2);
            }
        }

        // Account expenses
        $accountExpenses = Accounts::where('user_id', Auth::id())
            ->where('account_type', 'expense')
            ->where('transaction_date', '>=', $sixMonthsAgo)
            ->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as ym, SUM(amount) as total')
            ->groupByRaw('DATE_FORMAT(transaction_date, "%Y-%m")')
            ->get();

        foreach ($accountExpenses as $ae) {
            if (isset($expenses[$ae->ym])) {
                $expenses[$ae->ym] = round($expenses[$ae->ym] + $ae->total, 2);
            }
        }

        return [
            'labels' => $labels,
            'revenue' => array_values($revenue),
            'expenses' => array_values($expenses),
        ];
    }

    /**
     * Fetch all dashboard statistics from database.
     */
    private function getStats(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // -- Payment status counts (real data) --
        $paidCount = Payments::where('payment_status', 'paid')
            ->whereMonth('paid_at', $currentMonth)
            ->whereYear('paid_at', $currentYear)
            ->count();
        $pendingCount = Payments::where('payment_status', 'pending')->count();
        $overdueCount = Payments::where('due_date', '<', now())
            ->whereNull('paid_at')
            ->where('payment_status', '!=', 'cancelled')
            ->count();

        // -- Monthly expenses from utilities (current month) --
        $monthlyUtilities = Utilities::where('paid_status', true)
            ->where('billing_month', $currentMonth)
            ->where('billing_year', $currentYear)
            ->sum('charge_amount');

        $monthlyAccountExpenses = Accounts::where('user_id', Auth::id())
            ->where('account_type', 'expense')
            ->whereMonth('transaction_date', $currentMonth)
            ->whereYear('transaction_date', $currentYear)
            ->sum('amount');

        $monthlyExpensesTotal = $monthlyUtilities + $monthlyAccountExpenses;

        // -- Utility breakdown (current month, real data) --
        $utilityBreakdown = Utilities::where('paid_status', true)
            ->where('billing_month', $currentMonth)
            ->where('billing_year', $currentYear)
            ->selectRaw('utility_type, SUM(charge_amount) as total')
            ->groupBy('utility_type')
            ->pluck('total', 'utility_type')
            ->toArray();

        // -- Occupancy by floor (real data) --
        $floors = Floors::with(['apartments'])->orderBy('id')->get();
        $floorLabels = [];
        $floorOccupancy = [];
        foreach ($floors as $floor) {
            $total = $floor->apartments->count();
            $occupied = $floor->apartments->where('status', 'occupied')->count();
            $floorLabels[] = $floor->floor_name ?? 'Floor ' . $floor->id;
            $floorOccupancy[] = $total > 0 ? round(($occupied / $total) * 100, 1) : 0;
        }

        return [
            'floors_count' => Floors::count(),
            'apartments' => [
                'total' => Apartments::count(),
                'available' => Apartments::where('status', 'available')->count(),
                'occupied' => Apartments::where('status', 'occupied')->count(),
                'maintenance' => Apartments::where('status', 'maintenance')->count(),
            ],
            'tenants' => [
                'total' => Tenants::count(),
                'active' => Tenants::where('status', 'active')->count(),
                'inactive' => Tenants::where('status', 'inactive')->count(),
                'pending' => Tenants::where('status', 'pending')->count(),
            ],
            'rentals' => [
                'total' => Rentals::count(),
                'active' => Rentals::where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })->count(),
            ],
            'payments' => [
                'paid' => $paidCount,
                'pending' => $pendingCount,
                'overdue' => $overdueCount,
                'total_collected' => Payments::where('payment_status', 'paid')->sum('amount'),
                'total_pending' => Payments::where('payment_status', 'pending')->sum('amount'),
            ],
            'revenue' => [
                'total_monthly_rent' => Apartments::where('status', 'occupied')->sum('monthly_rent'),
                'collected_this_month' => Payments::where('payment_status', 'paid')
                    ->whereMonth('paid_at', $currentMonth)
                    ->whereYear('paid_at', $currentYear)
                    ->sum('amount'),
                'late_fees_this_month' => Payments::where('payment_status', 'paid')
                    ->whereMonth('paid_at', $currentMonth)
                    ->whereYear('paid_at', $currentYear)
                    ->sum('late_fee'),
            ],
            'expenses' => [
                'monthly_total' => round($monthlyExpensesTotal, 2),
                'utility_breakdown' => $utilityBreakdown,
            ],
            'floor_labels' => $floorLabels,
            'floor_occupancy' => $floorOccupancy,
        ];
    }
}
