<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformExpense;
use App\Models\PlatformFiscalPeriod;
use App\Models\PlatformWithdrawal;
use App\Services\Platform\PlatformFinanceService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Platform profit & loss for the superadmin: subscription revenue vs recorded
 * platform expenses, broken down month-by-month across a fiscal period. Reads
 * across the whole platform.
 */
class FinanceController extends Controller
{
    public function __construct(private PlatformFinanceService $finance) {}

    public function index(Request $request): View
    {
        $periods = $this->finance->periods();

        // A new period can't be opened while one is still open.
        $hasOpenPeriod = $periods->contains(fn ($p) => ! $p->isClosed());

        // No period defined yet — show the empty state with a "create" prompt.
        if ($periods->isEmpty()) {
            return view('superadmin.finance.index', [
                'period' => null,
                'periods' => $periods,
                'pnl' => null,
                'expenses' => null,
                'categories' => PlatformExpense::CATEGORIES,
                'hasOpenPeriod' => $hasOpenPeriod,
            ]);
        }

        $period = $periods->firstWhere('id', (int) $request->query('period'))
            ?? $this->defaultPeriod($periods);

        $pnl = $this->finance->forPeriod($period);

        $expenses = PlatformExpense::query()
            ->whereBetween('spent_at', [
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
            ])
            ->orderByDesc('spent_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('superadmin.finance.index', [
            'period' => $period,
            'periods' => $periods,
            'pnl' => $pnl,
            'expenses' => $expenses,
            'categories' => PlatformExpense::CATEGORIES,
            'hasOpenPeriod' => $hasOpenPeriod,
        ]);
    }

    /** Render the period's income statement (P&L) as a downloadable PDF. */
    public function statement(PlatformFiscalPeriod $period)
    {
        $pnl = $this->finance->forPeriod($period);

        // Expenses grouped by category — the cost breakdown on the statement.
        $byCategory = PlatformExpense::query()
            ->whereBetween('spent_at', [
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
            ])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => PlatformExpense::CATEGORIES[$row->category] ?? ucfirst($row->category),
                'total' => (float) $row->total,
            ]);

        $data = compact('period', 'pnl', 'byCategory');
        $fileName = 'income-statement-'.\Illuminate\Support\Str::slug($period->name).'-'.now()->format('Y-m-d').'.pdf';

        // Use Dompdf when available; otherwise fall back to a printable HTML view.
        try {
            if (class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf') || class_exists('\\PDF')) {
                return \PDF::loadView('superadmin.finance.income-statement-pdf', $data)->download($fileName);
            }
        } catch (\Throwable $e) {
            // Fall through to HTML view.
        }

        return response()->view('superadmin.finance.income-statement-pdf', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', 'integer'],
            'category' => ['required', 'string', 'in:'.implode(',', array_keys(PlatformExpense::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'spent_at' => ['required', 'date'],
            'is_recurring' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        PlatformExpense::create([
            'category' => $validated['category'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency'] ?? 'USD'),
            'spent_at' => $validated['spent_at'],
            'is_recurring' => $request->boolean('is_recurring'),
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('superadmin.finance.index', ['period' => $validated['period'] ?? null])
            ->with('success', __('Platform expense recorded.'));
    }

    public function destroy(Request $request, PlatformExpense $expense): RedirectResponse
    {
        $expense->delete();

        return redirect()
            ->route('superadmin.finance.index', ['period' => $request->input('period')])
            ->with('success', __('Platform expense deleted.'));
    }

    /** Close a month — owner withdraws the profit or carries it forward. */
    public function closeMonth(Request $request, PlatformFiscalPeriod $period): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'decision' => ['required', 'in:carry,withdraw'],
            'owner_withdrawal' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'withdrawal_note' => ['nullable', 'string', 'max:255'],
        ]);

        $withdrawal = $validated['decision'] === 'withdraw'
            ? (float) ($validated['owner_withdrawal'] ?? 0)
            : 0.0;

        $this->finance->closeMonth(
            $period,
            (int) $validated['year'],
            (int) $validated['month'],
            $withdrawal,
            $validated['withdrawal_note'] ?? null,
        );

        return redirect()
            ->route('superadmin.finance.index', ['period' => $period->id])
            ->with('success', __('Month closed.'));
    }

    /** Reopen the most recently closed month. */
    public function reopenMonth(Request $request, PlatformFiscalPeriod $period): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $this->finance->reopenMonth($period, (int) $validated['year'], (int) $validated['month']);

        return redirect()
            ->route('superadmin.finance.index', ['period' => $period->id])
            ->with('success', __('Month reopened.'));
    }

    /** Create a fiscal period spanning an exact start date to an end date. */
    public function storePeriod(Request $request): RedirectResponse
    {
        // Only one platform fiscal period may be open at a time — the current one
        // must be closed before a new one is opened, so the carry-forward chain
        // stays unambiguous.
        if (PlatformFiscalPeriod::where('status', 'open')->exists()) {
            return redirect()
                ->route('superadmin.finance.index')
                ->with('warning', __('messages.flash_fp_close_current_first'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        // Date ranges must not overlap another period, or the same revenue would
        // count in two periods and the carry-forward chain would be ambiguous.
        if ($this->overlapsAnotherPeriod($validated['start_date'], $validated['end_date'])) {
            return redirect()
                ->route('superadmin.finance.index')
                ->with('warning', __('messages.flash_fp_period_overlap'));
        }

        $period = $this->finance->createPeriod(
            $validated['name'],
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date']),
            (float) ($validated['opening_balance'] ?? 0),
        );

        return redirect()
            ->route('superadmin.finance.index', ['period' => $period->id])
            ->with('success', __('Fiscal period created.'));
    }

    /** Rename a fiscal period, adjust its date range, or change its opening balance. */
    public function updatePeriod(Request $request, PlatformFiscalPeriod $period): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        // Re-dating must not push this period onto another's range.
        if ($this->overlapsAnotherPeriod($validated['start_date'], $validated['end_date'], $period->id)) {
            return redirect()
                ->route('superadmin.finance.index', ['period' => $period->id])
                ->with('warning', __('messages.flash_fp_period_overlap'));
        }

        $this->finance->updatePeriod(
            $period,
            $validated['name'],
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date']),
            (float) ($validated['opening_balance'] ?? 0),
        );

        return redirect()
            ->route('superadmin.finance.index', ['period' => $period->id])
            ->with('success', __('Fiscal period updated.'));
    }

    /** Delete a fiscal period. */
    public function destroyPeriod(PlatformFiscalPeriod $period): RedirectResponse
    {
        $this->finance->deletePeriod($period);

        return redirect()
            ->route('superadmin.finance.index')
            ->with('success', __('Fiscal period deleted.'));
    }

    /** Close the whole fiscal period — locks its months and carries the balance into the next period. */
    public function closePeriod(PlatformFiscalPeriod $period): RedirectResponse
    {
        $next = $this->finance->closePeriod($period);

        return redirect()
            ->route('superadmin.finance.index', ['period' => $next->id])
            ->with('success', __('Period closed — :amount carried forward into :name.', [
                'amount' => '$'.number_format((float) $next->opening_balance, 2),
                'name' => $next->name,
            ]));
    }

    /** Record an ad-hoc owner withdrawal against the period's carried cash. */
    public function storeWithdrawal(Request $request, PlatformFiscalPeriod $period): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->finance->withdraw($period, (float) $validated['amount'], $validated['note'] ?? null);

        return redirect()
            ->route('superadmin.finance.index', ['period' => $period->id])
            ->with('success', __('Withdrawal recorded.'));
    }

    /** Undo an ad-hoc withdrawal, returning the cash to the carried balance. */
    public function destroyWithdrawal(PlatformWithdrawal $withdrawal): RedirectResponse
    {
        $periodId = $withdrawal->platform_fiscal_period_id;
        $this->finance->deleteWithdrawal($withdrawal);

        return redirect()
            ->route('superadmin.finance.index', ['period' => $periodId])
            ->with('success', __('Withdrawal removed.'));
    }

    /** Reopen a closed fiscal period — unlocks its months. */
    public function reopenPeriod(PlatformFiscalPeriod $period): RedirectResponse
    {
        // Single-open invariant: closing a period spins up an open successor, so
        // reopening this one while a later period is already open would leave two
        // open at once. Make the user close the open one first.
        if (PlatformFiscalPeriod::where('status', 'open')->where('id', '!=', $period->id)->exists()) {
            return redirect()
                ->route('superadmin.finance.index', ['period' => $period->id])
                ->with('warning', __('messages.flash_fp_close_before_reopen'));
        }

        $this->finance->reopenPeriod($period);

        return redirect()
            ->route('superadmin.finance.index', ['period' => $period->id])
            ->with('success', __('Fiscal period reopened.'));
    }

    /**
     * Does [$start, $end] overlap any existing period (optionally ignoring one,
     * for the edit case)? Two ranges overlap when each starts on or before the
     * other ends.
     */
    private function overlapsAnotherPeriod(string $start, string $end, ?int $ignoreId = null): bool
    {
        return PlatformFiscalPeriod::query()
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    /** Default to the period covering today, else the most recent one. */
    private function defaultPeriod($periods): PlatformFiscalPeriod
    {
        $today = now()->toDateString();

        return $periods->first(fn ($p) => $p->start_date->toDateString() <= $today && $today <= $p->end_date->toDateString())
            ?? $periods->first();
    }
}
