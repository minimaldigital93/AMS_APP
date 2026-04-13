<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\MonthlyPeriod;
use App\Models\BalanceSheet;
use App\Models\User;
use App\Models\Payments;
use App\Models\Utilities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class FiscalPeriodController extends Controller
{
    /**
     * Display a listing of fiscal periods.
     */
    public function index()
    {
        $fiscalPeriods = FiscalPeriods::where('user_id', Auth::id())
            ->orderBy('opening_date', 'desc')
            ->paginate(15);

        return view('admin.fiscalperiod.index', compact('fiscalPeriods'));
    }

    public function create()
    {
        return view('admin.fiscalperiod.open_close_periods');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'opening_date' => 'required|date|before:closing_date',
            'closing_date' => 'required|date|after:opening_date',
            'opening_balance' => 'required|numeric|min:0',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status'] = 'open';
        $validated['closing_balance'] = 0;

        $fiscalPeriod = FiscalPeriods::create($validated);

        // Auto-generate monthly periods within the fiscal period date range
        $this->generateMonthlyPeriods($fiscalPeriod);

        return redirect()
            ->route('admin.fiscalperiod.balance-sheet', $fiscalPeriod->id)
            ->with('success', 'Fiscal period created with ' . $fiscalPeriod->monthlyPeriods()->count() . ' monthly periods.');
    }

    /**
     * Generate monthly periods for a fiscal period.
     */
    protected function generateMonthlyPeriods(FiscalPeriods $fiscalPeriod): void
    {
        $startDate = Carbon::parse($fiscalPeriod->opening_date)->startOfDay();
        $endDate = Carbon::parse($fiscalPeriod->closing_date)->endOfDay();
        $openingBalance = $fiscalPeriod->opening_balance;
        $isFirst = true;

        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            // Month start: either the fiscal period start date or the 1st of the month
            $monthStart = $isFirst ? $startDate->copy() : $current->copy()->startOfMonth();

            // Month end: either the last day of the month or the fiscal period end date
            $monthEnd = $current->copy()->endOfMonth();
            if ($monthEnd->gt($endDate)) {
                $monthEnd = $endDate->copy();
            }

            MonthlyPeriod::create([
                'fiscal_period_id' => $fiscalPeriod->id,
                'user_id' => $fiscalPeriod->user_id,
                'name' => $monthStart->format('F Y'),
                'month_number' => $monthStart->month,
                'year' => $monthStart->year,
                'start_date' => $monthStart->format('Y-m-d'),
                'end_date' => $monthEnd->format('Y-m-d'),
                'opening_balance' => $isFirst ? $openingBalance : 0,
                'closing_balance' => 0,
                'total_income' => 0,
                'total_expenses' => 0,
                'net_income' => 0,
                'status' => $isFirst ? 'open' : 'open',
            ]);

            $isFirst = false;
            $current->addMonth()->startOfMonth();
        }
    }

    /**
     * Show fiscal period details with revenue and expense tracking.
     */
    public function show(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);
        
        $balanceSheetItems = $fiscalperiod->balanceSheets()
            ->orderBy('as_of_date', 'desc')
            ->get();

        // Calculate Revenue from Payments (Rent Income) within this fiscal period
        $revenue = Payments::whereHas('rental', function($query) use ($fiscalperiod) {
            $query->whereHas('apartment', function($subQuery) use ($fiscalperiod) {
                $subQuery->where('supervisor_id', $fiscalperiod->user_id);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$fiscalperiod->opening_date, $fiscalperiod->closing_date])
        ->sum('amount');

        $lateFees = Payments::whereHas('rental', function($query) use ($fiscalperiod) {
            $query->whereHas('apartment', function($subQuery) use ($fiscalperiod) {
                $subQuery->where('supervisor_id', $fiscalperiod->user_id);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$fiscalperiod->opening_date, $fiscalperiod->closing_date])
        ->sum('late_fee');

        $totalIncome = $revenue + $lateFees;

        // Calculate Expenses from Utilities within this fiscal period
        $utilitiesData = Utilities::whereHas('rental', function($query) use ($fiscalperiod) {
            $query->whereHas('apartment', function($subQuery) use ($fiscalperiod) {
                $subQuery->where('supervisor_id', $fiscalperiod->user_id);
            });
        })
        ->where('paid_status', true)
        ->whereBetween('paid_at', [$fiscalperiod->opening_date, $fiscalperiod->closing_date])
        ->get();

        $expenses = [];
        $totalExpenses = 0;
        foreach ($utilitiesData->groupBy('utility_type') as $type => $items) {
            $typeTotal = $items->sum('charge_amount');
            $expenses[$type] = $typeTotal;
            $totalExpenses += $typeTotal;
        }

        // Net profit
        $netProfit = $totalIncome - $totalExpenses;

        // Payment count
        $paymentCount = Payments::whereHas('rental', function($query) use ($fiscalperiod) {
            $query->whereHas('apartment', function($subQuery) use ($fiscalperiod) {
                $subQuery->where('supervisor_id', $fiscalperiod->user_id);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$fiscalperiod->opening_date, $fiscalperiod->closing_date])
        ->count();

        $financialData = [
            'revenue' => $revenue,
            'late_fees' => $lateFees,
            'total_income' => $totalIncome,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'is_profitable' => $netProfit > 0,
            'payment_count' => $paymentCount,
        ];

        return view('admin.fiscalperiod.show', compact('fiscalperiod', 'balanceSheetItems', 'financialData'));
    }

    /**
     * Show form to manage balance sheet items.
     */
    public function balanceSheet(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()
            ->orderBy('item_type')
            ->get()
            ->groupBy('item_type');

        $summary = $this->calculateBalanceSheetSummary($fiscalperiod);

        return view('admin.fiscalperiod.balance_sheet_items', compact('fiscalperiod', 'balanceSheetItems', 'summary'));
    }

    /**
     * Store a balance sheet item.
     */
    public function storeBalanceItem(Request $request, FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $validated = $request->validate([
            'item_type' => 'required|in:asset,liability,equity',
            'sub_type' => 'required|string',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'as_of_date' => 'required|date|after_or_equal:' . $fiscalperiod->opening_date->format('Y-m-d') . '|before_or_equal:' . $fiscalperiod->closing_date->format('Y-m-d'),
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $validated['fiscal_period_id'] = $fiscalperiod->id;
        $validated['user_id'] = Auth::id();

        BalanceSheet::create($validated);

        return back()->with('success', 'Balance sheet item added successfully.');
    }

    /**
     * Show form for opening and closing balances.
     */
    public function openCloseBalances(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $assets = $fiscalperiod->balanceSheets()->where('item_type', 'asset')->sum('amount');
        $liabilities = $fiscalperiod->balanceSheets()->where('item_type', 'liability')->sum('amount');
        $equity = $fiscalperiod->balanceSheets()->where('item_type', 'equity')->sum('amount');

        $closingBalance = $assets - $liabilities;

        return view('admin.fiscalperiod.open_close_balances', compact('fiscalperiod', 'assets', 'liabilities', 'equity', 'closingBalance'));
    }

    /**
     * Update opening and closing balances and close period.
     */
    public function closeperiod(Request $request, FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $validated = $request->validate([
            'closing_balance' => 'required|numeric',
        ]);

        $fiscalperiod->update([
            'closing_balance' => $validated['closing_balance'],
            'status' => 'closed',
        ]);

        return redirect()
            ->route('admin.fiscalperiod.show', $fiscalperiod->id)
            ->with('success', 'Fiscal period closed successfully.');
    }

    /**
     * Export balance sheet as PDF.
     */
    public function exportPDF(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()->get();
        $summary = $this->calculateBalanceSheetSummary($fiscalperiod);

        // Create PDF using available library or HTML table
        $html = $this->generateBalanceSheetHTML($fiscalperiod, $balanceSheetItems, $summary);

        // For now, return view that can be printed as PDF
        return view('admin.fiscalperiod.export-pdf', compact('fiscalperiod', 'balanceSheetItems', 'summary', 'html'));
    }

    /**
     * Export balance sheet as CSV.
     */
    public function exportCSV(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()
            ->orderBy('item_type')
            ->get();

        $fileName = "balance_sheet_{$fiscalperiod->id}_" . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$fileName}",
        ];

        $callback = function () use ($fiscalperiod, $balanceSheetItems) {
            $file = fopen('php://output', 'w');
            
            // Write headers
            fputcsv($file, [
                'Fiscal Period: ' . $fiscalperiod->name,
                'Period: ' . $fiscalperiod->opening_date . ' to ' . $fiscalperiod->closing_date,
                'Generated: ' . now()->format('Y-m-d H:i:s'),
            ]);
            fputcsv($file, []);

            fputcsv($file, ['Item Type', 'Sub Type', 'Name', 'Amount', 'As Of Date', 'Reference Number', 'Notes']);

            foreach ($balanceSheetItems as $item) {
                fputcsv($file, [
                    ucfirst($item->item_type),
                    ucfirst(str_replace('_', ' ', $item->sub_type)),
                    $item->name,
                    number_format($item->amount, 2),
                    $item->as_of_date,
                    $item->reference_number,
                    $item->notes,
                ]);
            }

            // Add summary
            fputcsv($file, []);
            $summary = $this->calculateBalanceSheetSummary($fiscalperiod);
            fputcsv($file, ['SUMMARY']);
            fputcsv($file, ['Total Assets', number_format($summary['total_assets'], 2)]); 
            fputcsv($file, ['Total Liabilities', number_format($summary['total_liabilities'], 2)]);
            fputcsv($file, ['Total Equity', number_format($summary['total_equity'], 2)]);
            fputcsv($file, ['Opening Balance', number_format($fiscalperiod->opening_balance, 2)]);
            fputcsv($file, ['Closing Balance', number_format($fiscalperiod->closing_balance, 2)]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Edit fiscal period.
     */
    public function edit(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);
        
        return view('admin.fiscalperiod.edit', compact('fiscalperiod'));
    }

    /**
     * Update fiscal period.
     */
    public function update(Request $request, FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'opening_date' => 'required|date|before:closing_date',
            'closing_date' => 'required|date|after:opening_date',
            'opening_balance' => 'required|numeric|min:0',
        ]);

        $fiscalperiod->update($validated);

        return redirect()
            ->route('admin.fiscalperiod.show', $fiscalperiod->id)
            ->with('success', 'Fiscal period updated successfully.');
    }

    /**
     * Delete balance sheet item.
     */
    public function deleteBalanceItem(FiscalPeriods $fiscalperiod, BalanceSheet $balanceSheet)
    {
        $this->authorizeUser($fiscalperiod);

        if ($balanceSheet->fiscal_period_id !== $fiscalperiod->id) {
            abort(403);
        }

        $balanceSheet->delete();

        return back()->with('success', 'Balance sheet item deleted successfully.');
    }

    /**
     * Delete fiscal period.
     */
    public function destroy(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        if ($fiscalperiod->status === 'closed') {
            return back()->with('error', 'Cannot delete a closed fiscal period.');
        }

        $fiscalperiod->delete();

        return redirect()
            ->route('admin.fiscalperiod.index')
            ->with('success', 'Fiscal period deleted successfully.');
    }

    // ========================================
    // MONTHLY PERIOD MANAGEMENT
    // ========================================

    /**
     * Display monthly periods for a fiscal period.
     */
    public function monthlyPeriods(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $monthlyPeriods = $fiscalperiod->monthlyPeriods()
            ->orderBy('start_date')
            ->get();

        // Calculate live financial data for each monthly period
        foreach ($monthlyPeriods as $month) {
            $monthData = $this->calculateMonthlyFinancials($fiscalperiod, $month);
            $month->live_income = $monthData['total_income'];
            $month->live_expenses = $monthData['total_expenses'];
            $month->live_net = $monthData['net_income'];
        }

        return view('admin.fiscalperiod.monthly_periods', compact('fiscalperiod', 'monthlyPeriods'));
    }

    /**
     * Show detailed view of a specific monthly period.
     */
    public function showMonth(FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod)
    {
        $this->authorizeUser($fiscalperiod);

        if ($monthlyPeriod->fiscal_period_id !== $fiscalperiod->id) {
            abort(403);
        }

        $financials = $this->calculateMonthlyFinancials($fiscalperiod, $monthlyPeriod);

        // Get previous and next months for navigation
        $previousMonth = $fiscalperiod->monthlyPeriods()
            ->where('start_date', '<', $monthlyPeriod->start_date)
            ->orderBy('start_date', 'desc')
            ->first();

        $nextMonth = $fiscalperiod->monthlyPeriods()
            ->where('start_date', '>', $monthlyPeriod->start_date)
            ->orderBy('start_date')
            ->first();

        return view('admin.fiscalperiod.monthly_period_show', compact(
            'fiscalperiod', 'monthlyPeriod', 'financials', 'previousMonth', 'nextMonth'
        ));
    }

    /**
     * Close a monthly period and carry forward balance.
     */
    public function closeMonth(Request $request, FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod)
    {
        $this->authorizeUser($fiscalperiod);

        if ($monthlyPeriod->fiscal_period_id !== $fiscalperiod->id) {
            abort(403);
        }

        if (!$monthlyPeriod->canClose()) {
            return back()->with('error', 'This monthly period cannot be closed.');
        }

        // Calculate final financials for this month
        $financials = $this->calculateMonthlyFinancials($fiscalperiod, $monthlyPeriod);

        $closingBalance = $monthlyPeriod->opening_balance + $financials['net_income'];

        // Update this month
        $monthlyPeriod->update([
            'total_income' => $financials['total_income'],
            'total_expenses' => $financials['total_expenses'],
            'net_income' => $financials['net_income'],
            'closing_balance' => $closingBalance,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        // Carry forward: set next month's opening balance
        $nextMonth = $fiscalperiod->nextMonthlyPeriod($monthlyPeriod);
        if ($nextMonth && $nextMonth->isOpen()) {
            $nextMonth->update([
                'opening_balance' => $closingBalance,
            ]);
        }

        return back()->with('success', $monthlyPeriod->name . ' closed. Closing balance: $' . number_format($closingBalance, 2) . ($nextMonth ? ' carried forward to ' . $nextMonth->name : '') . '.');
    }

    /**
     * Reopen a closed monthly period.
     */
    public function reopenMonth(Request $request, FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod)
    {
        $this->authorizeUser($fiscalperiod);

        if ($monthlyPeriod->fiscal_period_id !== $fiscalperiod->id) {
            abort(403);
        }

        if (!$monthlyPeriod->canReopen()) {
            return back()->with('error', 'This monthly period cannot be reopened.');
        }

        // Check if next month is already closed (prevent breaking chain)
        $nextMonth = $fiscalperiod->nextMonthlyPeriod($monthlyPeriod);
        if ($nextMonth && $nextMonth->isClosed()) {
            return back()->with('error', 'Cannot reopen: the next month (' . $nextMonth->name . ') is already closed. Reopen it first.');
        }

        $monthlyPeriod->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        return back()->with('success', $monthlyPeriod->name . ' has been reopened.');
    }

    /**
     * Recalculate all monthly period balances (cascade carry-forward).
     */
    public function recalculateBalances(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $monthlyPeriods = $fiscalperiod->monthlyPeriods()->orderBy('start_date')->get();
        $carryForward = $fiscalperiod->opening_balance;

        foreach ($monthlyPeriods as $month) {
            $financials = $this->calculateMonthlyFinancials($fiscalperiod, $month);

            $month->update([
                'opening_balance' => $carryForward,
                'total_income' => $financials['total_income'],
                'total_expenses' => $financials['total_expenses'],
                'net_income' => $financials['net_income'],
                'closing_balance' => $carryForward + $financials['net_income'],
            ]);

            $carryForward = $month->closing_balance;
        }

        // Update fiscal period closing balance
        $fiscalperiod->update(['closing_balance' => $carryForward]);

        return back()->with('success', 'All monthly balances recalculated. Fiscal period closing balance: $' . number_format($carryForward, 2));
    }

    // ========================================
    // ENHANCED REPORTS
    // ========================================

    /**
     * Show comprehensive reports with monthly breakdown.
     */
    public function reports(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()->get();
        $summary = $this->calculateBalanceSheetSummary($fiscalperiod);

        // Monthly periods with financials
        $monthlyPeriods = $fiscalperiod->monthlyPeriods()->orderBy('start_date')->get();
        $monthlyData = [];

        foreach ($monthlyPeriods as $month) {
            $financials = $this->calculateMonthlyFinancials($fiscalperiod, $month);
            $monthlyData[] = [
                'period' => $month,
                'financials' => $financials,
            ];
        }

        // Overall period financials
        $periodFinancials = $this->calculatePeriodFinancials($fiscalperiod);

        // Income Statement data
        $incomeStatement = $this->generateIncomeStatement($fiscalperiod, $monthlyPeriods);

        // Cash Flow data
        $cashFlow = $this->generateCashFlowStatement($fiscalperiod, $monthlyPeriods);

        // Trial Balance data
        $trialBalance = $this->generateTrialBalance($fiscalperiod);

        return view('admin.fiscalperiod.period_reports_exports', compact(
            'fiscalperiod', 'balanceSheetItems', 'summary',
            'monthlyPeriods', 'monthlyData', 'periodFinancials',
            'incomeStatement', 'cashFlow', 'trialBalance'
        ));
    }

    // ========================================
    // FINANCIAL CALCULATION HELPERS
    // ========================================

    /**
     * Calculate financial data for a specific monthly period.
     */
    protected function calculateMonthlyFinancials(FiscalPeriods $fiscalperiod, MonthlyPeriod $month): array
    {
        $userId = $fiscalperiod->user_id;

        // Revenue from rent payments
        $rentIncome = Payments::whereHas('rental', function($query) use ($userId) {
            $query->whereHas('apartment', function($subQuery) use ($userId) {
                $subQuery->where('supervisor_id', $userId);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$month->start_date, $month->end_date])
        ->sum('amount');

        // Late fees
        $lateFees = Payments::whereHas('rental', function($query) use ($userId) {
            $query->whereHas('apartment', function($subQuery) use ($userId) {
                $subQuery->where('supervisor_id', $userId);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$month->start_date, $month->end_date])
        ->sum('late_fee');

        // Payment count
        $paymentCount = Payments::whereHas('rental', function($query) use ($userId) {
            $query->whereHas('apartment', function($subQuery) use ($userId) {
                $subQuery->where('supervisor_id', $userId);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$month->start_date, $month->end_date])
        ->count();

        $totalIncome = $rentIncome + $lateFees;

        // Expenses from utilities
        $utilitiesData = Utilities::whereHas('rental', function($query) use ($userId) {
            $query->whereHas('apartment', function($subQuery) use ($userId) {
                $subQuery->where('supervisor_id', $userId);
            });
        })
        ->where('paid_status', true)
        ->whereBetween('paid_at', [$month->start_date, $month->end_date])
        ->get();

        $expenses = [];
        $totalExpenses = 0;
        foreach ($utilitiesData->groupBy('utility_type') as $type => $items) {
            $typeTotal = $items->sum('charge_amount');
            $expenses[$type] = $typeTotal;
            $totalExpenses += $typeTotal;
        }

        // Fixed expenses from accounts table
        $fixedExpenses = $fiscalperiod->accounts()
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->whereBetween('transaction_date', [$month->start_date, $month->end_date])
            ->sum('amount');

        $totalExpenses += $fixedExpenses;

        // Account-based income (non-rent)
        $otherIncome = $fiscalperiod->accounts()
            ->where('account_type', Accounts::TYPE_INCOME)
            ->whereBetween('transaction_date', [$month->start_date, $month->end_date])
            ->sum('amount');

        $totalIncome += $otherIncome;

        $netIncome = $totalIncome - $totalExpenses;

        return [
            'rent_income' => $rentIncome,
            'late_fees' => $lateFees,
            'other_income' => $otherIncome,
            'total_income' => $totalIncome,
            'utility_expenses' => $expenses,
            'fixed_expenses' => $fixedExpenses,
            'total_expenses' => $totalExpenses,
            'net_income' => $netIncome,
            'payment_count' => $paymentCount,
            'is_profitable' => $netIncome >= 0,
        ];
    }

    /**
     * Calculate total period financials.
     */
    protected function calculatePeriodFinancials(FiscalPeriods $fiscalperiod): array
    {
        $userId = $fiscalperiod->user_id;

        $rentIncome = Payments::whereHas('rental', function($query) use ($userId) {
            $query->whereHas('apartment', function($subQuery) use ($userId) {
                $subQuery->where('supervisor_id', $userId);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$fiscalperiod->opening_date, $fiscalperiod->closing_date])
        ->sum('amount');

        $lateFees = Payments::whereHas('rental', function($query) use ($userId) {
            $query->whereHas('apartment', function($subQuery) use ($userId) {
                $subQuery->where('supervisor_id', $userId);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$fiscalperiod->opening_date, $fiscalperiod->closing_date])
        ->sum('late_fee');

        $paymentCount = Payments::whereHas('rental', function($query) use ($userId) {
            $query->whereHas('apartment', function($subQuery) use ($userId) {
                $subQuery->where('supervisor_id', $userId);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$fiscalperiod->opening_date, $fiscalperiod->closing_date])
        ->count();

        $utilExpenses = Utilities::whereHas('rental', function($query) use ($userId) {
            $query->whereHas('apartment', function($subQuery) use ($userId) {
                $subQuery->where('supervisor_id', $userId);
            });
        })
        ->where('paid_status', true)
        ->whereBetween('paid_at', [$fiscalperiod->opening_date, $fiscalperiod->closing_date])
        ->get();

        $expensesByType = [];
        $totalUtilExpenses = 0;
        foreach ($utilExpenses->groupBy('utility_type') as $type => $items) {
            $typeTotal = $items->sum('charge_amount');
            $expensesByType[$type] = $typeTotal;
            $totalUtilExpenses += $typeTotal;
        }

        $fixedExpenses = $fiscalperiod->accounts()
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->sum('amount');

        $otherIncome = $fiscalperiod->accounts()
            ->where('account_type', Accounts::TYPE_INCOME)
            ->sum('amount');

        $totalIncome = $rentIncome + $lateFees + $otherIncome;
        $totalExpenses = $totalUtilExpenses + $fixedExpenses;
        $netIncome = $totalIncome - $totalExpenses;

        return [
            'rent_income' => $rentIncome,
            'late_fees' => $lateFees,
            'other_income' => $otherIncome,
            'total_income' => $totalIncome,
            'utility_expenses' => $expensesByType,
            'total_util_expenses' => $totalUtilExpenses,
            'fixed_expenses' => $fixedExpenses,
            'total_expenses' => $totalExpenses,
            'net_income' => $netIncome,
            'is_profitable' => $netIncome >= 0,
            'payment_count' => $paymentCount,
        ];
    }

    /**
     * Generate Income Statement data.
     */
    protected function generateIncomeStatement(FiscalPeriods $fiscalperiod, $monthlyPeriods): array
    {
        $months = [];
        $totals = [
            'rent_income' => 0, 'late_fees' => 0, 'other_income' => 0,
            'total_income' => 0, 'total_expenses' => 0, 'net_income' => 0,
        ];

        foreach ($monthlyPeriods as $month) {
            $financials = $this->calculateMonthlyFinancials($fiscalperiod, $month);
            $months[] = [
                'name' => $month->name,
                'short' => Carbon::parse($month->start_date)->format('M'),
                'data' => $financials,
            ];
            $totals['rent_income'] += $financials['rent_income'];
            $totals['late_fees'] += $financials['late_fees'];
            $totals['other_income'] += $financials['other_income'];
            $totals['total_income'] += $financials['total_income'];
            $totals['total_expenses'] += $financials['total_expenses'];
            $totals['net_income'] += $financials['net_income'];
        }

        return ['months' => $months, 'totals' => $totals];
    }

    /**
     * Generate Cash Flow Statement data.
     */
    protected function generateCashFlowStatement(FiscalPeriods $fiscalperiod, $monthlyPeriods): array
    {
        $months = [];
        $runningBalance = $fiscalperiod->opening_balance;

        foreach ($monthlyPeriods as $month) {
            $financials = $this->calculateMonthlyFinancials($fiscalperiod, $month);
            $openBal = $runningBalance;
            $closeBal = $openBal + $financials['net_income'];

            $months[] = [
                'name' => $month->name,
                'short' => Carbon::parse($month->start_date)->format('M'),
                'opening_balance' => $openBal,
                'cash_in' => $financials['total_income'],
                'cash_out' => $financials['total_expenses'],
                'net_cash_flow' => $financials['net_income'],
                'closing_balance' => $closeBal,
            ];

            $runningBalance = $closeBal;
        }

        return [
            'months' => $months,
            'opening_balance' => $fiscalperiod->opening_balance,
            'closing_balance' => $runningBalance,
            'total_cash_in' => array_sum(array_column($months, 'cash_in')),
            'total_cash_out' => array_sum(array_column($months, 'cash_out')),
            'net_change' => $runningBalance - $fiscalperiod->opening_balance,
        ];
    }

    /**
     * Generate Trial Balance data.
     */
    protected function generateTrialBalance(FiscalPeriods $fiscalperiod): array
    {
        $periodFinancials = $this->calculatePeriodFinancials($fiscalperiod);
        $summary = $this->calculateBalanceSheetSummary($fiscalperiod);

        // Debit accounts (assets + expenses)
        $debits = [];
        $totalDebits = 0;

        // Assets from balance sheet
        if ($summary['total_assets'] > 0) {
            $debits[] = ['account' => 'Assets', 'amount' => $summary['total_assets']];
            $totalDebits += $summary['total_assets'];
        }

        // Expenses
        if ($periodFinancials['total_expenses'] > 0) {
            $debits[] = ['account' => 'Total Expenses', 'amount' => $periodFinancials['total_expenses']];
            $totalDebits += $periodFinancials['total_expenses'];
        }

        // Cash (opening balance)
        if ($fiscalperiod->opening_balance > 0) {
            $debits[] = ['account' => 'Cash (Opening Balance)', 'amount' => $fiscalperiod->opening_balance];
            $totalDebits += $fiscalperiod->opening_balance;
        }

        // Credit accounts (liabilities + equity + revenue)
        $credits = [];
        $totalCredits = 0;

        // Liabilities from balance sheet
        if ($summary['total_liabilities'] > 0) {
            $credits[] = ['account' => 'Liabilities', 'amount' => $summary['total_liabilities']];
            $totalCredits += $summary['total_liabilities'];
        }

        // Equity from balance sheet
        if ($summary['total_equity'] > 0) {
            $credits[] = ['account' => 'Equity', 'amount' => $summary['total_equity']];
            $totalCredits += $summary['total_equity'];
        }

        // Revenue
        if ($periodFinancials['total_income'] > 0) {
            $credits[] = ['account' => 'Total Revenue', 'amount' => $periodFinancials['total_income']];
            $totalCredits += $periodFinancials['total_income'];
        }

        return [
            'debits' => $debits,
            'credits' => $credits,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
            'difference' => $totalDebits - $totalCredits,
        ];
    }

    /**
     * Calculate balance sheet summary.
     */
    protected function calculateBalanceSheetSummary(FiscalPeriods $fiscalperiod): array
    {
        $assets = $fiscalperiod->balanceSheets()
            ->where('item_type', 'asset')
            ->sum('amount');

        $liabilities = $fiscalperiod->balanceSheets()
            ->where('item_type', 'liability')
            ->sum('amount');

        $equity = $fiscalperiod->balanceSheets()
            ->where('item_type', 'equity')
            ->sum('amount');

        return [
            'total_assets' => $assets,
            'total_liabilities' => $liabilities,
            'total_equity' => $equity,
            'net_worth' => $assets - $liabilities,
            'balance_check' => ($liabilities + $equity) == $assets ? true : false,
        ];
    }

    /**
     * Generate HTML for balance sheet.
     */
    protected function generateBalanceSheetHTML(FiscalPeriods $fiscalperiod, $balanceSheetItems, array $summary): string
    {
        $html = '<h1>' . $fiscalperiod->name . '</h1>';
        $html .= '<p>Period: ' . $fiscalperiod->opening_date . ' to ' . $fiscalperiod->closing_date . '</p>';
        $html .= '<table border="1" cellpadding="5" width="100%">';
        $html .= '<tr><th>Item Type</th><th>Name</th><th>Amount</th><th>As Of Date</th></tr>';

        foreach ($balanceSheetItems as $item) {
            $html .= '<tr>';
            $html .= '<td>' . ucfirst($item->item_type) . '</td>';
            $html .= '<td>' . $item->name . '</td>';
            $html .= '<td>' . number_format($item->amount, 2) . '</td>';
            $html .= '<td>' . $item->as_of_date . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    /**
     * Authorize user access.
     */
    protected function authorizeUser(FiscalPeriods $fiscalperiod): void
    {
        if ($fiscalperiod->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access');
        }
    }
}
