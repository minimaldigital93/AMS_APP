<?php

namespace App\Services\Tenants;

use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\Rentals;
use App\Models\Tenants;
use Carbon\Carbon;

/**
 * Keeps the lease in step with edits made on the tenant and room forms.
 *
 * Every money figure here is derived from the `rentals` row: the prorated rent
 * for the move-in month, the due day, arrears, the late-fee day count and the
 * contract PDF all read `start_date` / `rent_amount` (see BillingCycleService,
 * RevenueExpenseQueryService, TenantRentProgressCalculator, ContractGenerator).
 * The tenant edit form writes `tenants` and the room edit form writes
 * `apartments` — so without this service a corrected move-in date or a repriced
 * room shows on the profile while billing quietly keeps charging the original
 * figures.
 *
 * Only the CURRENT lease follows an edit. A tenancy that has already ended is
 * booked history: its dates and rent are what the tenant was actually charged,
 * and rewriting them would restate months that are already collected.
 */
class LeaseSyncService
{
    /**
     * The lease a correction applies to: the tenant's newest tenancy in their
     * current room that hasn't ended. Mirrors the room-move lookup the tenant
     * controllers already use.
     */
    public function activeRental(Tenants $tenant): ?Rentals
    {
        if (! $tenant->apartment_id) {
            return null;
        }

        return Rentals::where('tenant_id', $tenant->id)
            ->where('apartment_id', $tenant->apartment_id)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->latest('id')
            ->first();
    }

    /**
     * Push an edited move-in date / deposit onto the tenant's current lease.
     *
     * Call this AFTER the tenant row has been saved, inside the same
     * transaction — it reads the tenant's committed-in-memory values. It is a
     * no-op when nothing lease-relevant changed, so it is safe to call on every
     * update (including a room move, where the freshly created rental already
     * carries the new values).
     */
    public function syncFromTenantEdit(Tenants $tenant): void
    {
        $rental = $this->activeRental($tenant);

        if (! $rental) {
            return;
        }

        $changes = [];

        $moveIn = $tenant->move_in_date ? Carbon::parse($tenant->move_in_date)->startOfDay() : null;
        $startChanged = $moveIn && (! $rental->start_date || ! $rental->start_date->isSameDay($moveIn));

        if ($startChanged) {
            $changes['start_date'] = $moveIn;
            // payment_due_day has no form field of its own — it has always been
            // seeded from the move-in day at check-in, so it moves with it.
            // Left stale it would print the wrong day on ប្រការ៤ of the contract.
            $changes['payment_due_day'] = $moveIn->day;
        }

        $deposit = (float) ($tenant->deposit ?? 0);
        $depositChanged = abs((float) $rental->deposit - $deposit) > 0.001;

        if ($depositChanged) {
            $changes['deposit'] = $deposit;
        }

        if ($changes === []) {
            return;
        }

        $rental->update($changes);

        $this->syncDepositIncome($rental, $deposit, $startChanged, $depositChanged);
    }

    /**
     * Carry a new room price onto the tenancies it applies to.
     *
     * Rent owed is derived, not invoiced, so the current lease's `rent_amount`
     * IS the price the occupant is billed. An owner who edits the room price
     * expects the sitting tenant's next bill to change; leaving the snapshot
     * behind is what made the rent-collection page show the new price in the
     * "monthly rent" column while charging the old one.
     *
     * Ended tenancies keep their own rent — that is the price that was actually
     * charged and collected.
     *
     * @return int number of leases repriced
     */
    public function repriceActiveLeases(Apartments $apartment, float $rent): int
    {
        $rentals = Rentals::where('apartment_id', $apartment->id)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->get();

        $repriced = 0;

        foreach ($rentals as $rental) {
            if (abs((float) $rental->rent_amount - $rent) < 0.001) {
                continue;
            }

            $rental->update(['rent_amount' => $rent]);
            $repriced++;
        }

        return $repriced;
    }

    /**
     * Correct the deposit income that check-in booked for this lease.
     *
     * Update-only on purpose: the ledger row is created by the check-in flows
     * (TenantAssignmentService / TenantController@store) when a deposit is
     * collected. An edit corrects the figure that was booked; it does not book
     * new income on its own, and it never reaches into a period that has been
     * closed off.
     */
    private function syncDepositIncome(Rentals $rental, float $deposit, bool $dateChanged, bool $amountChanged): void
    {
        $row = Accounts::with('fiscalPeriod')
            ->where('reference_number', 'deposit:rental:'.$rental->id)
            ->first();

        if (! $row || ($row->fiscalPeriod && $row->fiscalPeriod->status !== 'open')) {
            return;
        }

        $updates = [];

        if ($amountChanged) {
            $updates['amount'] = $deposit;
        }

        // Deposit income is recognised in the move-in month, so a corrected
        // move-in date moves the row into the right month's books.
        if ($dateChanged && $rental->start_date) {
            $updates['transaction_date'] = $rental->start_date->toDateString();
        }

        if ($updates !== []) {
            $row->update($updates);
        }
    }
}
