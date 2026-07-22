<?php

namespace App\Services\RevenueExpense;

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\Utilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Write-side service for income recording — single payments, bulk rent runs,
 * tenant charge management, and the tenant checkout flow.
 *
 * Preserves the two/three-row write pattern documented in CLAUDE.md sec. 4:
 *   1. Payments row (cashflow record)
 *   2. Accounts row (ledger entry, category mapped from payment_type)
 *   3. Late-fee Accounts row (only if late_fee > 0) — separate so reports can
 *      break late-fee income out from base rent income.
 *
 * Tenant charges (addTenantCharge/removeTenantCharge/clearTenantCharges) live
 * in Utilities until paid; the Accounts ledger entry is created on payment
 * via checkout(), not on charge — avoids double-counting.
 */
class IncomeRecordingService
{
    /** Types billed off a continuous meter (usage = out − in). */
    public const METERED_TYPES = ['electricity', 'water'];

    public function __construct(
        private int $userId,
        private FiscalPeriods $period,
        private ?int $propertyId = null,
    ) {}

    /**
     * The property a ledger row belongs to: derived from the rental's room
     * (income always belongs to the room's property), falling back to the
     * active property the controller is scoped to.
     */
    private function propertyIdFor(Rentals $rental): ?int
    {
        return $rental->apartment?->floor?->property_id ?? $this->propertyId;
    }

