<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FiscalPeriod\CloseFiscalPeriodRequest;
use App\Http\Requests\FiscalPeriod\CloseMonthlyPeriodRequest;
use App\Http\Requests\FiscalPeriod\StoreBalanceSheetItemRequest;
use App\Http\Requests\FiscalPeriod\StoreFiscalPeriodRequest;
use App\Http\Requests\FiscalPeriod\UpdateFiscalPeriodRequest;
use App\Models\BalanceSheet;
use App\Models\FiscalPeriods;
use App\Models\MonthlyPeriod;
use App\Services\FiscalPeriod\BalanceSheetService;
use App\Services\FiscalPeriod\FiscalPeriodFinancialsService;
use App\Services\FiscalPeriod\FiscalPeriodReportsService;
use App\Services\FiscalPeriod\MonthlyPeriodManager;
use Illuminate\Support\Facades\Auth;

class FiscalPeriodController extends Controller
{
    public function __construct(
        private FiscalPeriodFinancialsService $financials,
        private BalanceSheetService $balanceSheetService,
        private MonthlyPeriodManager $monthlyManager,
        private FiscalPeriodReportsService $reportsService,
    ) {}

    // ============================================================
    // FISCAL PERIOD CRUD
    // ============================================================

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

    public function store(StoreFiscalPeriodRequest $request)
    {
        $data = $request->validated();

        $fiscalPeriod = FiscalPeriods::create([
            ...$data,
            'user_id' => Auth::id(),
            'status' => 'open',
            // The opening cash carry-forward seed is the opening assets: from here
            // the monthly closing balance tracks total assets as profit accrues.
            'opening_balance' => $data['opening_assets'],
            'closing_balance' => 0,
        ]);

        $this->monthlyManager->generateForFiscalPeriod($fiscalPeriod);

        return redirect()
            ->route('admin.fiscalperiod.show', $fiscalPeriod->id)
            ->with('success', 'Fiscal period created with '.$fiscalPeriod->monthlyPeriods()->count().' monthly periods. The balance sheet will update automatically as you record income and expenses.');
    }

    /**
     * Dashboard view — financial summary + monthly periods with live numbers.
     */
    public function show(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $financialData = $this->financials->forPeriod($fiscalperiod);
        $monthlyPeriods = $this->attachLiveFinancials($fiscalperiod->monthlyPeriods()->orderBy('start_date')->get(), $fiscalperiod);
        $balanceSummary = $this->balanceSheetService->summary($fiscalperiod);

        return view('admin.fiscalperiod.show', compact(
            'fiscalperiod', 'financialData', 'monthlyPeriods', 'balanceSummary'
        ));
    }

    public function edit(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        return view('admin.fiscalperiod.edit', compact('fiscalperiod'));
    }

    public function update(UpdateFiscalPeriodRequest $request, FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $data = $request->validated();
        $fiscalperiod->update([
            ...$data,
            // Keep the cash carry-forward seed aligned with the opening assets.
            'opening_balance' => $data['opening_assets'],
        ]);

        // Re-cascade the monthly carry-forward so the months reflect the new
        // opening figures.
        $this->monthlyManager->recalculateBalances($fiscalperiod);

        return redirect()
            ->route('admin.fiscalperiod.show', $fiscalperiod->id)
            ->with('success', 'Fiscal period updated successfully.');
    }

    public function destroy(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $fiscalperiod->balanceSheets()->delete();
        $fiscalperiod->monthlyPeriods()->delete();
        $fiscalperiod->delete();

        return redirect()
            ->route('admin.fiscalperiod.index')
            ->with('success', 'Fiscal period deleted successfully.');
    }

    // ============================================================
    // BALANCE SHEET
    // ============================================================

    public function balanceSheet(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()
            ->orderBy('item_type')
            ->get()
            ->groupBy('item_type');

        $summary = $this->balanceSheetService->summary($fiscalperiod);

        return view('admin.fiscalperiod.balance_sheet_items', compact('fiscalperiod', 'balanceSheetItems', 'summary'));
    }

