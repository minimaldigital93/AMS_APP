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
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{

    public function index(): View
    {
        $stats = $this->getStats();
        $fiscalData = $this->getActiveFiscalPeriodData();
        $calendarData = $this->getCalendarData();

        // Active fiscal period for quick recording
        $activePeriod = FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'open')
            ->orderBy('opening_date', 'desc')
            ->first();

        // Apartments with active rentals for revenue modal
        $apartmentsWithRentals = Apartments::with(['rentals' => function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('end_date')->orWhere('end_date', '>=', now());
                })->with('tenant');
            }])
            ->where(function ($q) {
                $q->where('supervisor_id', Auth::id())->orWhereNull('supervisor_id');
            })
            ->where('status', 'occupied')
            ->orderBy('apartment_number')
            ->get();

        // Recent transactions
        $recentTransactions = Accounts::where('user_id', Auth::id())
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        // Per-apartment revenue comparison (expected vs collected for current month)
        $apartmentRevenues = $this->getApartmentRevenueComparison();

        return view('admin.dashboard', compact(
            'stats', 'fiscalData', 'calendarData',
            'activePeriod', 'apartmentsWithRentals', 'recentTransactions', 'apartmentRevenues'
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
            : ($request->payment_type === 'late_fee' ? Accounts::CAT_LATE_FEE_INCOME : Accounts::CAT_OTHER_INCOME);

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
        $payments = Payments::whereHas('rental', function ($query) {
                $query->whereHas('apartment', function ($subQuery) {
                    $subQuery->where('supervisor_id', Auth::id())
                             ->orWhereNull('supervisor_id');
                });
            })
            ->where('payment_status', 'paid')
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
        $utilities = Utilities::whereHas('rental', function ($query) {
                $query->whereHas('apartment', function ($subQuery) {
                    $subQuery->where('supervisor_id', Auth::id())
                             ->orWhereNull('supervisor_id');
                });
            })
            ->where('paid_status', true)
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

        // -- Payment status counts (from active rentals, same logic as record_income) --
        $paidCount = 0;
        $pendingCount = 0;
        $overdueCount = 0;
        $totalPendingAmount = 0;

        $activeRentals = Rentals::with(['payments' => function ($pq) {
                $pq->where('payment_status', 'paid');
            }, 'apartment'])
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->whereHas('apartment', function ($q) {
                $q->where('supervisor_id', Auth::id())
                  ->orWhereNull('supervisor_id');
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
            $dueDay = $rental->start_date ? Carbon::parse($rental->start_date)->day : 1;
            $dueDay = min($dueDay, Carbon::create($currentYear, $currentMonth)->daysInMonth);
            $dueDate = Carbon::create($currentYear, $currentMonth, $dueDay);

            if ($paidThisMonth) {
                $paidCount++;
            } elseif (now()->gt($dueDate)) {
                $overdueCount++;
                $totalPendingAmount += $rental->rent_amount;
            } else {
                $pendingCount++;
                $totalPendingAmount += $rental->rent_amount;
            }
        }

        // -- Monthly expenses from utilities (current month) --
        $monthlyUtilities = Utilities::whereHas('rental', function ($query) {
                $query->whereHas('apartment', function ($subQuery) {
                    $subQuery->where('supervisor_id', Auth::id())
                             ->orWhereNull('supervisor_id');
                });
            })
            ->where('paid_status', true)
            ->where('billing_month', $currentMonth)
            ->where('billing_year', $currentYear)
            ->sum('charge_amount');

        $monthlyAccountExpenses = Accounts::where('user_id', Auth::id())
            ->where('account_type', 'expense')
            ->whereMonth('transaction_date', $currentMonth)
            ->whereYear('transaction_date', $currentYear)
            ->sum('amount');

        $monthlyExpensesTotal = $monthlyUtilities + $monthlyAccountExpenses;

        // -- Utility breakdown (current month, all consumption regardless of payment status) --
        $utilityBreakdown = Utilities::whereHas('rental', function ($query) {
                $query->whereHas('apartment', function ($subQuery) {
                    $subQuery->where('supervisor_id', Auth::id())
                             ->orWhereNull('supervisor_id');
                });
            })
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

        // -- Rentals expiring soon (next 30 days) --
        $expiringSoon = Rentals::with(['tenant', 'apartment'])
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->whereHas('apartment', function ($q) {
                $q->where('supervisor_id', Auth::id())
                  ->orWhereNull('supervisor_id');
            })
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
                'total_collected' => Payments::whereHas('rental', function ($q) {
                        $q->whereHas('apartment', function ($sq) {
                            $sq->where('supervisor_id', Auth::id())
                               ->orWhereNull('supervisor_id');
                        });
                    })
                    ->where('payment_status', 'paid')
                    ->whereMonth('paid_at', $currentMonth)
                    ->whereYear('paid_at', $currentYear)
                    ->sum('amount'),
                'total_pending' => $totalPendingAmount,
            ],
            'revenue' => [
                'total_monthly_rent' => Apartments::where('status', 'occupied')->sum('monthly_rent'),
                'collected_this_month' => Payments::whereHas('rental', function ($q) {
                        $q->whereHas('apartment', function ($sq) {
                            $sq->where('supervisor_id', Auth::id())
                               ->orWhereNull('supervisor_id');
                        });
                    })
                    ->where('payment_status', 'paid')
                    ->whereMonth('paid_at', $currentMonth)
                    ->whereYear('paid_at', $currentYear)
                    ->sum('amount'),
                'late_fees_this_month' => Payments::whereHas('rental', function ($q) {
                        $q->whereHas('apartment', function ($sq) {
                            $sq->where('supervisor_id', Auth::id())
                               ->orWhereNull('supervisor_id');
                        });
                    })
                    ->where('payment_status', 'paid')
                    ->whereMonth('paid_at', $currentMonth)
                    ->whereYear('paid_at', $currentYear)
                    ->sum('late_fee'),
                'by_type' => Payments::whereHas('rental', function ($q) {
                        $q->whereHas('apartment', function ($sq) {
                            $sq->where('supervisor_id', Auth::id())
                               ->orWhereNull('supervisor_id');
                        });
                    })
                    ->where('payment_status', 'paid')
                    ->whereMonth('paid_at', $currentMonth)
                    ->whereYear('paid_at', $currentYear)
                    ->selectRaw('payment_type, SUM(amount) as total')
                    ->groupBy('payment_type')
                    ->pluck('total', 'payment_type')
                    ->toArray(),
                'archived_deposits' => Tenants::onlyTrashed()->sum('deposit'),
            ],
            'expenses' => [
                'monthly_total' => round($monthlyExpensesTotal, 2),
                'utilities_total' => round($monthlyUtilities, 2),
                'account_total' => round($monthlyAccountExpenses, 2),
                'utility_breakdown' => $utilityBreakdown,
                'account_breakdown' => Accounts::where('user_id', Auth::id())
                    ->where('account_type', 'expense')
                    ->whereMonth('transaction_date', $currentMonth)
                    ->whereYear('transaction_date', $currentYear)
                    ->selectRaw('category, SUM(amount) as total')
                    ->groupBy('category')
                    ->pluck('total', 'category')
                    ->toArray(),
            ],
            'floor_labels' => $floorLabels,
            'floor_occupancy' => $floorOccupancy,
        ];
    }

    /**
     * Compute expected vs collected rent grouped by floor, with apartment breakdown.
     */
    private function getApartmentRevenueComparison(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $floors = Floors::with(['apartments' => function ($q) {
                $q->where(function ($q2) {
                    $q2->where('supervisor_id', Auth::id())->orWhereNull('supervisor_id');
                })->select('id', 'floor_id', 'apartment_number', 'monthly_rent', 'status')
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
    private function getCalendarData(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

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
}
