<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Shared\RevenueExpenseController as SharedRevenueExpenseController;
use App\Models\Accounts;
use App\Models\FiscalPeriods;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;

/**
 * Supervisor panel Revenue & Expense. All behaviour lives in the shared base;
 * this class only pins the supervisor-specific hooks: which fiscal periods are
 * visible, whose ledger is written, and the supervisor property guards.
 */
class RevenueExpenseController extends SharedRevenueExpenseController
{
    protected function panel(): string
    {
        return 'supervisor';
    }

    /**
     * Supervisors read from the admin's fiscal periods (not their own).
     */
    protected function fiscalPeriodsQuery(): Builder
    {
        return FiscalPeriods::whereHas('user', fn ($q) => $q->role('admin'));
    }

    /**
     * Supervisors write/read ledger rows under the admin's user_id, resolved
     * from the active fiscal period.
     */
    protected function ledgerUserId(): ?int
    {
        return $this->getActiveFiscalPeriod()?->user_id;
    }

    protected function khqrRoutePrefix(): string
    {
        return 'supervisor.revenue_expense';
    }

    /**
     * Supervisors can't open fiscal periods — send them back to their
     * dashboard with a warning instead of the create-period form.
     */
    protected function missingPeriodRedirect(string $messageKey = 'messages.flash_fp_required'): RedirectResponse
    {
        return redirect()->route('supervisor.dashboard')
            ->with('warning', __($messageKey));
    }

    /**
     * Supervisor authorization: the row must belong to the active fiscal period
     * and (when it is tagged to a property) to one of the supervisor's
     * assigned properties.
     */
    protected function authorizeOtherExpenseDelete(Accounts $expense): void
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod || $expense->fiscal_period_id !== $activePeriod->id) {
            abort(403);
        }
        $this->authorizeSupervisorPropertyRow($expense->property_id);
    }
}
