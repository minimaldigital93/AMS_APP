<?php

namespace App\Services\Tenants;

use App\Models\Apartments;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\TenantLeave;
use App\Models\Tenants;
use App\Models\User;
use App\Models\Utilities;
use App\Services\TenantLeaveCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Orchestrates the shared steps of the tenant move-out flow:
 *
 *   prepare()  — resolve the rental, parse charge IDs ("payment_N"/"utility_N"),
 *                calculate pro-rata + final settlement via TenantLeaveCalculator
 *   persist()  — create the TenantLeave row and stamp rental.end_date
 *   finalize() — archive the tenant, clear the apartment, mark it available,
 *                soft-delete the tenant, and suspend the linked user account
 *
 * Role-specific accounting (per-payment vs aggregate, deposit refund vs not)
 * stays in the calling controller — see Admin\TenantController::processLeave
 * and Supervisor\TenantController::processLeave.
 */
class TenantLeaveProcessor
{
    public function __construct(private TenantLeaveCalculator $calculator) {}

    /**
     * Resolve the rental, parse charge IDs, and compute the settlement.
     *
     * @param  array  $validated  expects: leave_date, charge_full_month (optional), charge_ids[] (optional)
     * @return array{
     *   rental: Rentals,
     *   leave_date: Carbon,
     *   selected_payments: Collection,
     *   selected_utilities: Collection,
     *   settlement: array<string, mixed>
     * }
     */
    public function prepare(Tenants $tenant, array $validated): array
    {
        $tenant->load(['apartment', 'rentals']);

        $rental = $this->resolveOrCreateActiveRental($tenant);
        $leaveDate = Carbon::parse($validated['leave_date']);

        $chargeFullMonth = (bool) ($validated['charge_full_month'] ?? false);
        $proRataRent = $chargeFullMonth
            ? (float) $rental->rent_amount
            : $this->calculator->calculateProRataRent($rental, $leaveDate);

        [$paymentIds, $utilityIds] = $this->parseChargeIds($validated['charge_ids'] ?? []);

        $selectedPayments = $paymentIds === []
            ? collect()
            : Payments::whereIn('id', $paymentIds)
                ->whereIn('payment_type', ['utilities', 'other'])
                ->get();

        $selectedUtilities = $utilityIds === []
            ? collect()
            : Utilities::whereIn('id', $utilityIds)
                ->where('paid_status', false)
                ->get();

        // Per-type buckets so the stored tenant_leaves columns say what they
        // mean (the old code lumped ALL utilities into electricity_charge and
        // "other" into parking_charge). Charges without a per-type breakdown —
        // manually-recorded pending Payments rows and untyped utility rows —
        // land in the extra bucket; only the labelling changes, never the total.
        $utilByType = fn (string $type) => (float) $selectedUtilities
            ->where('utility_type', $type)->sum('charge_amount');
        $untypedUtilities = (float) $selectedUtilities
            ->whereNotIn('utility_type', ['electricity', 'water', 'internet', 'parking'])
            ->sum('charge_amount');
        $selectedPaymentsTotal = (float) $selectedPayments->sum('amount');

        $extraCharges = $this->normalizeExtraCharges($validated['extra_charges'] ?? []);
        $extraTotal = array_sum(array_column($extraCharges, 'amount'));

        $settlement = $this->calculator->calculateSettlement(
            rental: $rental,
            tenant: $tenant,
            leaveDate: $leaveDate,
            charges: [
                'pro_rata_rent' => $proRataRent,
                'electricity' => $utilByType('electricity'),
                'water' => $utilByType('water'),
                'internet' => $utilByType('internet'),
                'parking' => $utilByType('parking'),
                'extra' => $extraTotal + $untypedUtilities + $selectedPaymentsTotal,
            ],
            deposit: (float) ($tenant->deposit ?? 0),
        );

        return [
            'rental' => $rental,
            'leave_date' => $leaveDate,
            'selected_payments' => $selectedPayments,
            'selected_utilities' => $selectedUtilities,
            'extra_charges' => $extraCharges,
            'settlement' => $settlement,
        ];
    }

