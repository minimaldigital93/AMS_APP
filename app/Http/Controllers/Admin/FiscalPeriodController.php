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
use App\Services\FiscalPeriod\MonthClosePreflight;
use App\Services\FiscalPeriod\MonthlyPeriodManager;
use App\Services\Property\PropertyContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalPeriodController extends Controller
{
    public function __construct(
        private FiscalPeriodFinancialsService $financials,
        private BalanceSheetService $balanceSheetService,
        private MonthlyPeriodManager $monthlyManager,
        private FiscalPeriodReportsService $reportsService,
        private MonthClosePreflight $closePreflight,
    ) {}

    // --- Fiscal period CRUD ---

    /**
     * List this admin's fiscal periods.
     */
    public function index(): View
    {
        $fiscalPeriods = FiscalPeriods::where('user_id', current_account_id())
            ->orderBy('opening_date', 'desc')
            ->paginate(15);

        $hasOpenPeriod = $this->hasOpenPeriod();

        return view('admin.fiscalperiod.index', compact('fiscalPeriods', 'hasOpenPeriod'));
    }

    /**
     * Show the form for opening a period.
     */
    public function create(): View|RedirectResponse
    {
        // Only one period may be open at a time — getActiveFiscalPeriod() and
        // the fiscal.period middleware both assume exactly one.
        if ($this->hasOpenPeriod()) {
            return redirect()
                ->route('admin.fiscalperiod.index')
                ->with('warning', __('messages.flash_fp_close_current_first'));
        }

        return view('admin.fiscalperiod.open_close_periods');
    }

    /**
     * Open a period and generate its monthly skeleton.
     */
    public function store(StoreFiscalPeriodRequest $request): RedirectResponse
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
            'user_id' => current_account_id(),
            'status' => 'open',
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
    public function show(FiscalPeriods $fiscalperiod, PropertyContext $propertyContext): View
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

        // The opening balance is account-wide, so a single-property view has no
        // cash seed of its own and starts at zero.
        $periodOpening = $consolidated ? (float) $fiscalperiod->opening_balance : 0.0;

        return view('admin.fiscalperiod.show', compact(
            'fiscalperiod', 'financialData', 'monthlyPeriods', 'balanceSummary',
            'consolidated', 'showingAll', 'selectedProperty', 'periodOpening'
        ));
    }

    /**
     * Show the form for editing a period's dates and opening figures.
     */
    public function edit(FiscalPeriods $fiscalperiod): View
    {
        $this->authorizeUser($fiscalperiod);

        return view('admin.fiscalperiod.edit', compact('fiscalperiod'));
    }

    /**
     * Update a period, regenerating the monthly skeleton if its dates moved.
     */
    public function update(UpdateFiscalPeriodRequest $request, FiscalPeriods $fiscalperiod): RedirectResponse
    {
        $this->authorizeUser($fiscalperiod);

        $data = $request->validated();

        $datesChanged = $fiscalperiod->opening_date->toDateString() !== $data['opening_date']
            || $fiscalperiod->closing_date->toDateString() !== $data['closing_date'];

        if ($datesChanged) {
            // The monthly skeleton mirrors the period range: frozen months can't
            // be resized, and shrinking must not strand ledger rows outside it.
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

    /**
     * Delete an open period that has never been posted to.
     */
    public function destroy(FiscalPeriods $fiscalperiod): RedirectResponse
    {
        $this->authorizeUser($fiscalperiod);

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

    // --- Balance sheet ---

    /**
     * The period's balance sheet items, grouped by type.
     */
    public function balanceSheet(FiscalPeriods $fiscalperiod): View
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()
            ->orderBy('item_type')
            ->get()
            ->groupBy('item_type');

        $summary = $this->balanceSheetService->summary($fiscalperiod);

        return view('admin.fiscalperiod.balance_sheet_items', compact('fiscalperiod', 'balanceSheetItems', 'summary'));
    }

    /**
     * Add a balance sheet item to the period.
     */
    public function storeBalanceItem(StoreBalanceSheetItemRequest $request, FiscalPeriods $fiscalperiod): RedirectResponse
    {
        $this->authorizeUser($fiscalperiod);

        BalanceSheet::create([
            ...$request->validated(),
            'fiscal_period_id' => $fiscalperiod->id,
            'user_id' => current_account_id(),
        ]);

        return back()->with('success', __('messages.flash_bs_item_added'));
    }

    /**
     * Remove a balance sheet item from the period.
     */
    public function deleteBalanceItem(FiscalPeriods $fiscalperiod, BalanceSheet $balanceSheet): RedirectResponse
    {
        $this->authorizeUser($fiscalperiod);

        if ($balanceSheet->fiscal_period_id !== $fiscalperiod->id) {
            abort(403);
        }

        $balanceSheet->delete();

        return back()->with('success', __('messages.flash_bs_item_deleted'));
    }

    /**
     * Close the period once every month inside it is frozen.
     */
    public function closeperiod(CloseFiscalPeriodRequest $request, FiscalPeriods $fiscalperiod): RedirectResponse
    {
        $this->authorizeUser($fiscalperiod);

        // Every month must be frozen first — the close reads the months' chain,
        // and an open month leaves its balance un-carried.
        if ($fiscalperiod->monthlyPeriods()->where('status', 'open')->exists()) {
            return back()->with('error', __('messages.flash_fp_close_months_first'));
        }

        // Computed from the carry-forward cascade, never taken from the form —
        // the old client-supplied value let the frozen figure drift.
        $this->monthlyManager->recalculateBalances($fiscalperiod);

        $fiscalperiod->update(['status' => 'closed']);

        return redirect()
            ->route('admin.fiscalperiod.show', $fiscalperiod->id)
            ->with('success', __('messages.flash_fp_closed'));
    }

    // --- Monthly periods ---

    /**
     * One month's figures, balances and pre-close unpaid check.
     */
    public function showMonth(FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod, PropertyContext $propertyContext): View
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

        // Who still owes rent/utilities. Only needed where the close button
        // lives (consolidated view) and only while the month is open.
        $unpaidPreflight = ($consolidated && $monthlyPeriod->canClose())
            ? $this->closePreflight->unpaidFor($monthlyPeriod)
            : null;

        return view('admin.fiscalperiod.monthly_period_show', compact(
            'fiscalperiod', 'monthlyPeriod', 'financials', 'previousMonth', 'nextMonth', 'balanceSheet',
            'consolidated', 'showingAll', 'selectedProperty', 'openingBalance', 'closingBalance', 'closingIsFirm',
            'unpaidPreflight'
        ));
    }

    /**
     * Freeze a month, book any owner withdrawal and carry the balance forward.
     */
    public function closeMonth(CloseMonthlyPeriodRequest $request, FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod): RedirectResponse
    {
        $this->authorizeUser($fiscalperiod);
        $this->ensureMonthBelongsTo($fiscalperiod, $monthlyPeriod);

        if (! $monthlyPeriod->canClose()) {
            return back()->with('error', __('messages.flash_mp_cannot_close'));
        }

        $withdrawal = (float) $request->validated()['owner_withdrawal'];

        // A withdrawal can't exceed month-end cash (opening + net income), or
        // the carry-forward goes negative.
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

    /**
     * Reopen the most recently closed month, if the chain allows it.
     */
    public function reopenMonth(FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod): RedirectResponse
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

    /**
     * Re-run the carry-forward cascade across every month of the period.
     */
    public function recalculateBalances(FiscalPeriods $fiscalperiod): RedirectResponse
    {
        $this->authorizeUser($fiscalperiod);

        $carryForward = $this->monthlyManager->recalculateBalances($fiscalperiod);

        return back()->with(
            'success',
            __('messages.flash_balances_recalculated', ['balance' => number_format($carryForward, 2)])
        );
    }

    // --- Reports and exports ---

    /**
     * Income statement, cash flow, trial balance and the monthly breakdown.
     */
    public function reports(FiscalPeriods $fiscalperiod, PropertyContext $propertyContext): View
    {
        $this->authorizeUser($fiscalperiod);

        $selectedProperty = $propertyContext->activeProperty();
        $selectedPropertyId = $selectedProperty?->id;
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

    /**
     * Printable one-month report.
     */
    public function printMonthlyPDF(FiscalPeriods $fiscalperiod, MonthlyPeriod $monthlyPeriod, PropertyContext $propertyContext): View
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

    /**
     * Stream the period's figures as CSV.
     */
    public function exportCSV(FiscalPeriods $fiscalperiod, PropertyContext $propertyContext): StreamedResponse
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

                // Formula-injection guard: a value starting = + - @ (or tab/CR)
                // executes in Excel/Sheets; a leading ' forces plain text.
                $safe = function ($value) {
                    $value = (string) $value;

                    return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
                };

                fputcsv($file, [
                    'Fiscal Period: '.$safe($fiscalperiod->name),
                    'Period: '.$fiscalperiod->opening_date.' to '.$fiscalperiod->closing_date,
                    'Property: '.$safe($scopeLabel),
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
                        $safe($item->name),
                        number_format($item->amount, 2, '.', ''),
                        $item->as_of_date,
                        $safe($item->reference_number),
                        $safe($item->notes),
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

    // --- Helpers ---

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
        $consolidated = $showingAll || $propertyContext->hasNothingToConsolidate();
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
     *
     * @param  Collection<int, MonthlyPeriod>  $monthlyPeriods
     * @return Collection<int, MonthlyPeriod>
     */
    private function attachLiveFinancials(Collection $monthlyPeriods, FiscalPeriods $fiscalPeriod, bool $consolidated, ?int $propertyId): Collection
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

    /**
     * A period is only ever readable/writable within the account that owns it
     * (the owner's user id — co-admins share the same books).
     */
    private function authorizeUser(FiscalPeriods $fiscalperiod): void
    {
        if ($fiscalperiod->user_id !== current_account_id()) {
            abort(403, 'Unauthorized access');
        }
    }

    /**
     * Does this admin already have an open fiscal period? A new period can't be
     * opened while one is still open — the current one must be closed first.
     */
    private function hasOpenPeriod(): bool
    {
        return FiscalPeriods::where('user_id', current_account_id())
            ->where('status', 'open')
            ->exists();
    }
}
