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
use App\Services\Property\PropertyContext;
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

        $hasOpenPeriod = $this->hasOpenPeriod();

        return view('admin.fiscalperiod.index', compact('fiscalPeriods', 'hasOpenPeriod'));
    }

    public function create()
    {
        // Only one fiscal period may be open at a time — the current one must be
        // closed before a new one is opened (getActiveFiscalPeriod() and the
        // fiscal.period middleware both assume a single open period).
        if ($this->hasOpenPeriod()) {
            return redirect()
                ->route('admin.fiscalperiod.index')
                ->with('warning', __('messages.flash_fp_close_current_first'));
        }

        return view('admin.fiscalperiod.open_close_periods');
    }

    public function store(StoreFiscalPeriodRequest $request)
    {
        // Authoritative guard: refuse a second open period even if the UI is bypassed.
        if ($this->hasOpenPeriod()) {
            return redirect()
                ->route('admin.fiscalperiod.index')
                ->with('warning', __('messages.flash_fp_close_current_first'));
        }

        $data = $request->validated();

        $fiscalPeriod = FiscalPeriods::create([
            ...$data,
            'user_id' => Auth::id(),
            'status' => 'open',
            // No opening balance sheet is collected at creation; the carry-forward
            // seed starts at zero (opening_assets/liabilities/equity default to 0).
            'opening_balance' => 0,
            'closing_balance' => 0,
        ]);

        $this->monthlyManager->generateForFiscalPeriod($fiscalPeriod);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', __('messages.flash_fp_created', ['count' => $fiscalPeriod->monthlyPeriods()->count()]));
    }

    /**
     * Dashboard view — financial summary + monthly periods with live numbers.
     */
    public function show(FiscalPeriods $fiscalperiod, PropertyContext $propertyContext)
    {
        $this->authorizeUser($fiscalperiod);

        [$consolidated, $showingAll, $selectedProperty, $scopePropertyId] = $this->resolveScope($propertyContext);

        $financialData = $this->financials->forPeriod($fiscalperiod, $scopePropertyId);
        $monthlyPeriods = $this->attachLiveFinancials(
            $fiscalperiod->monthlyPeriods()->orderBy('start_date')->get(),
            $fiscalperiod,
            $consolidated,
            $scopePropertyId,
        );
        $balanceSummary = $this->balanceSheetService->summary($fiscalperiod);

        // A single-property view has no cash seed of its own — the fiscal
        // period's opening balance is account-wide — so its running balance
        // starts at zero.
        $periodOpening = $consolidated ? (float) $fiscalperiod->opening_balance : 0.0;

        return view('admin.fiscalperiod.show', compact(
            'fiscalperiod', 'financialData', 'monthlyPeriods', 'balanceSummary',
            'consolidated', 'showingAll', 'selectedProperty', 'periodOpening'
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

        $datesChanged = $fiscalperiod->opening_date->toDateString() !== $data['opening_date']
            || $fiscalperiod->closing_date->toDateString() !== $data['closing_date'];

        if ($datesChanged) {
            // The monthly skeleton must mirror the period range. Frozen months
            // can't be resized, and shrinking must never strand ledger rows
            // outside every report window.
            if ($fiscalperiod->monthlyPeriods()->where('status', '!=', 'open')->exists()) {
                return back()->with('error', __('messages.flash_fp_dates_locked_closed_months'));
            }
            if ($fiscalperiod->accounts()
                ->where(fn ($q) => $q
                    ->where('transaction_date', '<', $data['opening_date'])
                    ->orWhere('transaction_date', '>', $data['closing_date']))
                ->exists()) {
                return back()->with('error', __('messages.flash_fp_dates_strand_ledger'));
            }
        }

        $fiscalperiod->update([
            ...$data,
            // Keep the cash carry-forward seed aligned with the opening assets.
            'opening_balance' => $data['opening_assets'],
        ]);

        if ($datesChanged) {
            // All months are open (guarded above) — regenerate the skeleton to
            // match the new range instead of leaving gaps/orphan months.
            $fiscalperiod->monthlyPeriods()->delete();
            $this->monthlyManager->generateForFiscalPeriod($fiscalperiod);
        }

        // Re-cascade the monthly carry-forward so the months reflect the new
        // opening figures.
        $this->monthlyManager->recalculateBalances($fiscalperiod);

        return redirect()
            ->route('admin.fiscalperiod.show', $fiscalperiod->id)
            ->with('success', __('messages.flash_fp_updated'));
    }

    public function destroy(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        // A closed period is frozen history, and accounts.fiscal_period_id is
        // ON DELETE CASCADE — deleting a period with ledger rows would silently
        // hard-delete its entire income/expense history. Refuse both cases;
        // only an open period with no recorded transactions can be removed.
        if ($fiscalperiod->status === 'closed' || $fiscalperiod->accounts()->exists()) {
            return redirect()
                ->route('admin.fiscalperiod.index')
                ->with('error', __('messages.flash_fp_delete_blocked'));
        }

        $fiscalperiod->balanceSheets()->delete();
        $fiscalperiod->monthlyPeriods()->delete();
        $fiscalperiod->delete();

        return redirect()
            ->route('admin.fiscalperiod.index')
            ->with('success', __('messages.flash_fp_deleted'));
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

        return back()->with('success', __('messages.flash_bs_item_added'));
    }

    public function deleteBalanceItem(FiscalPeriods $fiscalperiod, BalanceSheet $balanceSheet)
    {
        $this->authorizeUser($fiscalperiod);

        if ($balanceSheet->fiscal_period_id !== $fiscalperiod->id) {
            abort(403);
        }

        $balanceSheet->delete();

        return back()->with('success', __('messages.flash_bs_item_deleted'));
    }

    public function closeperiod(CloseFiscalPeriodRequest $request, FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        // Every month must be frozen first — a period closed over open months
        // leaves un-carried balances behind, and the whole close reads from
        // the months' chain.
        if ($fiscalperiod->monthlyPeriods()->where('status', 'open')->exists()) {
            return back()->with('error', __('messages.flash_fp_close_months_first'));
        }

        // The closing balance is COMPUTED from the ledger's carry-forward
        // cascade, never taken from the form (the old client-supplied value
        // let the frozen figure diverge from the books).
        $this->monthlyManager->recalculateBalances($fiscalperiod);

        $fiscalperiod->update(['status' => 'closed']);

        return redirect()
            ->route('admin.fiscalperiod.show', $fiscalperiod->id)
            ->with('success', __('messages.flash_fp_closed'));
    }

    // ============================================================
    // MONTHLY PERIOD MANAGEMENT
    // ============================================================

    public function showMonth(FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod, PropertyContext $propertyContext)
    {
        $this->authorizeUser($fiscalperiod);
        $this->ensureMonthBelongsTo($fiscalperiod, $monthlyPeriod);

        [$consolidated, $showingAll, $selectedProperty, $scopePropertyId] = $this->resolveScope($propertyContext);

        $financials = $this->financials->forMonth($fiscalperiod, $monthlyPeriod, $scopePropertyId);
        ['opening' => $openingBalance, 'closing' => $closingBalance, 'closing_is_firm' => $closingIsFirm]
            = $this->monthBalances($fiscalperiod, $monthlyPeriod, $financials, $consolidated, $scopePropertyId);

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
            'fiscalperiod', 'monthlyPeriod', 'financials', 'previousMonth', 'nextMonth', 'balanceSheet',
            'consolidated', 'showingAll', 'selectedProperty', 'openingBalance', 'closingBalance', 'closingIsFirm'
        ));
    }

    public function closeMonth(CloseMonthlyPeriodRequest $request, FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod)
    {
        $this->authorizeUser($fiscalperiod);
        $this->ensureMonthBelongsTo($fiscalperiod, $monthlyPeriod);

        if (! $monthlyPeriod->canClose()) {
            return back()->with('error', __('messages.flash_mp_cannot_close'));
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
                ->with('error', __('messages.flash_withdrawal_exceeds', [
                    'withdrawal' => number_format($withdrawal, 2),
                    'cash' => number_format(max(0, $availableCash), 2),
                    'month' => $monthlyPeriod->name,
                ]));
        }

        $result = $this->monthlyManager->closeMonth(
            $fiscalperiod,
            $monthlyPeriod,
            $withdrawal,
            $request->validated()['withdrawal_note'] ?? null,
        );

        $msg = __('messages.flash_month_closed', [
            'month' => $monthlyPeriod->name,
            'net' => number_format($result['net_income'], 2),
        ]);
        if ($result['owner_withdrawal'] > 0) {
            $msg .= __('messages.flash_month_owner_withdrawal', [
                'amount' => number_format($result['owner_withdrawal'], 2),
            ]);
        }
        $msg .= $result['next_month']
            ? __('messages.flash_month_closing_balance_carried', [
                'balance' => number_format($result['closing_balance'], 2),
                'month' => $result['next_month']->name,
            ])
            : __('messages.flash_month_closing_balance', [
                'balance' => number_format($result['closing_balance'], 2),
            ]);

        return back()->with('success', $msg);
    }

    public function reopenMonth(FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod)
    {
        $this->authorizeUser($fiscalperiod);
        $this->ensureMonthBelongsTo($fiscalperiod, $monthlyPeriod);

        if (! $monthlyPeriod->canReopen()) {
            return back()->with('error', __('messages.flash_mp_cannot_reopen'));
        }

        $result = $this->monthlyManager->reopenMonth($fiscalperiod, $monthlyPeriod);

        // Service returns the blocking next month if reopen would break the chain.
        if ($result instanceof MonthlyPeriod) {
            return back()->with('error', __('messages.flash_mp_reopen_blocked', ['month' => $result->name]));
        }

        return back()->with('success', __('messages.flash_mp_reopened', ['month' => $monthlyPeriod->name]));
    }

    public function recalculateBalances(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $carryForward = $this->monthlyManager->recalculateBalances($fiscalperiod);

        return back()->with(
            'success',
            __('messages.flash_balances_recalculated', ['balance' => number_format($carryForward, 2)])
        );
    }

    // ============================================================
    // REPORTS + EXPORTS
    // ============================================================

    public function reports(FiscalPeriods $fiscalperiod, PropertyContext $propertyContext)
    {
        $this->authorizeUser($fiscalperiod);

        // Reports follow the global top-bar property selector, so the whole app
        // shares one active-property context (null = the "All properties"
        // consolidated view).
        $selectedProperty = $propertyContext->activeProperty();
        $selectedPropertyId = $selectedProperty?->id;

        // Balance sheet & trial balance use account-level opening figures and
        // owner draws, so they always reflect the whole account — never a
        // single property.
        $balanceSheetItems = $fiscalperiod->balanceSheets()->get();
        $summary = $this->balanceSheetService->summary($fiscalperiod);
        $monthlyPeriods = $fiscalperiod->monthlyPeriods()->orderBy('start_date')->get();

        $monthlyData = [];
        foreach ($monthlyPeriods as $month) {
            $monthlyData[] = [
                'period' => $month,
                'financials' => $this->financials->forMonth($fiscalperiod, $month, $selectedPropertyId),
            ];
        }

        $periodFinancials = $this->financials->forPeriod($fiscalperiod, $selectedPropertyId);
        $incomeStatement = $this->reportsService->incomeStatement($fiscalperiod, $monthlyPeriods, $selectedPropertyId);
        $cashFlow = $this->reportsService->cashFlow($fiscalperiod, $monthlyPeriods, $selectedPropertyId);
        $trialBalance = $this->reportsService->trialBalance($fiscalperiod);

        return view('admin.fiscalperiod.period_reports_exports', compact(
            'fiscalperiod', 'balanceSheetItems', 'summary',
            'monthlyPeriods', 'monthlyData', 'periodFinancials',
            'incomeStatement', 'cashFlow', 'trialBalance',
            'selectedProperty', 'selectedPropertyId'
        ));
    }

    public function exportPDF(FiscalPeriods $fiscalperiod, PropertyContext $propertyContext)
    {
        $this->authorizeUser($fiscalperiod);

        $selectedProperty = $propertyContext->activeProperty();
        $selectedPropertyId = $selectedProperty?->id;

        $balanceSheetItems = $fiscalperiod->balanceSheets()->get();
        $summary = $this->balanceSheetService->summary($fiscalperiod);
        $html = $this->balanceSheetService->renderHtml($fiscalperiod, $balanceSheetItems);
        $periodFinancials = $this->financials->forPeriod($fiscalperiod, $selectedPropertyId);

        return view('admin.fiscalperiod.export-pdf', compact(
            'fiscalperiod', 'balanceSheetItems', 'summary', 'html', 'periodFinancials', 'selectedProperty'
        ));
    }

    public function printMonthlyPDF(FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod, PropertyContext $propertyContext)
    {
        $this->authorizeUser($fiscalperiod);
        $this->ensureMonthBelongsTo($fiscalperiod, $monthlyPeriod);

        [$consolidated, $showingAll, $selectedProperty, $scopePropertyId] = $this->resolveScope($propertyContext);

        $financials = $this->financials->forMonth($fiscalperiod, $monthlyPeriod, $scopePropertyId);
        ['opening' => $openingBalance, 'closing' => $closingBalance, 'closing_is_firm' => $closingIsFirm]
            = $this->monthBalances($fiscalperiod, $monthlyPeriod, $financials, $consolidated, $scopePropertyId);
        $balanceSheet = $this->balanceSheetService->summaryAsOf($fiscalperiod, $monthlyPeriod);

        return view('admin.fiscalperiod.monthly-period-pdf', compact(
            'fiscalperiod', 'monthlyPeriod', 'financials', 'balanceSheet',
            'consolidated', 'showingAll', 'selectedProperty', 'openingBalance', 'closingBalance', 'closingIsFirm'
        ));
    }

    public function exportCSV(FiscalPeriods $fiscalperiod, PropertyContext $propertyContext)
    {
        $this->authorizeUser($fiscalperiod);

        $selectedProperty = $propertyContext->activeProperty();
        $selectedPropertyId = $selectedProperty?->id;

        $balanceSheetItems = $fiscalperiod->balanceSheets()->orderBy('item_type')->get();
        $summary = $this->balanceSheetService->summary($fiscalperiod);
        $periodFinancials = $this->financials->forPeriod($fiscalperiod, $selectedPropertyId);

        $scopeLabel = $selectedProperty?->name ?? 'All Properties (consolidated)';
        $scopeSlug = $selectedProperty ? str()->slug($selectedProperty->name) : 'all';
        $fileName = "fiscal_report_{$fiscalperiod->id}_{$scopeSlug}_".now()->format('Y-m-d').'.csv';

        return response()->stream(
            function () use ($fiscalperiod, $balanceSheetItems, $summary, $periodFinancials, $scopeLabel) {
                $file = fopen('php://output', 'w');

                fputcsv($file, [
                    'Fiscal Period: '.$fiscalperiod->name,
                    'Period: '.$fiscalperiod->opening_date.' to '.$fiscalperiod->closing_date,
                    'Property: '.$scopeLabel,
                    'Generated: '.now()->format('Y-m-d H:i:s'),
                ]);

                // Income / revenue summary — scoped to the selected property.
                fputcsv($file, []);
                fputcsv($file, ['INCOME STATEMENT (Property: '.$scopeLabel.')']);
                fputcsv($file, ['Account', 'Amount']);
                fputcsv($file, ['Rent Income',   number_format($periodFinancials['rent_income'], 2, '.', '')]);
                fputcsv($file, ['Late Fees',     number_format($periodFinancials['late_fees'], 2, '.', '')]);
                fputcsv($file, ['Other Income',  number_format($periodFinancials['other_income'], 2, '.', '')]);
                fputcsv($file, ['Total Revenue', number_format($periodFinancials['total_income'], 2, '.', '')]);
                fputcsv($file, ['Total Expenses', number_format($periodFinancials['total_expenses'], 2, '.', '')]);
                fputcsv($file, ['Net Income',    number_format($periodFinancials['net_income'], 2, '.', '')]);

                fputcsv($file, []);
                fputcsv($file, ['BALANCE SHEET ITEMS (account-wide)']);
                fputcsv($file, ['Item Type', 'Sub Type', 'Name', 'Amount', 'As Of Date', 'Reference Number', 'Notes']);

                foreach ($balanceSheetItems as $item) {
                    fputcsv($file, [
                        ucfirst($item->item_type),
                        ucfirst(str_replace('_', ' ', $item->sub_type)),
                        $item->name,
                        number_format($item->amount, 2, '.', ''),
                        $item->as_of_date,
                        $item->reference_number,
                        $item->notes,
                    ]);
                }

                fputcsv($file, []);
                fputcsv($file, ['BALANCE SHEET SUMMARY (account-wide)']);
                fputcsv($file, ['Total Assets',     number_format($summary['total_assets'], 2, '.', '')]);
                fputcsv($file, ['Total Liabilities', number_format($summary['total_liabilities'], 2, '.', '')]);
                fputcsv($file, ['Total Equity',     number_format($summary['total_equity'], 2, '.', '')]);
                fputcsv($file, ['Opening Balance',  number_format($fiscalperiod->opening_balance, 2, '.', '')]);
                fputcsv($file, ['Closing Balance',  number_format($fiscalperiod->closing_balance, 2, '.', '')]);

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
     * Resolve the active property scope for the fiscal-period display pages.
     *
     * A single-property account (or the explicit "All properties" view) is
     * treated as *consolidated*: it reads the stored account-wide cash
     * carry-forward and offers the real month-close controls. When one property
     * is selected out of several, the figures are scoped to that property and
     * the balance flow becomes a live running total — the month-close, owner
     * draws and balance sheet stay account-wide (only offered when consolidated).
     *
     * @return array{0: bool, 1: bool, 2: \App\Models\Property|null, 3: int|null}
     *                                                                            [consolidated, showingAll, selectedProperty, scopePropertyId]
     */
    private function resolveScope(PropertyContext $propertyContext): array
    {
        $showingAll = $propertyContext->showingAllProperties();
        $consolidated = $showingAll || $propertyContext->hasSingleProperty();
        $selectedProperty = $consolidated ? null : $propertyContext->activeProperty();

        return [$consolidated, $showingAll, $selectedProperty, $selectedProperty?->id];
    }

    /**
     * Opening/closing cash balance for a single month.
     *
     * Consolidated: use the stored account-wide carry-forward (firm once the
     * month is closed). Per-property: no stored balance exists, so build a live
     * running total from the property's net income across the period's earlier
     * months (owner draws stay account-wide and are excluded here).
     *
     * @return array{opening: float, closing: float, closing_is_firm: bool}
     */
    private function monthBalances(
        FiscalPeriods $fiscalPeriod,
        MonthlyPeriod $monthlyPeriod,
        array $financials,
        bool $consolidated,
        ?int $propertyId,
    ): array {
        if ($consolidated) {
            $opening = (float) $monthlyPeriod->opening_balance;
            $firm = $monthlyPeriod->isClosed();

            return [
                'opening' => $opening,
                'closing' => $firm ? (float) $monthlyPeriod->closing_balance : $opening + $financials['net_income'],
                'closing_is_firm' => $firm,
            ];
        }

        $opening = 0.0;
        $earlierMonths = $fiscalPeriod->monthlyPeriods()
            ->where('start_date', '<', $monthlyPeriod->start_date)
            ->orderBy('start_date')
            ->get();
        foreach ($earlierMonths as $earlier) {
            $opening += $this->financials->forMonth($fiscalPeriod, $earlier, $propertyId)['net_income'];
        }

        return [
            'opening' => round($opening, 2),
            'closing' => round($opening + $financials['net_income'], 2),
            'closing_is_firm' => false,
        ];
    }

    /**
     * Stamp each MonthlyPeriod with live income/expenses/net and a running
     * opening/closing balance for view rendering. (We don't persist these —
     * they're derived from the Accounts ledger.)
     *
     * Consolidated uses the stored account-wide carry-forward; a per-property
     * scope rebuilds the running balance live from that property's net income
     * (starting at zero, owner draws excluded).
     */
    private function attachLiveFinancials($monthlyPeriods, FiscalPeriods $fiscalPeriod, bool $consolidated, ?int $propertyId)
    {
        $running = 0.0;

        foreach ($monthlyPeriods as $month) {
            $data = $this->financials->forMonth($fiscalPeriod, $month, $propertyId);
            $month->live_income = $data['total_income'];
            $month->live_expenses = $data['total_expenses'];
            $month->live_net = $data['net_income'];

            if ($consolidated) {
                $month->live_opening = (float) $month->opening_balance;
                $month->live_closing = $month->isClosed()
                    ? (float) $month->closing_balance
                    : (float) $month->opening_balance + $data['net_income'];
            } else {
                $month->live_opening = round($running, 2);
                $month->live_closing = round($running + $data['net_income'], 2);
                $running = $month->live_closing;
            }
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

    /**
     * Does this admin already have an open fiscal period? A new period can't be
     * opened while one is still open — the current one must be closed first.
     */
    private function hasOpenPeriod(): bool
    {
        return FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'open')
            ->exists();
    }
}
