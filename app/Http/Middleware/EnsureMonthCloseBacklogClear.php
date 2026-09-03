<?php

namespace App\Http\Middleware;

use App\Services\FiscalPeriod\MonthCloseBacklog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stop new money going into the books while two or more finished months sit
 * un-closed. See MonthCloseBacklog for what "finished" and "two" mean.
 *
 * Sits beside `fiscal.period` on the revenue-expense groups of both panels, and
 * is deliberately narrower than it in three ways:
 *
 * - **Writes only.** A safe request passes. Every page stays readable, because
 *   reading them is how the operator works out what the month owes before
 *   closing it — and the dashboard banner has already asked. Blocking the
 *   reports would be punishing the wrong half of the workflow.
 * - **Reversing a payment is exempt.** Undoing a mistake is how a month gets
 *   ready to close, and a reversal is only reachable while its month is open
 *   (PaymentReversalService). Blocking it would deadlock the very close this
 *   middleware is demanding: fix July before it closes, but no writes until
 *   July closes.
 * - **Superadmin is exempt**, as everywhere else — the platform operator has no
 *   books of their own here.
 *
 * An admin is sent to the month's own page, where the close button lives. A
 * supervisor cannot close months, so they are bounced back with a notice to ask
 * the account owner — the same shape `EnsureFiscalPeriodExists` and
 * `EnsureSubscriptionActive` use for the identical split.
 */
class EnsureMonthCloseBacklogClear
{
    public function __construct(private MonthCloseBacklog $backlog) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || $request->isMethodSafe() || $user->hasRole('superadmin')) {
            return $next($request);
        }

        if ($request->routeIs('*.revenue_expense.reverse_payment')) {
            return $next($request);
        }

        $backlog = $this->backlog->build();

        if (! $backlog || ! $backlog['blocking']) {
            return $next($request);
        }

        $oldest = $backlog['oldest'];

        if ($backlog['close_url']) {
            return redirect($backlog['close_url'])->with('error', __('messages.flash_month_close_required', [
                'month' => $oldest->name,
                'count' => $backlog['count'],
            ]));
        }

        return back()->with('error', __('messages.flash_month_close_required_supervisor', [
            'month' => $oldest->name,
            'count' => $backlog['count'],
        ]));
    }
}
