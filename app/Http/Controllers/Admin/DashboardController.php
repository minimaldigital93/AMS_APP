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
use App\Models\TenantLeave;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{

    public function index(Request $request): View
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        $periodMonths = $activePeriod ? $this->buildPeriodMonths($activePeriod) : [];
        $isFullPeriod = $activePeriod && $request->query('view') === 'all';
        $selectedMonth = $isFullPeriod ? null : $this->resolveSelectedMonth(
            $activePeriod,
            $request->integer('month'),
            $request->integer('year')
        );
        $dateRange = $this->resolveDateRange($activePeriod, $selectedMonth, $isFullPeriod);
        $displayMonth = $selectedMonth ?: $this->resolveDisplayMonth($activePeriod, $periodMonths);

        $stats = $this->getStats($dateRange['start'], $dateRange['end'], $displayMonth);
        $fiscalData = $this->getActiveFiscalPeriodData();
        $calendarData = $isFullPeriod ? null : $this->getCalendarData($displayMonth);

        // Apartments with active rentals for revenue modal
        $apartmentsWithRentals = Apartments::with(['rentals' => function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('end_date')->orWhere('end_date', '>=', now());
                })->with('tenant');
            }])
            ->where('status', 'occupied')
            ->orderBy('apartment_number')
            ->get();

        // Recent transactions
        $recentTransactions = Accounts::where('user_id', Auth::id())
            ->whereBetween('transaction_date', [
                $dateRange['start']->copy()->startOfDay(),
                $dateRange['end']->copy()->endOfDay(),
            ])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        // Per-apartment revenue comparison for the selected month
        $apartmentRevenues = $isFullPeriod ? [] : $this->getApartmentRevenueComparison($displayMonth);

        $monthNavigation = $this->getMonthNavigation($periodMonths, $displayMonth, $isFullPeriod);

        return view('admin.dashboard', compact(
            'stats', 'fiscalData', 'calendarData',
            'activePeriod', 'apartmentsWithRentals', 'recentTransactions', 'apartmentRevenues',
            'selectedMonth', 'periodMonths', 'monthNavigation', 'isFullPeriod', 'displayMonth'
        ));
    }

    public function storeQuickRevenue(Request $request)
    {
        $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'payment_type' => 'required|in:rent,deposit,late_fee,other',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_payment',
            'note' => 'nullable|string|max:500',
        ]);

        $activePeriod = FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'open')->first();

        if (!$activePeriod) {
            return back()->with('error', 'No active fiscal period.');
        }

        $rental = Rentals::with('tenant', 'apartment')->findOrFail($request->rental_id);

        // Create payment record
        $payment = Payments::create([
            'rental_id' => $rental->id,
            'amount' => $request->amount,
            'late_fee' => 0,
            'payment_type' => $request->payment_type,
            'payment_method' => $request->payment_method,
            'payment_status' => 'paid',
            'paid_at' => $request->transaction_date,
            'note' => $request->note,
        ]);

        // Create accounts record
        $category = $request->payment_type === 'rent' ? Accounts::CAT_RENT_INCOME
            : ($request->payment_type === 'late_fee' ? Accounts::CAT_LATE_FEE_INCOME : (
                $request->payment_type === 'deposit' ? Accounts::CAT_DEPOSIT_INCOME : Accounts::CAT_OTHER_INCOME
            ));

        Accounts::create([
            'user_id' => Auth::id(),
            'fiscal_period_id' => $activePeriod->id,
            'payment_id' => $payment->id,
            'account_type' => Accounts::TYPE_INCOME,
            'category' => $category,
            'amount' => $request->amount,
            'description' => ucfirst($request->payment_type) . ' - ' . ($rental->apartment->apartment_number ?? 'N/A') . ' (' . ($rental->tenant->name ?? 'N/A') . ')',
            'transaction_date' => $request->transaction_date,
        ]);

        return back()->with('success', 'Revenue of $' . number_format($request->amount, 2) . ' recorded.');
    }

    public function storeQuickExpense(Request $request)
    {
        $request->validate([
            'category' => 'required|in:' . implode(',', [
                Accounts::CAT_UTILITIES_EXPENSE,
                Accounts::CAT_MAINTENANCE_EXPENSE,
                Accounts::CAT_BUSINESS_FIXED,
                Accounts::CAT_BUSINESS_VARIABLE,
                Accounts::CAT_OTHER_EXPENSE,
            ]),
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        $activePeriod = FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'open')->first();

        if (!$activePeriod) {
            return back()->with('error', 'No active fiscal period.');
        }

        Accounts::create([
            'user_id' => Auth::id(),
            'fiscal_period_id' => $activePeriod->id,
            'account_type' => Accounts::TYPE_EXPENSE,
            'category' => $request->category,
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->transaction_date,
            'note' => $request->note,
        ]);

        return back()->with('success', 'Expense of $' . number_format($request->amount, 2) . ' recorded.');
    }

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

        // Use Accounts as the single accounting source to avoid double counting.
        $incomeRecords = Accounts::where('user_id', Auth::id())
            ->where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', Accounts::TYPE_INCOME)
            ->get();

        $expenseRecords = Accounts::where('user_id', Auth::id())
            ->where('fiscal_period_id', $activePeriod->id)
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->get();

        $revenue = $incomeRecords
            ->where('category', Accounts::CAT_RENT_INCOME)
            ->sum('amount');
        $lateFees = $incomeRecords
            ->where('category', Accounts::CAT_LATE_FEE_INCOME)
            ->sum('amount');
        $totalIncome = $incomeRecords->sum('amount');

        $expenses = $expenseRecords
            ->groupBy('category')
            ->map(fn ($items) => round($items->sum('amount'), 2))
            ->toArray();
        $totalExpenses = $expenseRecords->sum('amount');

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
        $income = Accounts::where('user_id', $period->user_id)
            ->where('fiscal_period_id', $period->id)
            ->where('account_type', Accounts::TYPE_INCOME)
            ->selectRaw('YEAR(transaction_date) as year, MONTH(transaction_date) as month, SUM(amount) as total')
            ->groupByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->orderByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->get();

        $result = [];
        foreach ($income as $item) {
            $label = date('M Y', mktime(0, 0, 0, $item->month, 1, $item->year));
            $result[$label] = round($item->total, 2);
        }

        return $result;
    }

    /**
     * Get monthly expenses breakdown within fiscal period.
     */
    private function getMonthlyExpenses(FiscalPeriods $period): array
    {
        $accountExpenses = Accounts::where('user_id', $period->user_id)
            ->where('fiscal_period_id', $period->id)
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->selectRaw('YEAR(transaction_date) as year, MONTH(transaction_date) as month, SUM(amount) as total')
            ->groupByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->orderByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->get();

        $result = [];
        foreach ($accountExpenses as $expense) {
            $label = date('M Y', mktime(0, 0, 0, $expense->month, 1, $expense->year));
            $result[$label] = round($expense->total, 2);
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

        $accountIncome = Accounts::where('user_id', Auth::id())
            ->where('account_type', Accounts::TYPE_INCOME)
            ->where('transaction_date', '>=', $sixMonthsAgo)
            ->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as ym, SUM(amount) as total')
            ->groupByRaw('DATE_FORMAT(transaction_date, "%Y-%m")')
            ->get();

        foreach ($accountIncome as $ai) {
            if (isset($revenue[$ai->ym])) {
                $revenue[$ai->ym] = round($revenue[$ai->ym] + $ai->total, 2);
            }
        }

        $accountExpenses = Accounts::where('user_id', Auth::id())
            ->where('account_type', Accounts::TYPE_EXPENSE)
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
    private function getStats(Carbon $startDate, Carbon $endDate, Carbon $referenceMonth): array
    {
        $currentMonth = $referenceMonth->month;
        $currentYear = $referenceMonth->year;
        $referenceDate = $referenceMonth->isPast() ? $endDate->copy()->endOfDay() : now();

        // -- Payment status counts (from active rentals, same logic as record_income) --
        $paidCount = 0;
        $pendingCount = 0;
        $overdueCount = 0;
        $totalPendingAmount = 0;

        $activeRentals = Rentals::with(['payments' => function ($pq) {
                $pq->where('payment_status', 'paid');
            }, 'apartment'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate)
                    ->where(function ($q2) use ($startDate) {
                        $q2->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
                    });
            })
            ->get();

        foreach ($activeRentals as $rental) {
            // Check if rent was paid this month
            $paidThisMonth = $rental->payments
                ->filter(function ($p) use ($currentMonth, $currentYear) {
                    return $p->payment_type === 'rent'
                        && Carbon::parse($p->paid_at)->month === $currentMonth
                        && Carbon::parse($p->paid_at)->year === $currentYear;
                })->isNotEmpty();

            // Calculate due date from rental start_date
            $start = $rental->start_date ? Carbon::parse($rental->start_date) : null;
            $dueDay = $start ? $start->day : 1;
            $dueDay = min($dueDay, Carbon::create($currentYear, $currentMonth)->daysInMonth);
            $dueDate = Carbon::create($currentYear, $currentMonth, $dueDay)->endOfDay();

            // If the rental started in the reference month and hasn't paid yet,
            // treat the first partial month as pending (do not mark as overdue).
            if ($start && $start->month === $currentMonth && $start->year === $currentYear && !$paidThisMonth) {
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

        // Accounting totals use Accounts only.
        $monthlyRevenueAccounts = Accounts::where('user_id', Auth::id())
            ->where('account_type', Accounts::TYPE_INCOME)
            ->whereBetween('transaction_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->get();

        $monthlyCollected = $monthlyRevenueAccounts
            ->where('category', '!=', Accounts::CAT_LATE_FEE_INCOME)
            ->sum('amount');
        $monthlyLateFees = $monthlyRevenueAccounts
            ->where('category', Accounts::CAT_LATE_FEE_INCOME)
            ->sum('amount');
        $monthlyTotalRevenue = $monthlyCollected + $monthlyLateFees;

        $monthlyExpenseAccounts = Accounts::where('user_id', Auth::id())
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->whereBetween('transaction_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->get();

        $monthlyUtilities = $monthlyExpenseAccounts
            ->where('category', Accounts::CAT_UTILITIES_EXPENSE)
            ->sum('amount');
        $monthlyAccountExpenses = $monthlyExpenseAccounts
            ->where('category', '!=', Accounts::CAT_UTILITIES_EXPENSE)
            ->sum('amount');
        $monthlyExpensesTotal = $monthlyExpenseAccounts->sum('amount');

        // -- Utility breakdown (current month, all consumption regardless of payment status) --
        $utilityBreakdown = Utilities::where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('paid_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                    ->orWhere(function ($query2) use ($startDate, $endDate) {
                        $query2->whereRaw('(billing_year * 100 + billing_month) >= ?', [$startDate->year * 100 + $startDate->month])
                            ->whereRaw('(billing_year * 100 + billing_month) <= ?', [$endDate->year * 100 + $endDate->month]);
                    });
            })
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

        // -- Rentals expiring soon (next 30 days) --
        $expiringSoon = Rentals::with(['tenant', 'apartment'])
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->orderBy('end_date')
            ->get();

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
            'leases' => [
                'expiring_soon' => $expiringSoon,
            ],
            'payments' => [
                'paid' => $paidCount,
                'pending' => $pendingCount,
                'overdue' => $overdueCount,
                'total_collected' => Payments::where('payment_status', 'paid')
                    ->whereBetween('paid_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                    ->sum('amount'),
                'total_pending' => $totalPendingAmount,
            ],
            'revenue' => [
                'total_monthly' => round($monthlyTotalRevenue, 2),
                'total_monthly_rent' => Apartments::where('status', 'occupied')->sum('monthly_rent'),
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
                'account_breakdown' => Accounts::where('user_id', Auth::id())
                    ->where('account_type', Accounts::TYPE_EXPENSE)
                    ->where('category', '!=', Accounts::CAT_UTILITIES_EXPENSE)
                    ->whereBetween('transaction_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                    ->selectRaw('category, SUM(amount) as total')
                    ->groupBy('category')
                    ->pluck('total', 'category')
                    ->toArray(),
            ],
            'floor_labels' => $floorLabels,
            'floor_occupancy' => $floorOccupancy,
            'tenants_on_leave' => TenantLeave::count(),
        ];
    }

    /**
     * Compute expected vs collected rent grouped by floor, with apartment breakdown.
     */
    private function getApartmentRevenueComparison(Carbon $selectedMonth): array
    {
        $currentMonth = $selectedMonth->month;
        $currentYear = $selectedMonth->year;

        $floors = Floors::with(['apartments' => function ($q) {
                $q->select('id', 'floor_id', 'apartment_number', 'monthly_rent', 'status')
                  ->orderBy('apartment_number');
            }])
            ->orderBy('id')
            ->get();

        $result = [];

        foreach ($floors as $floor) {
            $floorExpected = 0;
            $floorActual = 0;
            $apartments = [];

            foreach ($floor->apartments as $apt) {
                $expected = (float) ($apt->monthly_rent ?? 0);

                $actual = (float) Payments::whereHas('rental', function ($q) use ($apt) {
                        $q->where('apartment_id', $apt->id);
                    })
                    ->where('payment_status', 'paid')
                    ->where('payment_type', 'rent')
                    ->whereMonth('paid_at', $currentMonth)
                    ->whereYear('paid_at', $currentYear)
                    ->sum('amount');

                $percentage = $expected > 0 ? round(($actual / $expected) * 100, 1) : 0;

                $floorExpected += $expected;
                $floorActual += $actual;

                $apartments[] = [
                    'apartment' => $apt->apartment_number ?? "Apt {$apt->id}",
                    'expected' => round($expected, 2),
                    'actual' => round($actual, 2),
                    'percentage' => $percentage,
                    'status' => $apt->status,
                ];
            }

            $floorPct = $floorExpected > 0 ? round(($floorActual / $floorExpected) * 100, 1) : 0;

            $result[] = [
                'floor' => $floor->floor_name ?? "Floor {$floor->id}",
                'expected' => round($floorExpected, 2),
                'actual' => round($floorActual, 2),
                'percentage' => $floorPct,
                'apartments' => $apartments,
            ];
        }

        return $result;
    }

    /**
     * Get calendar data for current month (revenue & expense).
     */
    private function getCalendarData(Carbon $selectedMonth): array
    {
        $startOfMonth = $selectedMonth->copy()->startOfMonth();
        $endOfMonth = $selectedMonth->copy()->endOfMonth();

        // Daily totals are ledger-based to match Revenue & Expense screens.
        $dailyIncome = Accounts::where('user_id', Auth::id())
            ->where('account_type', Accounts::TYPE_INCOME)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('DATE(transaction_date) as day, SUM(amount) as total_income, COUNT(*) as tx_count')
            ->groupByRaw('DATE(transaction_date)')
            ->get()
            ->keyBy('day');

        $dailyAccountExpenses = Accounts::where('user_id', Auth::id())
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('DATE(transaction_date) as day, SUM(amount) as total_expense, COUNT(*) as tx_count')
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

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = $startOfMonth->copy()->day($d)->toDateString();
            $income = $dailyIncome[$dateStr]->total_income ?? 0;
            $expense = $dailyAccountExpenses[$dateStr]->total_expense ?? 0;
            $net = $income - $expense;
            $txCount = ($dailyIncome[$dateStr]->tx_count ?? 0)
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
            }
        }

        $monthNet = $monthTotalIncome - $monthTotalExpense;

        return [
            'startOfMonth' => $startOfMonth,
            'firstDayOfWeek' => $firstDayOfWeek,
            'daysInMonth' => $daysInMonth,
            'calendarDays' => $calendarDays,
            'monthTotalIncome' => $monthTotalIncome,
            'monthTotalExpense' => $monthTotalExpense,
            'monthNet' => $monthNet,
            'bestDay' => $bestDay,
        ];
    }

    private function getActiveFiscalPeriod(): ?FiscalPeriods
    {
        return FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'open')
            ->orderBy('opening_date', 'desc')
            ->first();
    }

    private function buildPeriodMonths(FiscalPeriods $period): array
    {
        $months = [];
        $cursor = Carbon::parse($period->opening_date)->startOfMonth();
        $end = Carbon::parse($period->closing_date)->endOfMonth();

        while ($cursor->lte($end)) {
            $months[] = [
                'month' => $cursor->month,
                'year' => $cursor->year,
                'label' => $cursor->format('F Y'),
            ];
            $cursor->addMonth();
        }

        return $months;
    }

    private function resolveSelectedMonth(?FiscalPeriods $activePeriod, ?int $month, ?int $year): Carbon
    {
        if ($activePeriod) {
            $periodMonths = $this->buildPeriodMonths($activePeriod);

            if ($month && $year && $month >= 1 && $month <= 12) {
                foreach ($periodMonths as $periodMonth) {
                    if ($periodMonth['month'] === $month && $periodMonth['year'] === $year) {
                        return Carbon::create($year, $month, 1)->startOfMonth();
                    }
                }
            }

            foreach ($periodMonths as $periodMonth) {
                if ($periodMonth['month'] === now()->month && $periodMonth['year'] === now()->year) {
                    return now()->startOfMonth();
                }
            }

            if (!empty($periodMonths)) {
                return Carbon::create($periodMonths[0]['year'], $periodMonths[0]['month'], 1)->startOfMonth();
            }
        }

        if ($month && $year && $month >= 1 && $month <= 12) {
            return Carbon::create($year, $month, 1)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function resolveDateRange(?FiscalPeriods $activePeriod, ?Carbon $selectedMonth, bool $isFullPeriod): array
    {
        if ($activePeriod && $isFullPeriod) {
            return [
                'start' => Carbon::parse($activePeriod->opening_date)->startOfDay(),
                'end' => Carbon::parse($activePeriod->closing_date)->endOfDay(),
            ];
        }

        $month = $selectedMonth ?: now()->startOfMonth();

        return [
            'start' => $month->copy()->startOfMonth(),
            'end' => $month->copy()->endOfMonth(),
        ];
    }

    private function resolveDisplayMonth(?FiscalPeriods $activePeriod, array $periodMonths): Carbon
    {
        if ($activePeriod) {
            foreach ($periodMonths as $periodMonth) {
                if ($periodMonth['month'] === now()->month && $periodMonth['year'] === now()->year) {
                    return now()->startOfMonth();
                }
            }

            if (!empty($periodMonths)) {
                return Carbon::create($periodMonths[0]['year'], $periodMonths[0]['month'], 1)->startOfMonth();
            }
        }

        return now()->startOfMonth();
    }

    private function getMonthNavigation(array $periodMonths, Carbon $selectedMonth, bool $isFullPeriod): array
    {
        $currentIndex = null;

        foreach ($periodMonths as $index => $periodMonth) {
            if ($periodMonth['month'] === $selectedMonth->month && $periodMonth['year'] === $selectedMonth->year) {
                $currentIndex = $index;
                break;
            }
        }

        return [
            'previousMonth' => !$isFullPeriod && $currentIndex !== null && $currentIndex > 0 ? $periodMonths[$currentIndex - 1] : null,
            'nextMonth' => !$isFullPeriod && $currentIndex !== null && $currentIndex < count($periodMonths) - 1 ? $periodMonths[$currentIndex + 1] : null,
            'isCurrentMonth' => $selectedMonth->month === now()->month && $selectedMonth->year === now()->year,
            'isFullPeriod' => $isFullPeriod,
            'currentMonthInPeriod' => collect($periodMonths)->first(function ($periodMonth) {
                return $periodMonth['month'] === now()->month && $periodMonth['year'] === now()->year;
            }),
        ];
    }
}
