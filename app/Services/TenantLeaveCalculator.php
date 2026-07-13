<?php

namespace App\Services;

use App\Models\Apartments;
use App\Models\Rentals;
use App\Models\Tenants;
use Carbon\Carbon;

class TenantLeaveCalculator
{
    /**
     * Total tenancy length in days (move-in → leave, inclusive). Display /
     * record-keeping only — NOT a billing input (see calculateProRataRent).
     */
    public function calculateStayDays(Rentals $rental, Carbon $leaveDate): int
    {
        $moveInDate = Carbon::parse($rental->start_date);

        return (int) $moveInDate->diffInDays($leaveDate) + 1;
    }

    /**
     * Days of the FINAL month being settled: from the start of the leave
     * month (or the rental start, if the tenancy began inside that month)
     * through the leave date, inclusive. Capped at 30 (a 31-day month is
     * still one banker's month, never 31/30 of the rent).
     */
    public function finalMonthDays(Rentals $rental, Carbon $leaveDate): int
    {
        $monthStart = $leaveDate->copy()->startOfMonth();
        $rentalStart = Carbon::parse($rental->start_date)->startOfDay();
        $anchor = $rentalStart->greaterThan($monthStart) ? $rentalStart : $monthStart;

        $days = (int) $anchor->diffInDays($leaveDate->copy()->startOfDay()) + 1;

        return min(max($days, 1), 30);
    }

    /**
     * Pro-rata rent for the final (unbilled) month only, at rent/30 per day.
     *
     * Earlier months were already billed through the normal monthly rent flow —
     * the settlement must never re-charge them. (The pre-2026-07 version
     * multiplied the WHOLE tenancy's days by the daily rate, overcharging any
     * move-out after the first month.)
     */
    public function calculateProRataRent(Rentals $rental, Carbon $leaveDate): float
    {
        $dailyRate = $rental->rent_amount / 30;

        return round($this->finalMonthDays($rental, $leaveDate) * $dailyRate, 2);
    }

    /**
     * Calculate total settlement amount
     */
    public function calculateSettlement(
        Rentals $rental,
        Tenants $tenant,
        Carbon $leaveDate,
        array $charges = [],
        float $deposit = 0
    ): array {
        $charges = array_merge([
            'pro_rata_rent' => 0,
            'electricity' => 0,
            'water' => 0,
            'internet' => 0,
            'parking' => 0,
            'extra' => 0,
        ], $charges);

        $totalAmountDue = array_sum($charges);
        $depositApplied = min($deposit, $totalAmountDue);
        $balanceDue = max(0, $totalAmountDue - $depositApplied);
        $refundAmount = $deposit - $depositApplied;

        return [
            'stay_days' => $this->calculateStayDays($rental, $leaveDate),
            'pro_rata_rent' => round($charges['pro_rata_rent'], 2),
            'electricity_charge' => round($charges['electricity'], 2),
            'water_charge' => round($charges['water'], 2),
            'internet_charge' => round($charges['internet'], 2),
            'parking_charge' => round($charges['parking'], 2),
            'extra_charges' => round($charges['extra'], 2),
            'total_amount_due' => round($totalAmountDue, 2),
            'deposit_applied' => round($depositApplied, 2),
            'balance_due' => round($balanceDue, 2),
            'refund_amount' => round($refundAmount, 2),
        ];
    }

    /**
     * Archive a tenant after leave
     */
    public function archiveTenant(Tenants $tenant, Carbon $archivedAt): bool
    {
        return $tenant->update([
            'archived_at' => $archivedAt,
            'status' => 'moved_out',
        ]);
    }

    /**
     * Update apartment to available after tenant leaves
     */
    public function markApartmentAvailable(Apartments $apartment): bool
    {
        return $apartment->update([
            'status' => 'available',
        ]);
    }

    /**
     * Clear tenant from apartment
     */
    public function clearTenantFromApartment(Tenants $tenant): bool
    {
        return $tenant->update([
            'apartment_id' => null,
        ]);
    }
}