    /**
     * Coerce free-form extra-charge input into a clean
     * list of {description, amount} rows; drop empties.
     *
     * @return list<array{description: string, amount: float}>
     */
    private function normalizeExtraCharges(array $rows): array
    {
        $cleaned = [];
        foreach ($rows as $row) {
            $description = trim((string) ($row['description'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            if ($description === '' || $amount <= 0) {
                continue;
            }
            $cleaned[] = ['description' => $description, 'amount' => round($amount, 2)];
        }

        return $cleaned;
    }

    /**
     * Persist the TenantLeave row, stamp rental.end_date.
     *
     * @param  array  $context  the output of prepare()
     */
    public function persist(Tenants $tenant, array $context, ?string $notes = null): TenantLeave
    {
        $settlement = $context['settlement'];
        $leaveDate = $context['leave_date'];
        $rental = $context['rental'];

        $leave = TenantLeave::create([
            'tenant_id' => $tenant->id,
            'rental_id' => $rental->id,
            'apartment_id' => $tenant->apartment_id,
            'leave_date' => $leaveDate,
            'original_move_out_date' => $rental->end_date,
            'stay_days' => $settlement['stay_days'],
            'pro_rata_rent' => $settlement['pro_rata_rent'],
            'electricity_charge' => $settlement['electricity_charge'],
            'water_charge' => $settlement['water_charge'],
            'internet_charge' => $settlement['internet_charge'],
            'parking_charge' => $settlement['parking_charge'],
            'total_amount_due' => $settlement['total_amount_due'],
            'deposit_applied' => $settlement['deposit_applied'],
            'balance_due' => $settlement['balance_due'],
            'refund_amount' => $settlement['refund_amount'],
            'status' => 'completed',
            'notes' => $notes,
        ]);

        $rental->update(['end_date' => $leaveDate]);

        return $leave;
    }

    /**
     * Finalize the move-out: archive tenant, free the apartment, suspend the
     * linked user account. Must be called after persist() (and after any
     * role-specific accounting writes the controllers perform).
     */
    public function finalize(Tenants $tenant): void
    {
        $apartment = $tenant->apartment;

        $this->calculator->archiveTenant($tenant, now());
        $this->calculator->clearTenantFromApartment($tenant);

        if ($apartment instanceof Apartments) {
            $this->calculator->markApartmentAvailable($apartment);
        }

        $tenant->delete();

        if ($tenant->user_id) {
            User::where('id', $tenant->user_id)->update(['status' => 'suspended']);
        }
    }

    /**
     * Find the currently-active rental for this tenant's apartment, creating
     * one from tenant/apartment defaults if none exists (preserves the
     * historical behavior — legacy tenants without explicit rental rows).
     */
    private function resolveOrCreateActiveRental(Tenants $tenant): Rentals
    {
        $rental = $tenant->rentals()
            ->where('apartment_id', $tenant->apartment_id)
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhere('end_date', '>', now());
            })
            ->latest()
            ->first();

        if ($rental) {
            return $rental;
        }

        return Rentals::create([
            'apartment_id' => $tenant->apartment_id,
            'tenant_id' => $tenant->id,
            'rent_amount' => $tenant->apartment?->monthly_rent ?? 0,
            'start_date' => $tenant->move_in_date,
            'end_date' => null,
        ]);
    }

    /**
     * Split prefixed charge IDs into payment_id and utility_id lists.
     * Form sends "payment_N" / "utility_N" strings.
     *
     * @param  array<string>  $ids
     * @return array{0: list<int>, 1: list<int>}
     */
    private function parseChargeIds(array $ids): array
    {
        $paymentIds = [];
        $utilityIds = [];

        foreach ($ids as $id) {
            if (str_starts_with($id, 'payment_')) {
                $paymentIds[] = (int) substr($id, 8);
            } elseif (str_starts_with($id, 'utility_')) {
                $utilityIds[] = (int) substr($id, 8);
            }
        }

        return [$paymentIds, $utilityIds];
    }
}
