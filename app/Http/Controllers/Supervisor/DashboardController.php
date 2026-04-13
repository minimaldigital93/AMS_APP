<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\FiscalPeriods;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\TenantLeave;
use App\Models\Utilities;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display supervisor dashboard with statistics and activity.
     */
    public function index(): View
    {
        $apartmentIds = Apartments::pluck('id')->toArray();
        $fiscalData = $this->getFiscalPeriodData($apartmentIds);
        $stats = $this->getStats($apartmentIds, $fiscalData['period'] ?? null);

        // Recent tenant registrations (supervisor's apartments)
        $recentRegistrations = Tenants::whereIn('apartment_id', $apartmentIds)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->with('apartment')
            ->get();

        // Recent departures
        $recentDepartures = TenantLeave::whereIn('apartment_id', $apartmentIds)
            ->orderBy('leave_date', 'desc')
            ->take(5)
            ->with(['tenant', 'apartment'])
            ->get();

        // Recent payments
        $recentPayments = Payments::whereHas('rental', function ($q) use ($apartmentIds) {
            $q->whereIn('apartment_id', $apartmentIds);
        })->where('payment_status', 'paid')
          ->orderBy('paid_at', 'desc')
          ->take(5)
          ->with(['rental.tenant', 'rental.apartment'])
          ->get();

        // Recent transactions from Accounts ledger (same data admin sees, scoped by fiscal period)
        $recentTransactions = collect();
        if ($fiscalData['has_active_period']) {
            $recentTransactions = Accounts::where('fiscal_period_id', $fiscalData['period']->id)
                ->where(function ($q) use ($apartmentIds) {
                    // Income/expenses linked to supervisor's apartments via payment
                    $q->whereHas('payment', function ($pq) use ($apartmentIds) {
                        $pq->whereHas('rental', function ($rq) use ($apartmentIds) {
                            $rq->whereIn('apartment_id', $apartmentIds);
                        });
                    })
                    // OR general property expenses (no payment link)
                    ->orWhere(function ($q2) {
                        $q2->where('account_type', Accounts::TYPE_EXPENSE)
                           ->whereNull('payment_id');
                    });
                })
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->take(15)
                ->get();
        }

        return view('supervisor.dashboard', compact(
            'stats', 'fiscalData', 'recentRegistrations', 'recentDepartures', 'recentPayments', 'recentTransactions'
        ));
    }

    /**
     * Get the admin's active fiscal period data scoped to supervisor's apartments.
     * Reads from Payments for income, Utilities + Accounts for expenses (matching admin approach).
     */
    private function getFiscalPeriodData(array $apartmentIds): array
    {
        $activePeriod = FiscalPeriods::where('status', 'open')
            ->whereHas('user', function ($q) {
                $q->role('admin');
            })
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
                'monthly_revenue' => [],
                'monthly_expenses' => [],
            ];
        }

        // ── Revenue from Payments (scoped to supervisor's apartments) ──
        $paymentScope = fn($q) => $q->whereIn('apartment_id', $apartmentIds);

        $revenue = Payments::whereHas('rental', $paymentScope)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date])
            ->sum('amount');

        $lateFees = Payments::whereHas('rental', $paymentScope)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date])
            ->sum('late_fee');

        $totalIncome = $revenue + $lateFees;

        // ── Expenses from Utilities (scoped to supervisor's apartments) ──
        $utilitiesData = Utilities::whereHas('rental', $paymentScope)
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

        // ── Expenses from Accounts ledger (business expenses recorded by admin) ──
        // Apartment-specific expense entries (linked via payment → rental → apartment)
        $accountApartmentExpenses = Accounts::where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->whereNotNull('payment_id')
            ->whereHas('payment', function ($q) use ($apartmentIds) {
                $q->whereHas('rental', function ($r) use ($apartmentIds) {
                    $r->whereIn('apartment_id', $apartmentIds);
                });
            })
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        foreach ($accountApartmentExpenses as $cat => $amount) {
            $expenses[$cat] = ($expenses[$cat] ?? 0) + $amount;
            $totalExpenses += $amount;
        }

        // General property expenses (no payment link — maintenance, insurance, etc.)
        $accountGeneralExpenses = Accounts::where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->whereNull('payment_id')
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        foreach ($accountGeneralExpenses as $cat => $amount) {
            $expenses[$cat] = ($expenses[$cat] ?? 0) + $amount;
            $totalExpenses += $amount;
        }

        // ── Net profit & balance ──
        $netProfit = $totalIncome - $totalExpenses;
        $profitMargin = $totalIncome > 0 ? round(($netProfit / $totalIncome) * 100, 2) : 0;
        $currentBalance = $activePeriod->opening_balance + $netProfit;

        // ── Monthly revenue breakdown ──
        $monthlyRevenue = Payments::whereHas('rental', $paymentScope)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date])
            ->selectRaw('YEAR(paid_at) as year, MONTH(paid_at) as month, SUM(amount + late_fee) as total')
            ->groupByRaw('YEAR(paid_at), MONTH(paid_at)')
            ->orderByRaw('YEAR(paid_at), MONTH(paid_at)')
            ->get()
            ->mapWithKeys(fn($p) => [date('M Y', mktime(0, 0, 0, $p->month, 1, $p->year)) => round($p->total, 2)])
            ->toArray();

        // ── Monthly expense breakdown (Utilities + Accounts) ──
        $monthlyExpenses = Utilities::whereHas('rental', $paymentScope)
            ->where('paid_status', true)
            ->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date])
            ->selectRaw('YEAR(paid_at) as year, MONTH(paid_at) as month, SUM(charge_amount) as total')
            ->groupByRaw('YEAR(paid_at), MONTH(paid_at)')
            ->orderByRaw('YEAR(paid_at), MONTH(paid_at)')
            ->get()
            ->mapWithKeys(fn($u) => [date('M Y', mktime(0, 0, 0, $u->month, 1, $u->year)) => round($u->total, 2)])
            ->toArray();

        // Add Accounts-based expenses to monthly breakdown
        $accountMonthlyExpenses = Accounts::where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->where(function ($q) use ($apartmentIds) {
                $q->whereHas('payment', function ($pq) use ($apartmentIds) {
                    $pq->whereHas('rental', function ($rq) use ($apartmentIds) {
                        $rq->whereIn('apartment_id', $apartmentIds);
                    });
                })->orWhereNull('payment_id');
            })
            ->selectRaw('YEAR(transaction_date) as year, MONTH(transaction_date) as month, SUM(amount) as total')
            ->groupByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->orderByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->get();

        foreach ($accountMonthlyExpenses as $expense) {
            $label = date('M Y', mktime(0, 0, 0, $expense->month, 1, $expense->year));
            $monthlyExpenses[$label] = round(($monthlyExpenses[$label] ?? 0) + $expense->total, 2);
        }

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
            'monthly_revenue' => $monthlyRevenue,
            'monthly_expenses' => $monthlyExpenses,
        ];
    }

    /**
     * Fetch dashboard statistics scoped to supervisor's assigned apartments.
     */
    private function getStats(array $apartmentIds, ?FiscalPeriods $activePeriod = null): array
    {
        $totalApartments = count($apartmentIds);
        $occupied = Apartments::whereIn('id', $apartmentIds)->where('status', 'occupied')->count();
        $available = Apartments::whereIn('id', $apartmentIds)->where('status', 'available')->count();
        $maintenance = Apartments::whereIn('id', $apartmentIds)->where('status', 'maintenance')->count();

        $occupancyRate = $totalApartments > 0 ? round(($occupied / $totalApartments) * 100, 1) : 0;

        $activeRentals = Rentals::whereIn('apartment_id', $apartmentIds)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })->count();

        $monthlyRentExpected = Apartments::whereIn('id', $apartmentIds)
            ->where('status', 'occupied')
            ->sum('monthly_rent');

        // Use fiscal period date range if available, otherwise fall back to current month
        $revenueQuery = Payments::whereHas('rental', function ($q) use ($apartmentIds) {
            $q->whereIn('apartment_id', $apartmentIds);
        })->where('payment_status', 'paid')
          ->where('payment_type', 'rent');

        if ($activePeriod) {
            $revenueQuery->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);
        } else {
            $revenueQuery->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year);
        }
        $monthlyRentCollected = $revenueQuery->sum('amount');

        $pendingPayments = Payments::whereHas('rental', function ($q) use ($apartmentIds) {
            $q->whereIn('apartment_id', $apartmentIds);
        })->where('payment_status', 'pending')->count();

        $overduePayments = Payments::whereHas('rental', function ($q) use ($apartmentIds) {
            $q->whereIn('apartment_id', $apartmentIds);
        })->where('due_date', '<', now())
          ->whereNull('paid_at')
          ->where('payment_status', '!=', 'cancelled')
          ->count();

        $paidQuery = Payments::whereHas('rental', function ($q) use ($apartmentIds) {
            $q->whereIn('apartment_id', $apartmentIds);
        })->where('payment_status', 'paid');

        if ($activePeriod) {
            $paidQuery->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);
        } else {
            $paidQuery->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year);
        }
        $paidPayments = $paidQuery->count();

        $totalPendingAmount = Payments::whereHas('rental', function ($q) use ($apartmentIds) {
            $q->whereIn('apartment_id', $apartmentIds);
        })->where('payment_status', 'pending')->sum('amount');

        // Floor and apartment breakdown
        $floors = Floors::whereHas('apartments')->count();

        return [
            'apartments' => [
                'total' => $totalApartments,
                'available' => $available,
                'occupied' => $occupied,
                'maintenance' => $maintenance,
            ],
            'floors' => $floors,
            'occupancy_rate' => $occupancyRate,
            'tenants' => [
                'total' => Tenants::whereIn('apartment_id', $apartmentIds)->count(),
                'active' => Tenants::whereIn('apartment_id', $apartmentIds)->where('status', 'active')->count(),
            ],
            'rentals' => [
                'active' => $activeRentals,
            ],
            'payments' => [
                'paid' => $paidPayments,
                'pending' => $pendingPayments,
                'overdue' => $overduePayments,
                'total_pending' => $totalPendingAmount,
            ],
            'revenue' => [
                'expected_monthly' => $monthlyRentExpected,
                'collected_this_month' => $monthlyRentCollected,
                'collection_rate' => $monthlyRentExpected > 0
                    ? round(($monthlyRentCollected / $monthlyRentExpected) * 100, 1) : 0,
            ],
        ];
    }
}
