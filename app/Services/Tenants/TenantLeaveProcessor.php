<?php

namespace App\Services\Tenants;

use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\FiscalPeriods;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\TenantLeave;
use App\Models\Tenants;
use App\Models\User;
use App\Models\Utilities;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\BillingCycleService;
use App\Services\TenantLeaveCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
    public function __construct(
        private TenantLeaveCalculator $calculator,
        private TenantPendingChargesQuery $pendingCharges,
        private BillingCycleService $cycles,
        private AuditLogger $audit,
    ) {}

    /**
     * Month-by-month rent of the lease being ended, from its first month
     * through $through, each flagged paid/unpaid.
     *
     * A month counts as paid when ANY of the tenant's rentals holds a rent
     * payment dated in it — the same test Tenants::paymentHistory() applies,
     * widened across rentals because a room change opens a new rental that
     * restarts at the original move-in date: the months spent in the old room
     * were paid against the OLD rental and must not read as arrears here.
     *
     * @return Collection<int, array{key: string, label: string, month: int, year: int, amount: float, paid: bool}>
     */
    public function rentLedger(Tenants $tenant, Rentals $rental, Carbon $through): Collection
    {
        if (! $rental->start_date) {
            return collect();
        }

        $rentalIds = Rentals::where('tenant_id', $tenant->id)->pluck('id')->push($rental->id)->filter()->unique();

        $paidMonths = Payments::whereIn('rental_id', $rentalIds)
            ->where('payment_type', 'rent')
            ->whereNotNull('paid_at')
            ->pluck('paid_at')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m'))
            ->flip();

        $ledger = collect();
        $cursor = $rental->start_date->copy()->startOfMonth();
        $end = $through->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $ledger->push([
                'key' => $key,
                'label' => $cursor->format('M Y'),
                'month' => $cursor->month,
                'year' => $cursor->year,
                // Same figure the rent collection page bills (prorated
                // move-in month under a collection day).
                'amount' => round($this->cycles->periodFor($rental, $cursor->month, $cursor->year)?->amount
                    ?? (float) $rental->rent_amount, 2),
                'paid' => $paidMonths->has($key),
            ]);
            $cursor->addMonth();
        }

        return $ledger;
    }

    /**
     * EVERYTHING the tenant owes on a given leave date — the one set the leave
     * form lists and the one set a submission is checked against, so an item
     * cannot be dropped by leaving it off the page or out of the POST.
     *
     *   rent_months      — unpaid rent for every month BEFORE the leave month
     *                      (the leave month itself is the pro-rata line).
     *   final_month_paid — the leave month's rent is already in, so no pro-rata
     *                      is charged on top of it.
     *   charges          — every open bill on any of the tenant's rentals.
     *
     * @return array{rent_months: Collection, final_month_paid: bool, charges: Collection}
     */
    public function owed(Tenants $tenant, Rentals $rental, Carbon $leaveDate): array
    {
        $leaveKey = $leaveDate->format('Y-m');
        $ledger = $this->rentLedger($tenant, $rental, $leaveDate);

        return [
            'rent_months' => $ledger->filter(fn ($m) => ! $m['paid'] && $m['key'] < $leaveKey)->values(),
            'final_month_paid' => (bool) ($ledger->firstWhere('key', $leaveKey)['paid'] ?? false),
            'charges' => $this->pendingCharges->forTenant($tenant),
        ];
    }

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
        $owed = $this->owed($tenant, $rental, $leaveDate);

        // The leave month's rent was already collected: charging pro-rata (or
        // a full month) on top of it billed that month twice.
        $chargeFullMonth = (bool) ($validated['charge_full_month'] ?? false);
        $proRataRent = match (true) {
            $owed['final_month_paid'] => 0.0,
            $chargeFullMonth => (float) $rental->rent_amount,
            default => $this->calculator->calculateProRataRent($rental, $leaveDate),
        };

        // Only what is actually owed can be selected — an id or month from a
        // stale tab (or another tenant) is dropped rather than booked.
        $selectedMonthKeys = collect($validated['rent_months'] ?? [])->map(fn ($k) => (string) $k)->unique();
        $arrearsMonths = $owed['rent_months']->filter(fn ($m) => $selectedMonthKeys->contains($m['key']))->values();

        $selectedChargeIds = collect($validated['charge_ids'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->intersect($owed['charges']->pluck('id'))
            ->values();

        $writtenOff = $this->writtenOff($owed, $arrearsMonths, $selectedChargeIds);
        $writeOffReason = trim((string) ($validated['write_off_reason'] ?? ''));

        if ($writtenOff['amount'] > 0 && $writeOffReason === '') {
            throw ValidationException::withMessages([
                'write_off_reason' => __('messages.leave_write_off_reason_required', ['amount' => money($writtenOff['amount'])]),
            ]);
        }

        [$paymentIds, $utilityIds] = $this->parseChargeIds($selectedChargeIds->all());

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
                'arrears_rent' => (float) $arrearsMonths->sum('amount'),
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
            'arrears_months' => $arrearsMonths,
            'written_off' => $writtenOff + ['reason' => $writeOffReason !== '' && $writtenOff['amount'] > 0 ? $writeOffReason : null],
            'settlement' => $settlement,
        ];
    }

    /**
     * What is owed but was NOT selected — the money this move-out forgives.
     *
     * @return array{amount: float, items: list<array<string, mixed>>}
     */
    private function writtenOff(array $owed, Collection $arrearsMonths, Collection $selectedChargeIds): array
    {
        $selectedKeys = $arrearsMonths->pluck('key');

        $items = $owed['rent_months']
            ->reject(fn ($m) => $selectedKeys->contains($m['key']))
            ->map(fn ($m) => ['kind' => 'rent', 'month' => $m['key'], 'label' => $m['label'], 'amount' => $m['amount']])
            ->concat(
                $owed['charges']
                    ->reject(fn ($c) => $selectedChargeIds->contains($c->id))
                    ->map(fn ($c) => ['kind' => 'charge', 'id' => $c->id, 'label' => $c->description, 'amount' => round((float) $c->amount, 2)])
            )
            ->values();

        return [
            'amount' => round((float) $items->sum('amount'), 2),
            'items' => $items->all(),
        ];
    }

    /**
     * Book the collected arrears: one rent Payments row per month, anchored in
     * the month it pays for (so that month goes green everywhere rent status is
     * derived — the same rule checkout's rentAnchorDate() follows), with the
     * ledger row dated on the leave date, when the money was received.
     * Shared by both panels so an admin and a supervisor book it identically.
     */
    public function bookArrearsRent(Tenants $tenant, array $context, FiscalPeriods $period, int $ledgerUserId): void
    {
        $leaveDate = $context['leave_date'];
        $rental = $context['rental'];
        $apartmentNumber = $tenant->apartment->apartment_number ?? 'N/A';

        foreach ($context['arrears_months'] ?? [] as $month) {
            $monthStart = Carbon::create($month['year'], $month['month'], 1);

            $payment = Payments::create([
                'rental_id' => $rental->id,
                'amount' => $month['amount'],
                'due_date' => $monthStart,
                'paid_at' => $monthStart->copy()->endOfMonth()->startOfDay(),
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'payment_type' => 'rent',
                'transaction_reference' => null,
                'late_fee' => 0,
                'note' => 'Tenant leave settlement - unpaid rent '.$month['label'],
            ]);

            Accounts::create([
                'fiscal_period_id' => $period->id,
                'payment_id' => $payment->id,
                'user_id' => $ledgerUserId,
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_RENT_INCOME,
                'description' => '[Apt '.$apartmentNumber.'] Leave settlement - unpaid rent '.$month['label'],
                'amount' => $month['amount'],
                'transaction_date' => $leaveDate,
                'note' => 'Tenant: '.$tenant->name,
            ]);
        }
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
            'arrears_rent' => $settlement['arrears_rent'],
            'electricity_charge' => $settlement['electricity_charge'],
            'water_charge' => $settlement['water_charge'],
            'internet_charge' => $settlement['internet_charge'],
            'parking_charge' => $settlement['parking_charge'],
            'total_amount_due' => $settlement['total_amount_due'],
            'deposit_applied' => $settlement['deposit_applied'],
            'balance_due' => $settlement['balance_due'],
            'refund_amount' => $settlement['refund_amount'],
            'written_off_amount' => $context['written_off']['amount'] ?? 0,
            'written_off_items' => ($context['written_off']['amount'] ?? 0) > 0 ? $context['written_off']['items'] : null,
            'write_off_reason' => $context['written_off']['reason'] ?? null,
            'status' => 'completed',
            'notes' => $notes,
        ]);

        if (($context['written_off']['amount'] ?? 0) > 0) {
            $this->audit->record('tenant.leave.written_off', $leave, [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'amount' => $context['written_off']['amount'],
                'reason' => $context['written_off']['reason'],
                'items' => $context['written_off']['items'],
            ]);
        }

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
