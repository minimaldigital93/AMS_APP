<?php

namespace App\Services\FiscalPeriod;

use App\Models\MonthlyPeriod;
use App\Models\Rentals;
use App\Models\Utilities;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only pre-close check for the monthly period lifecycle.
 *
 * Before an admin closes a month we surface the tenants who have NOT fully
 * paid what they owe for that month, so the close is an informed decision
 * (warn, never block — a late tenant must not be able to freeze the books).
 *
 * Two obligations are checked:
 *
 *   - Rent: derived, not stored. A rental active during the month owes
 *     `rent_amount`; it is considered paid when the sum of its paid rent
 *     Payments landing in that calendar month reaches `rent_amount`. Mirrors
 *     the rent-collection page (RevenueExpenseController::recordIncome) and
 *     TenantRentProgressCalculator so the two never disagree.
 *   - Utilities: stored as unpaid `Utilities` rows for the month; these
 *     already carry forward on their own until settled.
 *
 * Account scoping is handled by the models' BelongsToAccount global scope —
 * this service runs inside the admin's request, so every query is already
 * constrained to the current account.
 */
class MonthClosePreflight
{
    /**
     * @return array{
     *     rent: Collection<int, object>,
     *     utilities: Collection<int, object>,
     *     rent_count: int,
     *     utilities_count: int,
     *     total_count: int,
     *     rent_shortfall: float,
     *     utilities_outstanding: float,
     *     has_unpaid: bool,
     * }
     */
    public function unpaidFor(MonthlyPeriod $month): array
    {
        $monthNumber = (int) $month->month_number;
        $year = (int) $month->year;
        $monthStart = Carbon::create($year, $monthNumber, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $rent = $this->unpaidRent($monthNumber, $year, $monthStart, $monthEnd);
        $utilities = $this->unpaidUtilities($monthNumber, $year);

        return [
            'rent' => $rent,
            'utilities' => $utilities,
            'rent_count' => $rent->count(),
            'utilities_count' => $utilities->count(),
            'total_count' => $rent->count() + $utilities->count(),
            'rent_shortfall' => round((float) $rent->sum('shortfall'), 2),
            'utilities_outstanding' => round((float) $utilities->sum('amount'), 2),
            'has_unpaid' => $rent->isNotEmpty() || $utilities->isNotEmpty(),
        ];
    }

    /**
     * Rentals active during the month whose rent for that month is not fully
     * paid. "Active during the month" = started on/before the month end and
     * not ended before the month start.
     *
     * @return Collection<int, object>
     */
    private function unpaidRent(int $monthNumber, int $year, Carbon $monthStart, Carbon $monthEnd): Collection
    {
        $rentals = Rentals::query()
            ->where('start_date', '<=', $monthEnd)
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $monthStart);
            })
            ->with(['tenant', 'apartment', 'payments' => function ($pq) use ($monthNumber, $year) {
                $pq->where('payment_type', 'rent')
                    ->where('payment_status', 'paid')
                    ->whereMonth('paid_at', $monthNumber)
                    ->whereYear('paid_at', $year);
            }])
            ->get();

        return $rentals
            ->map(function (Rentals $rental) {
                $rent = (float) $rental->rent_amount;
                $paid = (float) $rental->payments->sum('amount');
                $shortfall = round(max(0, $rent - $paid), 2);

                if ($shortfall <= 0.01) {
                    return null; // fully paid — nothing to warn about
                }

                return (object) [
                    'rental_id' => $rental->id,
                    'tenant_name' => $rental->tenant?->name ?? '—',
                    'apartment' => $rental->apartment?->apartment_number ?? '—',
                    'rent' => $rent,
                    'paid' => $paid,
                    'shortfall' => $shortfall,
                    'status' => $paid > 0 ? 'partial' : 'unpaid',
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Unpaid utility charges billed for the month.
     *
     * @return Collection<int, object>
     */
    private function unpaidUtilities(int $monthNumber, int $year): Collection
    {
        return Utilities::query()
            ->where('billing_month', $monthNumber)
            ->where('billing_year', $year)
            ->where('paid_status', false)
            ->with(['rental.tenant', 'rental.apartment'])
            ->get()
            ->map(function (Utilities $utility) {
                return (object) [
                    'tenant_name' => $utility->rental?->tenant?->name ?? '—',
                    'apartment' => $utility->rental?->apartment?->apartment_number ?? '—',
                    'type' => $utility->utility_type,
                    'amount' => (float) $utility->charge_amount,
                ];
            })
            ->values();
    }
}
