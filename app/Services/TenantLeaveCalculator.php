<?php

namespace App\Services;

use App\Models\Apartments;
use App\Models\Rentals;
use App\Models\Tenants;
use Carbon\Carbon;

class TenantLeaveCalculator
{
    /**
     * Calculate actual stay days
     */
    public function calculateStayDays(Rentals $rental, Carbon $leaveDate): int
    {
        $moveInDate = Carbon::parse($rental->start_date);

        return $moveInDate->diffInDays($leaveDate) + 1;
    }

    /**
     * Calculate pro-rata rent based on actual stay days
     */
    public function calculateProRataRent(Rentals $rental, Carbon $leaveDate): float
    {
        $stayDays = $this->calculateStayDays($rental, $leaveDate);
        $dailyRate = $rental->rent_amount / 30;

        return round($stayDays * $dailyRate, 2);
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
