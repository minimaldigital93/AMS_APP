<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Shared\RevenueExpenseController as SharedRevenueExpenseController;
use App\Models\Accounts;
use App\Models\FiscalPeriods;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Admin panel Revenue & Expense. All behaviour lives in the shared base; this
 * class only pins the admin-specific hooks. The base's supervisor property
 * guards no-op for admins (seesWholeAccount()), so an admin sees the whole
 * account, scoped only by the global active-property selector.
 */
class RevenueExpenseController extends SharedRevenueExpenseController
{
    protected function panel(): string
    {
        return 'admin';
    }

    /** Admins read their own fiscal periods. */
    protected function fiscalPeriodsQuery(): Builder
    {
        return FiscalPeriods::where('user_id', Auth::id());
    }

    /** Admins write/read ledger rows under their own user_id. */
    protected function ledgerUserId(): ?int
    {
        return Auth::id();
    }

    protected function khqrRoutePrefix(): string
    {
        return 'admin.revenue_expense';
    }

    /** No open period → send the admin to the create-period form. */
    protected function missingPeriodRedirect(string $messageKey = 'messages.flash_fp_required'): RedirectResponse
    {
        return redirect()->route('admin.fiscalperiod.create')
            ->with('warning', __($messageKey));
    }

    /** Admin authorization: the row must belong to the current user. */
    protected function authorizeOtherExpenseDelete(Accounts $expense): void
    {
        if ($expense->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
