<?php

namespace App\Services\RevenueExpense;

use App\Http\Controllers\Concerns\ScopesToSupervisorProperties;
use App\Models\KhqrPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * How many tenant-started payments are still waiting for the landlord.
 *
 * This exists so the nav can HIDE the confirmation queue while there is
 * nothing in it. Auto-confirm (TenantPaymentVerifier, on the landlord's own
 * token) settles the common case without anyone pressing anything, and a
 * permanently visible queue that is permanently empty trains the operator to
 * ignore it — which is fatal, because the queue is the only surface where the
 * cases auto-confirm CANNOT reach are visible:
 *
 *  - the tenant closed the tab before the poll confirmed (there is no webhook,
 *    and bakong:reconcile ships OFF),
 *  - the row was minted `manual` — before auto-confirm was switched on, or
 *    with an expired token / the platform switch off — and the channel is
 *    decided once, at mint time,
 *  - Bakong refused rather than answered (quota, backoff, errorCode 17):
 *    VERIFY_REFUSED leaves the row open on purpose,
 *  - Bakong confirmed but finalize() threw.
 *
 * khqr:expire-abandoned skips settlement_target = 'merchant' entirely, so
 * nothing else will ever close one of these rows. Hiding the entry is
 * therefore only ever allowed to mean "there is nothing here right now".
 *
 * The scope is Shared\RevenueExpenseController::pendingPayments()' own — the
 * same trait, the same apartment set — so the badge and the page it opens can
 * never state different numbers. Memoized because every admin/supervisor page
 * render asks.
 */
class PendingTenantPaymentsCount
{
    use ScopesToSupervisorProperties;

    private ?int $count = null;

    /**
     * The request the memo belongs to.
     *
     * Two nav surfaces ask on every render (the sidebar and the bottom-nav
     * sheet both compose), so the answer is worth keeping — but only for as
     * long as it is still true. Keying the memo to the Request object rather
     * than to the instance is what makes that safe wherever the container
     * outlives a request: Octane, and the test harness.
     */
    private ?Request $memoFor = null;

    /** Rows awaiting this user's confirmation; 0 when signed out or on error. */
    public function count(): int
    {
        $request = request();

        if ($this->count !== null && $this->memoFor === $request) {
            return $this->count;
        }

        $this->memoFor = $request;

        if (! Auth::check()) {
            return $this->count = 0;
        }

        try {
            $apartmentIds = $this->supervisorVisibleApartments()->pluck('id');

            $this->count = KhqrPayment::awaitingLandlord()
                ->whereHas('rental', fn ($q) => $q->whereIn('apartment_id', $apartmentIds))
                ->count();
        } catch (\Throwable $e) {
            // A nav badge must never break a page render.
            $this->count = 0;
        }

        return $this->count;
    }

    public function any(): bool
    {
        return $this->count() > 0;
    }
}
