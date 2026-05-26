<?php

namespace App\Services\Tenants;

use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Utilities;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the unified list of outstanding tenant charges shown on the leave page:
 *
 *   - Pending/overdue Payments rows (manually-recorded utility/other charges)
 *   - Unpaid Utilities rows (auto-billed monthly charges)
 *
 * Each entry exposes the same shape (id, source, description, type, amount,
 * due_date) so the view can render them uniformly. The id is prefixed with
 * "payment_" or "utility_" so the post-submit handler can split them apart
 * (see TenantLeaveProcessor::parseChargeIds).
 */
class TenantPendingChargesQuery
{
    /**
     * Returns an empty collection when the rental has no id (legacy tenants
     * without an explicit rental row).
     */
    public function forRental(?Rentals $rental): Collection
    {
        if (!$rental || !$rental->id) {
            return collect();
        }

        $pendingPayments = Payments::where('rental_id', $rental->id)
            ->whereIn('payment_type', ['utilities', 'other'])
            ->whereIn('payment_status', ['pending', 'overdue'])
            ->orderBy('due_date')
            ->get()
            ->map(fn ($p) => (object) [
                'id'          => 'payment_' . $p->id,
                'source'      => 'payment',
                'description' => $p->note ?: ucfirst($p->payment_type) . ' charge',
                'type'        => $p->payment_type,
                'amount'      => $p->amount,
                'due_date'    => $p->due_date,
            ]);

        $unpaidUtils = Utilities::where('rental_id', $rental->id)
            ->where('paid_status', false)
            ->orderBy('billing_year')
            ->orderBy('billing_month')
            ->get()
            ->map(fn ($u) => (object) [
                'id'          => 'utility_' . $u->id,
                'source'      => 'utility',
                'description' => ucfirst($u->utility_type) . ' — ' . Carbon::create($u->billing_year, $u->billing_month)->format('M Y'),
                'type'        => 'utilities',
                'amount'      => $u->charge_amount,
                'due_date'    => Carbon::create($u->billing_year, $u->billing_month)->endOfMonth(),
            ]);

        return $pendingPayments->concat($unpaidUtils)
            ->sortBy('due_date')
            ->values();
    }
}
