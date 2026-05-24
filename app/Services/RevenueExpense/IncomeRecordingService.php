<?php

namespace App\Services\RevenueExpense;

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Utilities;

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
    public function __construct(
        private int $userId,
        private FiscalPeriods $period,
    ) {}

    /**
     * Record a single income payment (rent, utilities, deposit, or other).
     *
     * @param array $data validated payment input
     */
    public function recordPayment(Rentals $rental, array $data): Payments
    {
        $payment = Payments::create([
            'rental_id'             => $rental->id,
            'amount'                => $data['amount'],
            'due_date'              => $data['transaction_date'],
            'paid_at'               => $data['transaction_date'],
            'payment_method'        => $data['payment_method'],
            'payment_status'        => 'paid',
            'payment_type'          => $data['payment_type'],
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'late_fee'              => $data['late_fee'] ?? 0,
            'note'                  => $data['note'] ?? null,
        ]);

        $category = Accounts::PAYMENT_TYPE_TO_CATEGORY[$data['payment_type']] ?? Accounts::CAT_OTHER_INCOME;

        Accounts::create([
            'fiscal_period_id' => $this->period->id,
            'payment_id'       => $payment->id,
            'user_id'          => $this->userId,
            'account_type'     => Accounts::TYPE_INCOME,
            'category'         => $category,
            'description'      => '[Apt ' . $rental->apartment->apartment_number . '] ' . ucfirst($data['payment_type']) . ' payment',
            'amount'           => $data['amount'],
            'transaction_date' => $data['transaction_date'],
            'reference_number' => $data['transaction_reference'] ?? null,
            'note'             => $data['note'] ?? null,
        ]);

        $lateFee = $data['late_fee'] ?? 0;
        if ($lateFee > 0) {
            $this->recordLateFee(
                rental: $rental,
                paymentId: $payment->id,
                amount: $lateFee,
                date: $data['transaction_date'],
                reference: $data['transaction_reference'] ?? null,
                note: 'Late fee for ' . ucfirst($data['payment_type']),
            );
        }

        return $payment;
    }

    /**
     * Record bulk monthly rent for many rentals at once. Returns counts +
     * totals for the success message.
     *
     * Skips rows where 'selected' is falsy. Each row may carry its own
     * amount + late_fee; payment_date and payment_method are shared.
     *
     * @param array $apartments validated rows: rental_id, amount, late_fee, selected
     * @return array{count: int, total: float}
     */
    public function recordBulkRent(string $paymentDate, string $paymentMethod, array $apartments): array
    {
        $recordedCount = 0;
        $totalAmount   = 0.0;

        foreach ($apartments as $aptData) {
            if (empty($aptData['selected'])) {
                continue;
            }

            $rental  = Rentals::with('apartment')->findOrFail($aptData['rental_id']);
            $amount  = (float) $aptData['amount'];
            $lateFee = (float) ($aptData['late_fee'] ?? 0);

            $payment = Payments::create([
                'rental_id'             => $rental->id,
                'amount'                => $amount,
                'due_date'              => $paymentDate,
                'paid_at'               => $paymentDate,
                'payment_method'        => $paymentMethod,
                'payment_status'        => 'paid',
                'payment_type'          => 'rent',
                'transaction_reference' => null,
                'late_fee'              => $lateFee,
                'note'                  => 'Auto-generated monthly rent',
            ]);

            Accounts::create([
                'fiscal_period_id' => $this->period->id,
                'payment_id'       => $payment->id,
                'user_id'          => $this->userId,
                'account_type'     => Accounts::TYPE_INCOME,
                'category'         => Accounts::CAT_RENT_INCOME,
                'description'      => '[Apt ' . $rental->apartment->apartment_number . '] Monthly rent',
                'amount'           => $amount,
                'transaction_date' => $paymentDate,
                'reference_number' => null,
                'note'             => 'Auto-generated monthly rent',
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
    }

    /**
     * Add an ad-hoc charge to a tenant's bill. Stored as an unpaid Utilities row;
     * Accounts entry is created later, on payment (see checkout()).
     */
    public function addTenantCharge(Rentals $rental, array $data): Utilities
    {
        $billingMonth = $data['billing_month'] ?? now()->month;
        $billingYear  = $data['billing_year']  ?? now()->year;

        return Utilities::create([
            'tenant_id'         => $rental->tenant_id,
            'rental_id'         => $rental->id,
            'utility_type'      => $data['charge_type'],
            'meter_number'      => null,
            'meter_reading_in'  => $data['meter_reading_in'] ?? 0,
            'meter_reading_out' => $data['meter_reading_out'] ?? 0,
            'charge_amount'     => $data['charge_amount'],
            'billing_month'     => $billingMonth,
            'billing_year'      => $billingYear,
            'paid_status'       => false,
            'paid_at'           => null,
        ]);
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
        // payment (older code path). Failure is non-fatal.
        try {
            Accounts::where('reference_number', 'tenant_charge:' . $charge->id)
                ->whereNull('payment_id')
                ->where('user_id', $this->userId)
                ->delete();
        } catch (\Throwable $e) {
            // not fatal
        }

        $charge->delete();

        return true;
    }

    /**
     * Drop every unpaid charge on a rental. Returns the count removed.
     */
    public function clearTenantCharges(Rentals $rental): int
    {
        $charges = Utilities::where('rental_id', $rental->id)
            ->where('paid_status', false)
            ->get();

        foreach ($charges as $charge) {
            try {
                Accounts::where('reference_number', 'tenant_charge:' . $charge->id)
                    ->whereNull('payment_id')
                    ->where('user_id', $this->userId)
                    ->delete();
            } catch (\Throwable $e) {
                // not fatal
            }
            $charge->delete();
        }

        return $charges->count();
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
        $paymentDate   = $data['payment_date'];
        $paymentMethod = $data['payment_method'];
        $lateFee       = $data['late_fee'] ?? 0;
        $reference     = $data['transaction_reference'] ?? null;
        $note          = $data['note'] ?? null;

        $totalPaid = 0.0;
        $items     = [];

        if (!empty($data['pay_rent'])) {
            $rentAmount = (float) $data['rent_amount'];

            $payment = Payments::create([
                'rental_id'             => $rental->id,
                'amount'                => $rentAmount,
                'due_date'              => $paymentDate,
                'paid_at'               => $paymentDate,
                'payment_method'        => $paymentMethod,
                'payment_status'        => 'paid',
                'payment_type'          => 'rent',
                'transaction_reference' => $reference,
                'late_fee'              => $lateFee,
                'note'                  => $note ?? 'Monthly rent payment',
            ]);

            Accounts::create([
                'fiscal_period_id' => $this->period->id,
                'payment_id'       => $payment->id,
                'user_id'          => $this->userId,
                'account_type'     => Accounts::TYPE_INCOME,
                'category'         => Accounts::CAT_RENT_INCOME,
                'description'      => '[Apt ' . $rental->apartment->apartment_number . '] Monthly rent',
                'amount'           => $rentAmount,
                'transaction_date' => $paymentDate,
                'reference_number' => $reference,
                'note'             => $note,
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
            $items[] = 'Rent: $' . number_format($rentAmount, 2);
        }

        if (!empty($data['pay_utilities'])) {
            $utilityResult = $this->settleCurrentMonthUtilities($rental, $paymentDate, $paymentMethod, $reference);
            if ($utilityResult !== null) {
                $totalPaid += $utilityResult['amount'];
                $items[]    = 'Utilities: $' . number_format($utilityResult['amount'], 2);
            }
        }

        return [
            'total_paid' => $totalPaid,
            'items'      => $items,
        ];
    }

    /**
     * Settle all unpaid utility charges for the current calendar month.
     * Splits the resulting income into two Accounts rows (utility vs other)
     * so dashboard breakdowns work.
     *
     * @return array{amount: float}|null  null when there were no unpaid charges
     */
    private function settleCurrentMonthUtilities(Rentals $rental, string $paymentDate, string $paymentMethod, ?string $reference): ?array
    {
        $unpaid = Utilities::where('rental_id', $rental->id)
            ->forMonth(now()->month, now()->year)
            ->unpaid()
            ->get();

        if ($unpaid->isEmpty()) {
            return null;
        }

        $utilityTotal = (float) $unpaid->sum('charge_amount');

        $payment = Payments::create([
            'rental_id'             => $rental->id,
            'amount'                => $utilityTotal,
            'due_date'              => $paymentDate,
            'paid_at'               => $paymentDate,
            'payment_method'        => $paymentMethod,
            'payment_status'        => 'paid',
            'payment_type'          => 'utilities',
            'transaction_reference' => $reference,
            'late_fee'              => 0,
            'note'                  => 'Utility charges: ' . $unpaid->pluck('utility_type')->implode(', '),
        ]);

        // Split: electricity+water → utility_income; internet/parking/trash/other → other_income
        $utilityIncomeTypes = ['electricity', 'water'];
        $otherIncomeTypes   = ['internet', 'parking', 'trash', 'other'];

        $utilIncomeAmt  = (float) $unpaid->whereIn('utility_type', $utilityIncomeTypes)->sum('charge_amount');
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
                'paid_at'     => now(),
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
            'payment_id'       => $paymentId,
            'user_id'          => $this->userId,
            'account_type'     => Accounts::TYPE_INCOME,
            'category'         => Accounts::CAT_LATE_FEE_INCOME,
            'description'      => '[Apt ' . $rental->apartment->apartment_number . '] Late fee',
            'amount'           => $amount,
            'transaction_date' => $date,
            'reference_number' => $reference,
            'note'             => $note,
        ]);
    }

    private function createUtilityIncomeAccount(Rentals $rental, int $paymentId, string $category, float $amount, string $date, ?string $reference, string $typesList, string $notePrefix): void
    {
        Accounts::create([
            'fiscal_period_id' => $this->period->id,
            'payment_id'       => $paymentId,
            'user_id'          => $this->userId,
            'account_type'     => Accounts::TYPE_INCOME,
            'category'         => $category,
            'description'      => '[Apt ' . $rental->apartment->apartment_number . '] ' . ucwords($typesList),
            'amount'           => $amount,
            'transaction_date' => $date,
            'reference_number' => $reference,
            'note'             => $notePrefix . ': ' . $typesList,
        ]);
    }
}