    /**
     * Record a single income payment (rent, utilities, deposit, or other).
     *
     * @param  array  $data  validated payment input
     */
    public function recordPayment(Rentals $rental, array $data): Payments
    {
        return DB::transaction(function () use ($rental, $data) {
            $payment = Payments::create([
                'rental_id' => $rental->id,
                'amount' => $data['amount'],
                'due_date' => $data['transaction_date'],
                'paid_at' => $data['transaction_date'],
                'payment_method' => $data['payment_method'],
                'payment_status' => 'paid',
                'payment_type' => $data['payment_type'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'late_fee' => $data['late_fee'] ?? 0,
                'note' => $data['note'] ?? null,
            ]);

            $category = Accounts::PAYMENT_TYPE_TO_CATEGORY[$data['payment_type']] ?? Accounts::CAT_OTHER_INCOME;

            Accounts::create([
                'fiscal_period_id' => $this->period->id,
                'property_id' => $this->propertyIdFor($rental),
                'payment_id' => $payment->id,
                'user_id' => $this->userId,
                'account_type' => Accounts::TYPE_INCOME,
                'category' => $category,
                'description' => '[Apt '.($rental->apartment?->apartment_number ?? 'N/A').'] '.ucfirst($data['payment_type']).' payment',
                'amount' => $data['amount'],
                'transaction_date' => $data['transaction_date'],
                'reference_number' => $data['transaction_reference'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $lateFee = $data['late_fee'] ?? 0;
            if ($lateFee > 0) {
                $this->recordLateFee(
                    rental: $rental,
                    paymentId: $payment->id,
                    amount: $lateFee,
                    date: $data['transaction_date'],
                    reference: $data['transaction_reference'] ?? null,
                    note: 'Late fee for '.ucfirst($data['payment_type']),
                );
            }

            return $payment;
        });
    }

    /**
     * Record bulk monthly rent for many rentals at once. Returns counts +
     * totals for the success message.
     *
     * Skips rows where 'selected' is falsy. Each row may carry its own
     * amount + late_fee; payment_date and payment_method are shared.
     *
     * @param  array  $apartments  validated rows: rental_id, amount, late_fee, selected
     * @return array{count: int, total: float}
     */
    public function recordBulkRent(string $paymentDate, string $paymentMethod, array $apartments): array
    {
        return DB::transaction(function () use ($paymentDate, $paymentMethod, $apartments) {
            $recordedCount = 0;
            $totalAmount = 0.0;

            $paidMonth = \Carbon\Carbon::parse($paymentDate);

            foreach ($apartments as $aptData) {
                if (empty($aptData['selected'])) {
                    continue;
                }

                $rental = Rentals::with('apartment')->findOrFail($aptData['rental_id']);

                // Idempotency guard (2026-07 audit): a double-submit of the bulk
                // form used to book every rent twice. Skip rentals that already
                // hold a bulk-recorded rent payment in the same month; manual
                // payments (different note) stay unaffected so legitimate
                // partial payments are never blocked.
                $alreadyRecorded = Payments::where('rental_id', $rental->id)
                    ->where('payment_type', 'rent')
                    ->where('note', 'Auto-generated monthly rent')
                    ->whereYear('paid_at', $paidMonth->year)
                    ->whereMonth('paid_at', $paidMonth->month)
                    ->exists();
                if ($alreadyRecorded) {
                    continue;
                }

                $amount = (float) $aptData['amount'];
                $lateFee = (float) ($aptData['late_fee'] ?? 0);

                $payment = Payments::create([
                    'rental_id' => $rental->id,
                    'amount' => $amount,
                    'due_date' => $paymentDate,
                    'paid_at' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'payment_type' => 'rent',
                    'transaction_reference' => null,
                    'late_fee' => $lateFee,
                    'note' => 'Auto-generated monthly rent',
                ]);

                Accounts::create([
                    'fiscal_period_id' => $this->period->id,
                    'property_id' => $this->propertyIdFor($rental),
                    'payment_id' => $payment->id,
                    'user_id' => $this->userId,
                    'account_type' => Accounts::TYPE_INCOME,
                    'category' => Accounts::CAT_RENT_INCOME,
                    'description' => '[Apt '.($rental->apartment?->apartment_number ?? 'N/A').'] Monthly rent',
                    'amount' => $amount,
                    'transaction_date' => $paymentDate,
                    'reference_number' => null,
                    'note' => 'Auto-generated monthly rent',
                ]);

                if ($lateFee > 0) {
                    $this->recordLateFee(
                        rental: $rental,
                        paymentId: $payment->id,
                        amount: $lateFee,
                        date: $paymentDate,
                        reference: null,
                        note: 'Auto-generated late fee',
                    );
                }

                $recordedCount++;
                $totalAmount += $amount + $lateFee;
            }

            return [
                'count' => $recordedCount,
                'total' => $totalAmount,
            ];
        });
    }

    /**
     * Add an ad-hoc charge to a tenant's bill. Stored as an unpaid Utilities row;
     * Accounts entry is created later, on payment (see checkout()).
     *
     * Electricity/water follow a metered lifecycle (opening reading → closing
     * reading → charge) and upsert a single continuous row per billing month;
     * every other type simply appends a flat-fee row.
     */
    public function addTenantCharge(Rentals $rental, array $data): Utilities
    {
        $type = $data['charge_type'];
        $billingMonth = (int) ($data['billing_month'] ?? now()->month);
        $billingYear = (int) ($data['billing_year'] ?? now()->year);

        $meterIn = $this->readingValue($data['meter_reading_in'] ?? null);
        $meterOut = $this->readingValue($data['meter_reading_out'] ?? null);

        if (in_array($type, self::METERED_TYPES, true)) {
            return $this->recordMeteredCharge($rental, $type, $billingMonth, $billingYear, $meterIn, $meterOut, $data);
        }

        return Utilities::create([
            'tenant_id' => $rental->tenant_id,
            'rental_id' => $rental->id,
            'utility_type' => $type,
            'meter_number' => null,
            'meter_reading_in' => $meterIn,
            'meter_reading_out' => $meterOut,
            'charge_amount' => $data['charge_amount'],
            'billing_month' => $billingMonth,
            'billing_year' => $billingYear,
            'paid_status' => false,
            'paid_at' => null,
        ]);
    }

    /**
     * Upsert the continuous metered row for a (rental, type, month). The amount is
     * recomputed authoritatively here — in auto-calc mode the client value is
     * ignored and the charge is usage × the account's unit price. A row that has
     * already been paid is never mutated; a fresh row is created alongside it.
     */
    private function recordMeteredCharge(Rentals $rental, string $type, int $month, int $year, ?float $in, ?float $out, array $data): Utilities
    {
        $attrs = [
            'tenant_id' => $rental->tenant_id,
            'rental_id' => $rental->id,
            'utility_type' => $type,
            'meter_number' => null,
            'meter_reading_in' => $in,
            'meter_reading_out' => $out,
            'charge_amount' => $this->resolveMeteredCharge($type, $in, $out, $data),
            'billing_month' => $month,
            'billing_year' => $year,
            'paid_status' => false,
            'paid_at' => null,
        ];

        $existing = Utilities::where('rental_id', $rental->id)
            ->where('utility_type', $type)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->where('paid_status', false)
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->update($attrs);

            return $existing;
        }

        return Utilities::create($attrs);
    }

    /**
     * The charge for a metered row:
     *  - opening reading only (no meter_out) → 0 (no charge yet);
     *  - auto-calc on + both readings → usage × unit price (client amount ignored);
     *  - otherwise → the operator-typed amount.
     */
    private function resolveMeteredCharge(string $type, ?float $in, ?float $out, array $data): float
    {
        if ($out === null) {
            return 0.0;
        }

        if ($this->meterAutoCalcEnabled() && $in !== null) {
            $usage = max($out - $in, 0.0);

            return round($usage * $this->meterUnitRate($type), 2);
        }

        return (float) ($data['charge_amount'] ?? 0);
    }

    /** Is metered auto-calculation switched on for this account? */
    private function meterAutoCalcEnabled(): bool
    {
        return filter_var(settings('utility_meter_auto_calc'), FILTER_VALIDATE_BOOLEAN);
    }

    /** Per-unit price (stored in USD) for a metered utility type. */
    private function meterUnitRate(string $type): float
    {
        $key = $type === 'water' ? 'utility_water_price' : 'utility_electricity_price';

        return (float) settings($key, 0);
    }

    /** Normalise a raw meter-reading input to a float, treating blank as null. */
    private function readingValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Remove an unpaid tenant charge plus any orphan Accounts entry tied to it.
     * Returns false if the charge has already been paid.
     */
    public function removeTenantCharge(Utilities $charge): bool
    {
        if ($charge->paid_status) {
            return false;
        }

        // Best-effort: drop any Accounts row that referenced this charge before
        // payment (older code path). Failure is non-fatal but must leave a
        // trace — this is ledger data.
        try {
            Accounts::where('reference_number', 'tenant_charge:'.$charge->id)
                ->whereNull('payment_id')
                ->where('user_id', $this->userId)
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Ledger cleanup for deleted tenant charge failed', [
                'charge_id' => $charge->id,
                'error' => $e->getMessage(),
            ]);
        }

        $charge->delete();

        return true;
    }

    /**
     * Drop every unpaid charge on a rental. Returns the count removed.
     */
    public function clearTenantCharges(Rentals $rental): int
    {
        return DB::transaction(function () use ($rental) {
            $charges = Utilities::where('rental_id', $rental->id)
                ->where('paid_status', false)
                ->get();

            foreach ($charges as $charge) {
                try {
                    Accounts::where('reference_number', 'tenant_charge:'.$charge->id)
                        ->whereNull('payment_id')
                        ->where('user_id', $this->userId)
                        ->delete();
                } catch (\Throwable $e) {
                    Log::warning('Ledger cleanup for cleared tenant charge failed', [
                        'charge_id' => $charge->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $charge->delete();
            }

            return $charges->count();
        });
    }

    /**
     * Tenant checkout — pay rent and/or utilities together.
     *
     * Utility payments produce up to TWO ledger entries: electricity+water
     * land in CAT_UTILITY_INCOME; internet/parking/trash/other land in
     * CAT_OTHER_INCOME. This split is what powers the dashboard's per-type
     * income breakdown (see RevenueExpenseQueryService::calculateIncome).
     *
     * @return array{total_paid: float, items: list<string>}
     */
    public function checkout(Rentals $rental, array $data): array
    {
        return DB::transaction(function () use ($rental, $data) {
            $paymentDate = $data['payment_date'];
            $paymentMethod = $data['payment_method'];
            $lateFee = $data['late_fee'] ?? 0;
            $reference = $data['transaction_reference'] ?? null;
            $note = $data['note'] ?? null;

            $totalPaid = 0.0;
            $items = [];

            if (! empty($data['pay_rent'])) {
                $rentAmount = (float) $data['rent_amount'];

                $payment = Payments::create([
                    'rental_id' => $rental->id,
                    'amount' => $rentAmount,
                    'due_date' => $paymentDate,
                    'paid_at' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'payment_type' => 'rent',
                    'transaction_reference' => $reference,
                    'late_fee' => $lateFee,
                    'note' => $note ?? 'Monthly rent payment',
                ]);

                Accounts::create([
                    'fiscal_period_id' => $this->period->id,
                    'property_id' => $this->propertyIdFor($rental),
                    'payment_id' => $payment->id,
                    'user_id' => $this->userId,
                    'account_type' => Accounts::TYPE_INCOME,
                    'category' => Accounts::CAT_RENT_INCOME,
                    'description' => '[Apt '.($rental->apartment?->apartment_number ?? 'N/A').'] Monthly rent',
                    'amount' => $rentAmount,
                    'transaction_date' => $paymentDate,
                    'reference_number' => $reference,
                    'note' => $note,
                ]);

                if ($lateFee > 0) {
                    $this->recordLateFee(
                        rental: $rental,
                        paymentId: $payment->id,
                        amount: $lateFee,
                        date: $paymentDate,
                        reference: $reference,
                        note: 'Late fee',
                    );
                }

                $totalPaid += $rentAmount + $lateFee;
                $items[] = 'Rent: $'.number_format($rentAmount, 2);
            }

            if (! empty($data['pay_utilities'])) {
                $paymentMonth = \Carbon\Carbon::parse($paymentDate);
                $utilityResult = $this->settleUtilitiesForMonth(
                    $rental,
                    (int) ($data['billing_month'] ?? $paymentMonth->month),
                    (int) ($data['billing_year'] ?? $paymentMonth->year),
                    $paymentDate,
                    $paymentMethod,
                    $reference,
                );
                if ($utilityResult !== null) {
                    $totalPaid += $utilityResult['amount'];
                    $items[] = 'Utilities: $'.number_format($utilityResult['amount'], 2);
                }
            }

            return [
                'total_paid' => $totalPaid,
                'items' => $items,
            ];
        });
    }

    /**
     * Settle ALL of a tenant's carried-forward debt in one transaction:
     * every unpaid rent month (from Tenants::outstandingCharges()) plus every
     * unpaid utility charge, regardless of which month they belong to.
     *
     * Arrears dating (see CLAUDE.md fiscal-period rules): the income is always
     * recognised on $paymentDate in the CURRENT open period ($this->period) —
     * closed months are never written into. For rent, the Payments row's
     * paid_at is anchored in the OWED month so the tenant's derived debt for
     * that month clears, while its ledger Accounts row is dated $paymentDate.
     * "We received that month's rent today."
     *
     * Item identifiers (for $selection): rent months are "rent_{rentalId}_{year}_{month}",
     * utility charges are "utility_{utilityId}". Pass null to settle everything.
     *
     * @param  array<int, string>|null  $selection  item ids to settle, or null for all
     * @return array{rent_count: int, utilities_count: int, total: float}
     */
    public function settleOutstandingForTenant(Tenants $tenant, string $paymentDate, string $paymentMethod, ?string $reference = null, ?array $selection = null): array
    {
        return DB::transaction(function () use ($tenant, $paymentDate, $paymentMethod, $reference, $selection) {
            $selected = $selection === null ? null : array_flip($selection);
            $wants = fn (string $id) => $selected === null || isset($selected[$id]);

            $outstanding = $tenant->outstandingCharges();
            $rentalsById = $tenant->rentals->keyBy('id');

            $rentCount = 0;
            $utilCount = 0;
            $total = 0.0;

            // --- Unpaid rent, one paid Payments row per owed month. ---
            foreach ($outstanding['unpaid_months'] as $month) {
                $rental = $rentalsById->get($month['rental_id']);
                $amount = (float) $month['rent_amount'];
                if (! $rental || $amount <= 0) {
                    continue;
                }
                if (! $wants('rent_'.$month['rental_id'].'_'.$month['year'].'_'.$month['month'])) {
                    continue;
                }
                $rental->loadMissing('apartment.floor');

                $payment = Payments::create([
                    'rental_id' => $rental->id,
                    'amount' => $amount,
                    'due_date' => $month['pay_date'],
                    'paid_at' => $month['pay_date'], // anchor in the owed month
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'payment_type' => 'rent',
                    'transaction_reference' => $reference,
                    'late_fee' => 0,
                    'note' => 'Outstanding rent settled ('.$month['label'].')',
                ]);

                Accounts::create([
                    'fiscal_period_id' => $this->period->id,
                    'property_id' => $this->propertyIdFor($rental),
                    'payment_id' => $payment->id,
                    'user_id' => $this->userId,
                    'account_type' => Accounts::TYPE_INCOME,
                    'category' => Accounts::CAT_RENT_INCOME,
                    'description' => '[Apt '.($rental->apartment?->apartment_number ?? 'N/A').'] Outstanding rent — '.$month['label'],
                    'amount' => $amount,
                    'transaction_date' => $paymentDate, // recognised today, open period
                    'reference_number' => $reference,
                    'note' => 'Back-rent for '.$month['label'].' collected on '.$paymentDate,
                ]);

                $rentCount++;
                $total += $amount;
            }

            // --- Unpaid utilities, settled per (rental, billing month), keeping
            // only the selected charges. One Payments row per month-batch. ---
            foreach ($tenant->rentals as $rental) {
                $rental->loadMissing('apartment.floor');

                $unpaid = Utilities::where('rental_id', $rental->id)
                    ->where('paid_status', false)
                    ->get()
                    ->filter(fn ($u) => $wants('utility_'.$u->id));

                foreach ($unpaid->groupBy(fn ($u) => $u->billing_year.'-'.$u->billing_month) as $rows) {
                    $result = $this->settleUtilityRows($rental, $rows->values(), $paymentDate, $paymentMethod, $reference);
                    if ($result !== null) {
                        $utilCount += $rows->count();
                        $total += $result['amount'];
                    }
                }
            }

            return [
                'rent_count' => $rentCount,
                'utilities_count' => $utilCount,
                'total' => round($total, 2),
            ];
        });
    }

    /**
     * Settle all unpaid utility charges for the given billing month — the month
     * the bill page was showing when the operator checked out (previously this
     * used now()'s month, which mismarked charges for backdated checkouts and
     * for KHQR webhooks landing just after a month boundary).
     * Splits the resulting income into two Accounts rows (utility vs other)
     * so dashboard breakdowns work.
     *
     * @return array{amount: float}|null null when there were no unpaid charges
     */
    private function settleUtilitiesForMonth(Rentals $rental, int $billingMonth, int $billingYear, string $paymentDate, string $paymentMethod, ?string $reference): ?array
    {
        $unpaid = Utilities::where('rental_id', $rental->id)
            ->forMonth($billingMonth, $billingYear)
            ->unpaid()
            ->get();

        return $this->settleUtilityRows($rental, $unpaid, $paymentDate, $paymentMethod, $reference);
    }

    /**
     * Settle a specific set of unpaid Utilities rows for one rental (one paid
     * Payments row + income split by type). Used by the month-scoped checkout
     * path and by selective outstanding collection.
     *
     * @param  \Illuminate\Support\Collection<int, Utilities>  $unpaid
     * @return array{amount: float}|null null when the set is empty
     */
    private function settleUtilityRows(Rentals $rental, \Illuminate\Support\Collection $unpaid, string $paymentDate, string $paymentMethod, ?string $reference): ?array
    {
        if ($unpaid->isEmpty()) {
            return null;
        }

        $utilityTotal = (float) $unpaid->sum('charge_amount');

        $payment = Payments::create([
            'rental_id' => $rental->id,
            'amount' => $utilityTotal,
            'due_date' => $paymentDate,
            'paid_at' => $paymentDate,
            'payment_method' => $paymentMethod,
            'payment_status' => 'paid',
            'payment_type' => 'utilities',
            'transaction_reference' => $reference,
            'late_fee' => 0,
            'note' => 'Utility charges: '.$unpaid->pluck('utility_type')->implode(', '),
        ]);

        // Split: electricity+water → utility_income; internet/parking/trash/other → other_income
        $utilityIncomeTypes = ['electricity', 'water'];
        $otherIncomeTypes = ['internet', 'parking', 'trash', 'other'];

        $utilIncomeAmt = (float) $unpaid->whereIn('utility_type', $utilityIncomeTypes)->sum('charge_amount');
        $otherIncomeAmt = (float) $unpaid->whereIn('utility_type', $otherIncomeTypes)->sum('charge_amount');

        if ($utilIncomeAmt > 0) {
            $utilTypes = $unpaid->whereIn('utility_type', $utilityIncomeTypes)->pluck('utility_type')->unique()->implode(', ');
            $this->createUtilityIncomeAccount($rental, $payment->id, Accounts::CAT_UTILITY_INCOME, $utilIncomeAmt, $paymentDate, $reference, $utilTypes, 'Utilities (electricity/water)');
        }

        if ($otherIncomeAmt > 0) {
            $otherTypes = $unpaid->whereIn('utility_type', $otherIncomeTypes)->pluck('utility_type')->unique()->implode(', ');
            $this->createUtilityIncomeAccount($rental, $payment->id, Accounts::CAT_OTHER_INCOME, $otherIncomeAmt, $paymentDate, $reference, $otherTypes, 'Other charges (internet/parking/trash)');
        }

        // Fallback for charges whose utility_type isn't in either list above —
        // preserves the original controller behavior; shouldn't normally fire.
        if ($utilIncomeAmt <= 0 && $otherIncomeAmt <= 0 && $utilityTotal > 0) {
            $this->createUtilityIncomeAccount($rental, $payment->id, Accounts::CAT_UTILITY_INCOME, $utilityTotal, $paymentDate, $reference, $unpaid->pluck('utility_type')->implode(', '), 'Utilities');
        }

        foreach ($unpaid as $utility) {
            $utility->update([
                'paid_status' => true,
                // Same clock as the Payments row — date-windowed reports must
                // see the payment and the settled charge in the same period.
                'paid_at' => $paymentDate,
            ]);
        }

        return ['amount' => $utilityTotal];
    }

    /**
     * Create a late-fee Accounts row (always separate from the main payment
     * row, so reports can split late fees out from base rent income).
     */
    private function recordLateFee(Rentals $rental, int $paymentId, float $amount, string $date, ?string $reference, string $note): void
    {
        Accounts::create([
            'fiscal_period_id' => $this->period->id,
            'property_id' => $this->propertyIdFor($rental),
            'payment_id' => $paymentId,
            'user_id' => $this->userId,
            'account_type' => Accounts::TYPE_INCOME,
            'category' => Accounts::CAT_LATE_FEE_INCOME,
            'description' => '[Apt '.($rental->apartment?->apartment_number ?? 'N/A').'] Late fee',
            'amount' => $amount,
            'transaction_date' => $date,
            'reference_number' => $reference,
            'note' => $note,
        ]);
    }

    private function createUtilityIncomeAccount(Rentals $rental, int $paymentId, string $category, float $amount, string $date, ?string $reference, string $typesList, string $notePrefix): void
    {
        Accounts::create([
            'fiscal_period_id' => $this->period->id,
            'property_id' => $this->propertyIdFor($rental),
            'payment_id' => $paymentId,
            'user_id' => $this->userId,
            'account_type' => Accounts::TYPE_INCOME,
            'category' => $category,
            'description' => '[Apt '.($rental->apartment?->apartment_number ?? 'N/A').'] '.ucwords($typesList),
            'amount' => $amount,
            'transaction_date' => $date,
            'reference_number' => $reference,
            'note' => $notePrefix.': '.$typesList,
        ]);
    }
}
