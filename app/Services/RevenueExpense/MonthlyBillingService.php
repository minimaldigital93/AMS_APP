<?php

namespace App\Services\RevenueExpense;

use App\Models\Accounts;
use App\Models\ApartmentFixedExpense;
use App\Models\FiscalPeriods;
use App\Models\Rentals;
use App\Models\Utilities;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Monthly bill generation — converts apartment fixed-expense templates into
 * concrete Utilities rows (charges the tenant owes) and matching Accounts rows
 * for fiscal tracking.
 *
 * The (rental_id, utility_type, billing_month, billing_year) invariant from
 * CLAUDE.md sec. 6 is enforced here: if a charge already exists for that
 * combination, it's skipped (not duplicated).
 */
class MonthlyBillingService
{
    public function __construct(
        private int $userId,
        private FiscalPeriods $period,
    ) {}

    /**
     * Bill the (apartment, expense) pairs the user selected on the form.
     *
     * Form shape: bills[].rental_id, bills[].selected, bills[].expenses[].expense_id,
     * bills[].expenses[].amount, bills[].expenses[].selected.
     *
     * @return array{count: int, total: float}
     */
    public function processSelected(array $bills, Carbon $billingDate): array
    {
        return DB::transaction(function () use ($bills, $billingDate) {
        $recordedCount = 0;
        $totalAmount   = 0.0;

        foreach ($bills as $billData) {
            if (empty($billData['selected']) || empty($billData['expenses'])) {
                continue;
            }

            $rental = Rentals::with(['tenant', 'apartment'])->findOrFail($billData['rental_id']);

            foreach ($billData['expenses'] as $expData) {
                if (empty($expData['selected'])) {
                    continue;
                }

                $fixedExpense = ApartmentFixedExpense::findOrFail($expData['expense_id']);
                $amount       = (float) $expData['amount'];

                if ($this->bill($rental, $fixedExpense->expense_type, $fixedExpense->expense_name, $amount, $billingDate)) {
                    $recordedCount++;
                    $totalAmount += $amount;
                }
            }
        }

            return ['count' => $recordedCount, 'total' => $totalAmount];
        });
    }

    /**
     * Auto-bill every active fixed-expense template across every active rental
     * in the given apartment scope. The (rental, type, month, year) guard
     * prevents double-billing on repeated runs.
     *
     * @return array{count: int, total: float}
     */
    public function processAll(Builder $apartmentsScope, Carbon $billingDate): array
    {
        return DB::transaction(function () use ($apartmentsScope, $billingDate) {
        $recordedCount = 0;
        $totalAmount   = 0.0;

        $apartments = $apartmentsScope->clone()
            ->with(['activeFixedExpenses', 'rentals' => function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('end_date')->orWhere('end_date', '>=', now());
                })->with('tenant');
            }])
            ->get();

        foreach ($apartments as $apartment) {
            foreach ($apartment->rentals as $rental) {
                // ensure description has access to apartment number without re-loading
                $rental->setRelation('apartment', $apartment);

                foreach ($apartment->activeFixedExpenses as $fe) {
                    if ($this->bill($rental, $fe->expense_type, $fe->expense_name, (float) $fe->amount, $billingDate)) {
                        $recordedCount++;
                        $totalAmount += (float) $fe->amount;
                    }
                }
            }
        }

            return ['count' => $recordedCount, 'total' => $totalAmount];
        });
    }

    /**
     * Create the Utilities row + Accounts ledger entry for one (rental,
     * utility_type, month, year). Returns false (and writes nothing) if the
     * pair has already been billed.
     */
    private function bill(Rentals $rental, string $utilityType, string $expenseName, float $amount, Carbon $billingDate): bool
    {
        $exists = Utilities::where('rental_id', $rental->id)
            ->where('utility_type', $utilityType)
            ->where('billing_month', $billingDate->month)
            ->where('billing_year', $billingDate->year)
            ->exists();

        if ($exists) {
            return false;
        }

        Utilities::create([
            'tenant_id'         => $rental->tenant_id,
            'rental_id'         => $rental->id,
            'utility_type'      => $utilityType,
            'meter_reading_in'  => 0,
            'meter_reading_out' => 0,
            'charge_amount'     => $amount,
            'billing_month'     => $billingDate->month,
            'billing_year'      => $billingDate->year,
            'paid_status'       => false,
            'paid_at'           => null,
        ]);

        Accounts::create([
            'fiscal_period_id' => $this->period->id,
            'payment_id'       => null,
            'user_id'          => $this->userId,
            'account_type'     => Accounts::TYPE_EXPENSE,
            'category'         => Accounts::CAT_BUSINESS_FIXED,
            'description'      => '[Apt ' . $rental->apartment->apartment_number . '] ' . $expenseName . ' (monthly)',
            'amount'           => $amount,
            'transaction_date' => $billingDate->toDateString(),
            'note'             => 'Auto-generated monthly fixed expense',
        ]);

        return true;
    }
}
