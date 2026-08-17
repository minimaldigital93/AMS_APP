<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Shared\RevenueExpenseController as SharedRevenueExpenseController;
use App\Models\Accounts;
use App\Models\FiscalPeriods;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;

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

    /** Admins read the account's fiscal periods (co-admins share the owner's). */
    protected function fiscalPeriodsQuery(): Builder
    {
        return FiscalPeriods::where('user_id', current_account_id());
    }

    /** The books hang off the account owner's user id, not the acting admin's. */
    protected function ledgerUserId(): ?int
    {
        return current_account_id();
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

    /** Admin authorization: the row must belong to the acting admin's account. */
    protected function authorizeOtherExpenseDelete(Accounts $expense): void
    {
        if ($expense->user_id !== current_account_id()) {
            abort(403);
        }
    }
}
