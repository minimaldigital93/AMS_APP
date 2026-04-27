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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $apartmentIds = $this->supervisorApartmentIds();

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

        $stats = $this->getStats($apartmentIds, $dateRange['start'], $dateRange['end'], $displayMonth);
        $fiscalData = $this->getActiveFiscalPeriodData($activePeriod, $apartmentIds);
        $calendarData = $isFullPeriod ? null : $this->getCalendarData($activePeriod, $apartmentIds, $displayMonth);

        $apartmentsWithRentals = Apartments::with(['rentals' => function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('end_date')->orWhere('end_date', '>=', now());
                })->with('tenant');
            }])
            ->whereIn('id', $apartmentIds)
            ->where('status', 'occupied')
            ->orderBy('apartment_number')
            ->get();

        $recentTransactions = collect();
        if ($activePeriod) {
            $recentTransactions = Accounts::where('fiscal_period_id', $activePeriod->id)
                ->where(function ($q) use ($apartmentIds) {
                    $q->whereHas('payment', function ($pq) use ($apartmentIds) {
                        $pq->whereHas('rental', function ($rq) use ($apartmentIds) {
                            $rq->whereIn('apartment_id', $apartmentIds);
                        });
                    })->orWhere(function ($q2) {
                        $q2->where('account_type', Accounts::TYPE_EXPENSE)->whereNull('payment_id');
                    });
                })
                ->whereBetween('transaction_date', [
                    $dateRange['start']->copy()->startOfDay(),
                    $dateRange['end']->copy()->endOfDay(),
                ])
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->take(15)
                ->get();
        }

        $apartmentRevenues = $isFullPeriod ? [] : $this->getApartmentRevenueComparison($apartmentIds, $displayMonth);
        $monthNavigation = $this->getMonthNavigation($periodMonths, $displayMonth, $isFullPeriod);

        return view('supervisor.dashboard', compact(
            'stats', 'fiscalData', 'calendarData',
            'activePeriod', 'apartmentsWithRentals', 'recentTransactions', 'apartmentRevenues',
            'selectedMonth', 'periodMonths', 'monthNavigation', 'isFullPeriod', 'displayMonth'
        ));
    }

    private function supervisorApartmentIds(): array
    {
        return Apartments::pluck('id')->toArray();
    }

    private function getActiveFiscalPeriod(): ?FiscalPeriods
    {
        return FiscalPeriods::where('status', 'open')
            ->whereHas('user', fn($q) => $q->role('admin'))
            ->orderBy('opening_date', 'desc')
            ->first();
    }

    private function getActiveFiscalPeriodData(?FiscalPeriods $activePeriod, array $apartmentIds): array
    {
        if (!$activePeriod) {
            return [
                'has_active_period' => false,
                'period' => null,
                'revenue' => 0, 'late_fees' => 0, 'total_income' => 0,
                'expenses' => [], 'total_expenses' => 0,
                'net_profit' => 0, 'is_profitable' => false, 'profit_margin' => 0,
                'opening_balance' => 0, 'current_balance' => 0,
                'balance_sheet' => ['total_assets' => 0, 'total_liabilities' => 0, 'total_equity' => 0],
                'recent_periods' => [],
                'monthly_revenue' => [], 'monthly_expenses' => [],
            ];
        }

        $accountsBase = Accounts::where('fiscal_period_id', $activePeriod->id)
            ->where(function ($q) use ($apartmentIds) {
                $q->whereHas('payment', function ($pq) use ($apartmentIds) {
                    $pq->whereHas('rental', fn($rq) => $rq->whereIn('apartment_id', $apartmentIds));
                })->orWhere(function ($q2) {
                    $q2->where('account_type', Accounts::TYPE_EXPENSE)->whereNull('payment_id');
                });
            });

        $incomeRecords = (clone $accountsBase)->where('account_type', Accounts::TYPE_INCOME)->get();
        $expenseRecords = (clone $accountsBase)->where('account_type', Accounts::TYPE_EXPENSE)->get();

        $revenue = $incomeRecords->where('category', Accounts::CAT_RENT_INCOME)->sum('amount');
        $lateFees = $incomeRecords->where('category', Accounts::CAT_LATE_FEE_INCOME)->sum('amount');
        $totalIncome = $incomeRecords->sum('amount');

        $expenses = $expenseRecords->groupBy('category')
            ->map(fn($items) => round($items->sum('amount'), 2))
            ->toArray();
        $totalExpenses = $expenseRecords->sum('amount');

        $netProfit = $totalIncome - $totalExpenses;
        $profitMargin = $totalIncome > 0 ? round(($netProfit / $totalIncome) * 100, 2) : 0;

        $totalAssets = $activePeriod->balanceSheets()->where('item_type', 'asset')->sum('amount');
        $totalLiabilities = $activePeriod->balanceSheets()->where('item_type', 'liability')->sum('amount');
        $totalEquity = $activePeriod->balanceSheets()->where('item_type', 'equity')->sum('amount');

        $currentBalance = $activePeriod->opening_balance + $netProfit;

        $recentPeriods = FiscalPeriods::where('user_id', $activePeriod->user_id)
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
            'monthly_revenue' => $this->getMonthlyAccounts($activePeriod, $apartmentIds, Accounts::TYPE_INCOME),
            'monthly_expenses' => $this->getMonthlyAccounts($activePeriod, $apartmentIds, Accounts::TYPE_EXPENSE),
        ];
    }

    private function getMonthlyAccounts(FiscalPeriods $period, array $apartmentIds, string $type): array
    {
        $rows = Accounts::where('fiscal_period_id', $period->id)
            ->where('account_type', $type)
            ->where(function ($q) use ($apartmentIds) {
                $q->whereHas('payment', function ($pq) use ($apartmentIds) {
                    $pq->whereHas('rental', fn($rq) => $rq->whereIn('apartment_id', $apartmentIds));
                })->orWhere(function ($q2) {
                    $q2->where('account_type', Accounts::TYPE_EXPENSE)->whereNull('payment_id');
                });
            })
            ->selectRaw('YEAR(transaction_date) as year, MONTH(transaction_date) as month, SUM(amount) as total')
            ->groupByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->orderByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $label = date('M Y', mktime(0, 0, 0, $row->month, 1, $row->year));
            $result[$label] = round($row->total, 2);
        }
        return $result;
    }

    private function getStats(array $apartmentIds, Carbon $startDate, Carbon $endDate, Carbon $referenceMonth): array
    {
        $currentMonth = $referenceMonth->month;
        $currentYear = $referenceMonth->year;
        $referenceDate = $referenceMonth->isPast() ? $endDate->copy()->endOfDay() : now();

        $paidCount = $pendingCount = $overdueCount = 0;
        $totalPendingAmount = 0;

        $activeRentals = Rentals::with(['payments' => fn($pq) => $pq->where('payment_status', 'paid'), 'apartment'])
            ->where('start_date', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
            })
            ->whereIn('apartment_id', $apartmentIds)
            ->get();

        foreach ($activeRentals as $rental) {
            $paidThisMonth = $rental->payments
                ->filter(function ($p) use ($currentMonth, $currentYear) {
                    return $p->payment_type === 'rent'
                        && Carbon::parse($p->paid_at)->month === $currentMonth
                        && Carbon::parse($p->paid_at)->year === $currentYear;
                })->isNotEmpty();

            $start = $rental->start_date ? Carbon::parse($rental->start_date) : null;
            $dueDay = $start ? $start->day : 1;
            $dueDay = min($dueDay, Carbon::create($currentYear, $currentMonth)->daysInMonth);
            $dueDate = Carbon::create($currentYear, $currentMonth, $dueDay)->endOfDay();

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

        $monthlyRevenueAccounts = Accounts::where('account_type', Accounts::TYPE_INCOME)
            ->whereHas('payment.rental', fn($q) => $q->whereIn('apartment_id', $apartmentIds))
            ->whereBetween('transaction_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->get();

        $monthlyCollected = $monthlyRevenueAccounts->where('category', '!=', Accounts::CAT_LATE_FEE_INCOME)->sum('amount');
        $monthlyLateFees = $monthlyRevenueAccounts->where('category', Accounts::CAT_LATE_FEE_INCOME)->sum('amount');
        $monthlyTotalRevenue = $monthlyCollected + $monthlyLateFees;

        $monthlyExpenseAccounts = Accounts::where('account_type', Accounts::TYPE_EXPENSE)
            ->where(function ($q) use ($apartmentIds) {
                $q->whereHas('payment.rental', fn($r) => $r->whereIn('apartment_id', $apartmentIds))
                  ->orWhereNull('payment_id');
            })
            ->whereBetween('transaction_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->get();

        $monthlyUtilities = $monthlyExpenseAccounts->where('category', Accounts::CAT_UTILITIES_EXPENSE)->sum('amount');
        $monthlyAccountExpenses = $monthlyExpenseAccounts->where('category', '!=', Accounts::CAT_UTILITIES_EXPENSE)->sum('amount');
        $monthlyExpensesTotal = $monthlyExpenseAccounts->sum('amount');

        $utilityBreakdown = Utilities::whereHas('rental', fn($q) => $q->whereIn('apartment_id', $apartmentIds))
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('paid_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereRaw('(billing_year * 100 + billing_month) >= ?', [$startDate->year * 100 + $startDate->month])
                           ->whereRaw('(billing_year * 100 + billing_month) <= ?', [$endDate->year * 100 + $endDate->month]);
                    });
            })
            ->selectRaw('utility_type, SUM(charge_amount) as total')
            ->groupBy('utility_type')
            ->pluck('total', 'utility_type')
            ->toArray();

        $floors = Floors::with(['apartments' => fn($q) => $q->whereIn('id', $apartmentIds)])
            ->orderBy('id')->get();
        $floorLabels = [];
        $floorOccupancy = [];
        foreach ($floors as $floor) {
            $total = $floor->apartments->count();
            if ($total === 0) continue;
            $occupied = $floor->apartments->where('status', 'occupied')->count();
            $floorLabels[] = $floor->floor_name ?? 'Floor ' . $floor->id;
            $floorOccupancy[] = round(($occupied / $total) * 100, 1);
        }

        $expiringSoon = Rentals::with(['tenant', 'apartment'])
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->whereIn('apartment_id', $apartmentIds)
            ->orderBy('end_date')
            ->get();

        return [
            'floors_count' => $floors->count(),
            'apartments' => [
                'total' => count($apartmentIds),
                'available' => Apartments::whereIn('id', $apartmentIds)->where('status', 'available')->count(),
                'occupied' => Apartments::whereIn('id', $apartmentIds)->where('status', 'occupied')->count(),
                'maintenance' => Apartments::whereIn('id', $apartmentIds)->where('status', 'maintenance')->count(),
            ],
            'tenants' => [
                'total' => Tenants::whereIn('apartment_id', $apartmentIds)->count(),
                'active' => Tenants::whereIn('apartment_id', $apartmentIds)->where('status', 'active')->count(),
                'inactive' => Tenants::whereIn('apartment_id', $apartmentIds)->where('status', 'inactive')->count(),
                'pending' => Tenants::whereIn('apartment_id', $apartmentIds)->where('status', 'pending')->count(),
            ],
            'rentals' => [
                'total' => Rentals::whereIn('apartment_id', $apartmentIds)->count(),
                'active' => Rentals::whereIn('apartment_id', $apartmentIds)
                    ->where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })->count(),
            ],
            'leases' => ['expiring_soon' => $expiringSoon],
            'payments' => [
                'paid' => $paidCount,
                'pending' => $pendingCount,
                'overdue' => $overdueCount,
                'total_collected' => Payments::whereHas('rental', fn($q) => $q->whereIn('apartment_id', $apartmentIds))
                    ->where('payment_status', 'paid')
                    ->whereBetween('paid_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                    ->sum('amount'),
                'total_pending' => $totalPendingAmount,
            ],
            'revenue' => [
                'total_monthly' => round($monthlyTotalRevenue, 2),
                'total_monthly_rent' => Apartments::whereIn('id', $apartmentIds)->where('status', 'occupied')->sum('monthly_rent'),
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
                    ->map(fn($items) => round($items->sum('amount'), 2))
                    ->toArray(),
            ],
            'floor_labels' => $floorLabels,
            'floor_occupancy' => $floorOccupancy,
            'tenants_on_leave' => TenantLeave::whereIn('apartment_id', $apartmentIds)->count(),
        ];
    }

    private function getApartmentRevenueComparison(array $apartmentIds, Carbon $selectedMonth): array
    {
        $currentMonth = $selectedMonth->month;
        $currentYear = $selectedMonth->year;

        $floors = Floors::with(['apartments' => function ($q) use ($apartmentIds) {
                $q->whereIn('id', $apartmentIds)
                  ->select('id', 'floor_id', 'apartment_number', 'monthly_rent', 'status')
                  ->orderBy('apartment_number');
            }])
            ->orderBy('id')
            ->get();

        $result = [];
        foreach ($floors as $floor) {
            if ($floor->apartments->isEmpty()) continue;
            $floorExpected = 0;
            $floorActual = 0;
            $apartments = [];

            foreach ($floor->apartments as $apt) {
                $expected = (float) ($apt->monthly_rent ?? 0);
                $actual = (float) Payments::whereHas('rental', fn($q) => $q->where('apartment_id', $apt->id))
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

    private function getCalendarData(?FiscalPeriods $activePeriod, array $apartmentIds, Carbon $selectedMonth): array
    {
        $startOfMonth = $selectedMonth->copy()->startOfMonth();
        $endOfMonth = $selectedMonth->copy()->endOfMonth();

        $accountsScope = function ($q) use ($apartmentIds, $activePeriod) {
            if ($activePeriod) {
                $q->where('fiscal_period_id', $activePeriod->id);
            }
            $q->where(function ($qq) use ($apartmentIds) {
                $qq->whereHas('payment.rental', fn($r) => $r->whereIn('apartment_id', $apartmentIds))
                   ->orWhere(function ($q2) {
                        $q2->where('account_type', Accounts::TYPE_EXPENSE)->whereNull('payment_id');
                   });
            });
        };

        $dailyIncome = Accounts::where('account_type', Accounts::TYPE_INCOME)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->where($accountsScope)
            ->selectRaw('DATE(transaction_date) as day, SUM(amount) as total_income, COUNT(*) as tx_count')
            ->groupByRaw('DATE(transaction_date)')
            ->get()
            ->keyBy('day');

        $dailyExpenses = Accounts::where('account_type', Accounts::TYPE_EXPENSE)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->where($accountsScope)
            ->selectRaw('DATE(transaction_date) as day, SUM(amount) as total_expense, COUNT(*) as tx_count')
            ->groupByRaw('DATE(transaction_date)')
            ->get()
            ->keyBy('day');

        $daysInMonth = $startOfMonth->daysInMonth;
        $firstDayOfWeek = $startOfMonth->dayOfWeek;
        $calendarDays = [];
        $monthTotalIncome = 0;
        $monthTotalExpense = 0;
        $bestDay = null;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = $startOfMonth->copy()->day($d)->toDateString();
            $income = $dailyIncome[$dateStr]->total_income ?? 0;
            $expense = $dailyExpenses[$dateStr]->total_expense ?? 0;
            $net = $income - $expense;
            $txCount = ($dailyIncome[$dateStr]->tx_count ?? 0) + ($dailyExpenses[$dateStr]->tx_count ?? 0);

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

            if ($txCount > 0 && ($bestDay === null || $net > $calendarDays[$bestDay]['net'])) {
                $bestDay = $d;
            }
        }

        return [
            'startOfMonth' => $startOfMonth,
            'firstDayOfWeek' => $firstDayOfWeek,
            'daysInMonth' => $daysInMonth,
            'calendarDays' => $calendarDays,
            'monthTotalIncome' => $monthTotalIncome,
            'monthTotalExpense' => $monthTotalExpense,
            'monthNet' => $monthTotalIncome - $monthTotalExpense,
            'bestDay' => $bestDay,
        ];
    }

    private function buildPeriodMonths(FiscalPeriods $period): array
    {
        $months = [];
        $cursor = Carbon::parse($period->opening_date)->startOfMonth();
        $end = Carbon::parse($period->closing_date)->endOfMonth();
        while ($cursor->lte($end)) {
            $months[] = ['month' => $cursor->month, 'year' => $cursor->year, 'label' => $cursor->format('F Y')];
            $cursor->addMonth();
        }
        return $months;
    }

    private function resolveSelectedMonth(?FiscalPeriods $activePeriod, ?int $month, ?int $year): Carbon
    {
        if ($activePeriod) {
            $periodMonths = $this->buildPeriodMonths($activePeriod);
            if ($month && $year && $month >= 1 && $month <= 12) {
                foreach ($periodMonths as $pm) {
                    if ($pm['month'] === $month && $pm['year'] === $year) {
                        return Carbon::create($year, $month, 1)->startOfMonth();
                    }
                }
            }
            foreach ($periodMonths as $pm) {
                if ($pm['month'] === now()->month && $pm['year'] === now()->year) {
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
        return ['start' => $month->copy()->startOfMonth(), 'end' => $month->copy()->endOfMonth()];
    }

    private function resolveDisplayMonth(?FiscalPeriods $activePeriod, array $periodMonths): Carbon
    {
        if ($activePeriod) {
            foreach ($periodMonths as $pm) {
                if ($pm['month'] === now()->month && $pm['year'] === now()->year) {
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
        foreach ($periodMonths as $index => $pm) {
            if ($pm['month'] === $selectedMonth->month && $pm['year'] === $selectedMonth->year) {
                $currentIndex = $index;
                break;
            }
        }

        return [
            'previousMonth' => !$isFullPeriod && $currentIndex !== null && $currentIndex > 0 ? $periodMonths[$currentIndex - 1] : null,
            'nextMonth' => !$isFullPeriod && $currentIndex !== null && $currentIndex < count($periodMonths) - 1 ? $periodMonths[$currentIndex + 1] : null,
            'isCurrentMonth' => $selectedMonth->month === now()->month && $selectedMonth->year === now()->year,
            'isFullPeriod' => $isFullPeriod,
            'currentMonthInPeriod' => collect($periodMonths)->first(function ($pm) {
                return $pm['month'] === now()->month && $pm['year'] === now()->year;
            }),
        ];
    }
}
