<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformExpense;
use App\Services\Platform\PlatformFinanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Platform profit & loss for the superadmin: subscription revenue vs recorded
 * platform expenses, monthly and yearly. Reads across the whole platform.
 */
class FinanceController extends Controller
{
    public function __construct(private PlatformFinanceService $finance) {}

    public function index(Request $request): View
    {
        $years = $this->finance->activeYears();
        $year = (int) $request->query('year', (int) now()->year);
        if (! in_array($year, $years, true)) {
            $year = (int) now()->year;
        }

        $pnl = $this->finance->forYear($year);

        $expenses = PlatformExpense::query()
            ->whereYear('spent_at', $year)
            ->orderByDesc('spent_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('superadmin.finance.index', [
            'pnl' => $pnl,
            'year' => $year,
            'years' => $years,
            'expenses' => $expenses,
            'categories' => PlatformExpense::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', array_keys(PlatformExpense::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
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
            ->route('superadmin.finance.index', ['year' => date('Y', strtotime($validated['spent_at']))])
            ->with('success', __('Platform expense recorded.'));
    }

    public function destroy(PlatformExpense $expense): RedirectResponse
    {
        $year = $expense->spent_at?->year ?? now()->year;
        $expense->delete();

        return redirect()
            ->route('superadmin.finance.index', ['year' => $year])
            ->with('success', __('Platform expense deleted.'));
    }

    /** Close a month — owner withdraws the profit or carries it forward. */
    public function closeMonth(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'decision' => ['required', 'in:carry,withdraw'],
            'owner_withdrawal' => ['nullable', 'numeric', 'min:0'],
            'withdrawal_note' => ['nullable', 'string', 'max:255'],
        ]);

        $withdrawal = $validated['decision'] === 'withdraw'
            ? (float) ($validated['owner_withdrawal'] ?? 0)
            : 0.0;

        $this->finance->closeMonth(
            (int) $validated['year'],
            (int) $validated['month'],
            $withdrawal,
            $validated['withdrawal_note'] ?? null,
        );

        return redirect()
            ->route('superadmin.finance.index', ['year' => $validated['year']])
            ->with('success', __('Month closed.'));
    }

    /** Reopen the most recently closed month. */
    public function reopenMonth(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $this->finance->reopenMonth((int) $validated['year'], (int) $validated['month']);

        return redirect()
            ->route('superadmin.finance.index', ['year' => $validated['year']])
            ->with('success', __('Month reopened.'));
    }
}
