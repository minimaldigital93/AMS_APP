<?php

namespace App\Services\RevenueExpense;

use App\Models\ApartmentFixedExpense;
use App\Models\Rentals;
use App\Models\Utilities;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Monthly bill generation — converts apartment fixed-expense templates into
 * concrete Utilities rows (charges the tenant owes). Income is booked when the
 * charge is paid (checkout); no ledger row is written at billing time.
 *
 * The (rental_id, utility_type, billing_month, billing_year) invariant is
 * enforced here: if a charge already exists for that combination, it's
 * skipped (not duplicated).
 *
 * Parking is the one type with a second source: the tenant's registered
 * vehicles (tenant_vehicles). A room's parking is ONE charge however many
 * vehicles it covers — the invariant above leaves room for exactly one — so the
 * tenant's priced vehicles are summed into it. When a tenant has priced
 * vehicles they are the authority and the room's fixed `parking` template is
 * skipped for that rental; billing both would mean charging the same spot
 * twice, and only one of the two could win the row anyway.
 */
class MonthlyBillingService
{
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
            $totalAmount = 0.0;

            foreach ($bills as $billData) {
                // Only the apartment checkbox gates the row now: vehicle
                // parking has no template to tick, so a rental whose whole bill
                // is vehicle parking posts no `expenses` array at all and used
                // to be skipped here.
                if (empty($billData['selected'])) {
                    continue;
                }

                $rental = Rentals::with(['tenant.vehicles', 'apartment'])->findOrFail($billData['rental_id']);

                // Vehicles first, so a tenant with priced vehicles wins the
                // single parking row over the room's fixed template below.
                $vehicleFee = $this->vehicleParkingFee($rental);
                if ($vehicleFee > 0 && $this->bill($rental, 'parking', $vehicleFee, $billingDate)) {
                    $recordedCount++;
                    $totalAmount += $vehicleFee;
                }

                foreach ($billData['expenses'] ?? [] as $expData) {
                    if (empty($expData['selected'])) {
                        continue;
                    }

                    $fixedExpense = ApartmentFixedExpense::findOrFail($expData['expense_id']);
                    $amount = (float) $expData['amount'];

                    if ($fixedExpense->expense_type === 'parking' && $vehicleFee > 0) {
                        continue;
                    }

                    if ($this->bill($rental, $fixedExpense->expense_type, $amount, $billingDate)) {
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
            $totalAmount = 0.0;

            $apartments = $apartmentsScope->clone()
                ->with(['activeFixedExpenses', 'rentals' => function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })->with('tenant.vehicles');
                }])
                ->get();

            foreach ($apartments as $apartment) {
                foreach ($apartment->rentals as $rental) {
                    // ensure description has access to apartment number without re-loading
                    $rental->setRelation('apartment', $apartment);

                    // Vehicles first — see the class docblock: priced vehicles
                    // are the authority for the room's single parking charge.
                    $vehicleFee = $this->vehicleParkingFee($rental);
                    if ($vehicleFee > 0 && $this->bill($rental, 'parking', $vehicleFee, $billingDate)) {
                        $recordedCount++;
                        $totalAmount += $vehicleFee;
                    }

                    foreach ($apartment->activeFixedExpenses as $fe) {
                        if ($fe->expense_type === 'parking' && $vehicleFee > 0) {
                            continue;
                        }

                        if ($this->bill($rental, $fe->expense_type, (float) $fe->amount, $billingDate)) {
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
     * What this rental's tenant owes for parking off their registered
     * vehicles: the sum of every priced one. Zero when the tenant keeps no
     * vehicle, or keeps only unpriced ones (parking included in the rent) —
     * in which case the room's fixed `parking` template, if any, still applies.
     *
     * Public so the bill-generation page can show the same figure it is about
     * to charge (see RevenueExpenseController::generateMonthlyBills()).
     */
    public function vehicleParkingFee(Rentals $rental): float
    {
        $tenant = $rental->tenant;

        if (! $tenant) {
            return 0.0;
        }

        $tenant->loadMissing('vehicles');

        return $tenant->vehicleParkingFee();
    }

    /**
     * Create the Utilities row (the charge the tenant owes) for one (rental,
     * utility_type, month, year). Returns false (and writes nothing) if the
     * pair has already been billed.
     *
     * Deliberately does NOT write an Accounts expense row (2026-07 accounting
     * audit): billing a tenant is not a cost the landlord incurred. The old
     * mirror `business_fixed` expense suppressed real profit on every recharge
     * and double-counted whenever the actual vendor bill was also recorded.
     * Real landlord costs enter the ledger through record-expense; the income
     * side is booked when the charge is paid (checkout), as before.
     */
    private function bill(Rentals $rental, string $utilityType, float $amount, Carbon $billingDate): bool
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
            'tenant_id' => $rental->tenant_id,
            'rental_id' => $rental->id,
            'utility_type' => $utilityType,
            'meter_reading_in' => 0,
            'meter_reading_out' => 0,
            'charge_amount' => $amount,
            'billing_month' => $billingDate->month,
            'billing_year' => $billingDate->year,
            'paid_status' => false,
            'paid_at' => null,
        ]);

        return true;
    }
}