    public function storeBalanceItem(StoreBalanceSheetItemRequest $request, FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        BalanceSheet::create([
            ...$request->validated(),
            'fiscal_period_id' => $fiscalperiod->id,
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Balance sheet item added successfully.');
    }

    public function deleteBalanceItem(FiscalPeriods $fiscalperiod, BalanceSheet $balanceSheet)
    {
        $this->authorizeUser($fiscalperiod);

        if ($balanceSheet->fiscal_period_id !== $fiscalperiod->id) {
            abort(403);
        }

        $balanceSheet->delete();

        return back()->with('success', 'Balance sheet item deleted successfully.');
    }

    public function closeperiod(CloseFiscalPeriodRequest $request, FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $fiscalperiod->update([
            'closing_balance' => $request->validated()['closing_balance'],
            'status' => 'closed',
        ]);

        return redirect()
            ->route('admin.fiscalperiod.show', $fiscalperiod->id)
            ->with('success', 'Fiscal period closed successfully.');
    }

    // ============================================================
    // MONTHLY PERIOD MANAGEMENT
    // ============================================================

    public function showMonth(FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod)
    {
        $this->authorizeUser($fiscalperiod);
        $this->ensureMonthBelongsTo($fiscalperiod, $monthlyPeriod);

        $financials = $this->financials->forMonth($fiscalperiod, $monthlyPeriod);

        $previousMonth = $fiscalperiod->monthlyPeriods()
            ->where('start_date', '<', $monthlyPeriod->start_date)
            ->orderBy('start_date', 'desc')
            ->first();

        $nextMonth = $fiscalperiod->monthlyPeriods()
            ->where('start_date', '>', $monthlyPeriod->start_date)
            ->orderBy('start_date')
            ->first();

        $balanceSheet = $this->balanceSheetService->summaryAsOf($fiscalperiod, $monthlyPeriod);

        return view('admin.fiscalperiod.monthly_period_show', compact(
            'fiscalperiod', 'monthlyPeriod', 'financials', 'previousMonth', 'nextMonth', 'balanceSheet'
        ));
    }

    public function closeMonth(CloseMonthlyPeriodRequest $request, FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod)
    {
        $this->authorizeUser($fiscalperiod);
        $this->ensureMonthBelongsTo($fiscalperiod, $monthlyPeriod);

        if (! $monthlyPeriod->canClose()) {
            return back()->with('error', 'This monthly period cannot be closed.');
        }

        $withdrawal = (float) $request->validated()['owner_withdrawal'];

        // A profit withdrawal can't exceed the cash actually available at month
        // end (opening balance + net income). Guard so the carry-forward never
        // goes negative from an over-draw.
        $financials = $this->financials->forMonth($fiscalperiod, $monthlyPeriod);
        $availableCash = $monthlyPeriod->opening_balance + $financials['net_income'];
        if ($withdrawal > $availableCash + 0.01) {
            return back()
                ->withInput()
                ->with('error', 'Withdrawal of $'.number_format($withdrawal, 2)
                    .' exceeds the available cash of $'.number_format(max(0, $availableCash), 2)
                    .' for '.$monthlyPeriod->name.'.');
        }

        $result = $this->monthlyManager->closeMonth(
            $fiscalperiod,
            $monthlyPeriod,
            $withdrawal,
            $request->validated()['withdrawal_note'] ?? null,
        );

        $msg = $monthlyPeriod->name.' closed. Net income: $'.number_format($result['net_income'], 2).'.';
        if ($result['owner_withdrawal'] > 0) {
            $msg .= ' Owner withdrawal: $'.number_format($result['owner_withdrawal'], 2).'.';
        }
        $msg .= ' Closing balance: $'.number_format($result['closing_balance'], 2)
            .($result['next_month'] ? ' carried forward to '.$result['next_month']->name : '').'.';

        return back()->with('success', $msg);
    }

    public function reopenMonth(FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod)
    {
        $this->authorizeUser($fiscalperiod);
        $this->ensureMonthBelongsTo($fiscalperiod, $monthlyPeriod);

        if (! $monthlyPeriod->canReopen()) {
            return back()->with('error', 'This monthly period cannot be reopened.');
        }

        $result = $this->monthlyManager->reopenMonth($fiscalperiod, $monthlyPeriod);

        // Service returns the blocking next month if reopen would break the chain.
        if ($result instanceof MonthlyPeriod) {
            return back()->with('error', 'Cannot reopen: the next month ('.$result->name.') is already closed. Reopen it first.');
        }

        return back()->with('success', $monthlyPeriod->name.' has been reopened.');
    }

    public function recalculateBalances(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $carryForward = $this->monthlyManager->recalculateBalances($fiscalperiod);

        return back()->with(
            'success',
            'All monthly balances recalculated. Fiscal period closing balance: $'.number_format($carryForward, 2)
        );
    }

    // ============================================================
    // REPORTS + EXPORTS
    // ============================================================

    public function reports(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()->get();
        $summary = $this->balanceSheetService->summary($fiscalperiod);
        $monthlyPeriods = $fiscalperiod->monthlyPeriods()->orderBy('start_date')->get();

        $monthlyData = [];
        foreach ($monthlyPeriods as $month) {
            $monthlyData[] = [
                'period' => $month,
                'financials' => $this->financials->forMonth($fiscalperiod, $month),
            ];
        }

        $periodFinancials = $this->financials->forPeriod($fiscalperiod);
        $incomeStatement = $this->reportsService->incomeStatement($fiscalperiod, $monthlyPeriods);
        $cashFlow = $this->reportsService->cashFlow($fiscalperiod, $monthlyPeriods);
        $trialBalance = $this->reportsService->trialBalance($fiscalperiod);

        return view('admin.fiscalperiod.period_reports_exports', compact(
            'fiscalperiod', 'balanceSheetItems', 'summary',
            'monthlyPeriods', 'monthlyData', 'periodFinancials',
            'incomeStatement', 'cashFlow', 'trialBalance'
        ));
    }

    public function exportPDF(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()->get();
        $summary = $this->balanceSheetService->summary($fiscalperiod);
        $html = $this->balanceSheetService->renderHtml($fiscalperiod, $balanceSheetItems);
        $periodFinancials = $this->financials->forPeriod($fiscalperiod);

        return view('admin.fiscalperiod.export-pdf', compact(
            'fiscalperiod', 'balanceSheetItems', 'summary', 'html', 'periodFinancials'
        ));
    }

    public function printMonthlyPDF(FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod)
    {
        $this->authorizeUser($fiscalperiod);
        $this->ensureMonthBelongsTo($fiscalperiod, $monthlyPeriod);

        $financials = $this->financials->forMonth($fiscalperiod, $monthlyPeriod);
        $balanceSheet = $this->balanceSheetService->summaryAsOf($fiscalperiod, $monthlyPeriod);

        return view('admin.fiscalperiod.monthly-period-pdf', compact('fiscalperiod', 'monthlyPeriod', 'financials', 'balanceSheet'));
    }

    public function exportCSV(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()->orderBy('item_type')->get();
        $summary = $this->balanceSheetService->summary($fiscalperiod);

        $fileName = "balance_sheet_{$fiscalperiod->id}_".now()->format('Y-m-d').'.csv';

        return response()->stream(
            function () use ($fiscalperiod, $balanceSheetItems, $summary) {
                $file = fopen('php://output', 'w');

                fputcsv($file, [
                    'Fiscal Period: '.$fiscalperiod->name,
                    'Period: '.$fiscalperiod->opening_date.' to '.$fiscalperiod->closing_date,
                    'Generated: '.now()->format('Y-m-d H:i:s'),
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

                fputcsv($file, []);
                fputcsv($file, ['SUMMARY']);
                fputcsv($file, ['Total Assets',     number_format($summary['total_assets'], 2)]);
                fputcsv($file, ['Total Liabilities', number_format($summary['total_liabilities'], 2)]);
                fputcsv($file, ['Total Equity',     number_format($summary['total_equity'], 2)]);
                fputcsv($file, ['Opening Balance',  number_format($fiscalperiod->opening_balance, 2)]);
                fputcsv($file, ['Closing Balance',  number_format($fiscalperiod->closing_balance, 2)]);

                fclose($file);
            },
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename={$fileName}",
            ]
        );
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Stamp each MonthlyPeriod with live_income/expenses/net for view rendering.
     * (We don't persist these — they're derived from the Accounts ledger.)
     */
    private function attachLiveFinancials($monthlyPeriods, FiscalPeriods $fiscalPeriod)
    {
        foreach ($monthlyPeriods as $month) {
            $data = $this->financials->forMonth($fiscalPeriod, $month);
            $month->live_income = $data['total_income'];
            $month->live_expenses = $data['total_expenses'];
            $month->live_net = $data['net_income'];
        }

        return $monthlyPeriods;
    }

    /**
     * Guard against a {monthlyPeriod} URL parameter that doesn't belong to the
     * route-bound {fiscalperiod}. Returns 403, never falls through.
     */
    private function ensureMonthBelongsTo(FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod): void
    {
        if ($monthlyPeriod->fiscal_period_id !== $fiscalperiod->id) {
            abort(403);
        }
    }

    private function authorizeUser(FiscalPeriods $fiscalperiod): void
    {
        if ($fiscalperiod->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access');
        }
    }
}
