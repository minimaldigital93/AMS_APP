<?php

namespace App\Services;

use App\Models\Apartments;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\Utilities;
use Carbon\Carbon;

class TenantLeaveCalculator
{
    /**
     * Calculate pro-rata rent based on actual stay days
     */
    public function calculateProRataRent(Rentals $rental, Carbon $leaveDate): float
    {
        $moveInDate = Carbon::parse($rental->start_date);
        $actualStayDays = $moveInDate->diffInDays($leaveDate) + 1; // Include both start and end date
        
        // Get the number of days in the rental period
        $originalStayDays = $moveInDate->diffInDays(Carbon::parse($rental->end_date ?? $rental->start_date->addMonth())) + 1;
        
        // Calculate daily rate
        $dailyRate = $rental->rent_amount / 30; // Assuming 30 days per month
        
        // Calculate pro-rata rent
        return round($actualStayDays * $dailyRate, 2);
    }

    /**
     * Calculate actual stay days
     */
    public function calculateStayDays(Rentals $rental, Carbon $leaveDate): int
    {
        $moveInDate = Carbon::parse($rental->start_date);
        return $moveInDate->diffInDays($leaveDate) + 1; // Include both start and end date
    }

    /**
     * Calculate utility charges for the stay period
     */
    public function calculateUtilityCharges(
        Rentals $rental,
        Carbon $leaveDate,
        array $meterReadings = []
    ): array
    {
        $utilities = Utilities::where('rental_id', $rental->id)
            ->where('utility_type', '!=', 'internet')
            ->where('utility_type', '!=', 'parking')
            ->orderBy('billing_month', 'desc')
            ->orderBy('billing_year', 'desc')
            ->first();

        $charges = [
            'electricity' => 0,
            'water' => 0,
            'internet' => 0,
            'parking' => 0,
        ];

        // Calculate electricity charges
        if (isset($meterReadings['electricity_reading'])) {
            $lastReading = $utilities?->meter_reading_in ?? 0;
            $consumption = $meterReadings['electricity_reading'] - $lastReading;
            // Assuming rate per unit
            $charges['electricity'] = round($consumption * 2.5, 2); // Example rate
        }

        // Calculate water charges
        if (isset($meterReadings['water_reading'])) {
            $lastReading = $utilities?->meter_reading_in ?? 0;
            $consumption = $meterReadings['water_reading'] - $lastReading;
            // Assuming rate per unit
            $charges['water'] = round($consumption * 1.8, 2); // Example rate
        }

        // Calculate pro-rata internet (assuming monthly)
        $stayDays = $this->calculateStayDays($rental, $leaveDate);
        $charges['internet'] = round((100 / 30) * $stayDays, 2); // Assuming 100 per month

        // Calculate pro-rata parking (assuming monthly)
        $charges['parking'] = round((50 / 30) * $stayDays, 2); // Assuming 50 per month

        return $charges;
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
    ): array
    {
        // Ensure we have all charge values
        $charges = array_merge([
            'pro_rata_rent' => 0,
            'electricity' => 0,
            'water' => 0,
            'internet' => 0,
            'parking' => 0,
        ], $charges);

        // Calculate total amount due
        $totalAmountDue = array_sum($charges);

        // Apply deposit
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
     * Clear tenant from apartment (remove apartment assignment)
     */
    public function clearTenantFromApartment(Tenants $tenant): bool
    {
        return $tenant->update([
            'apartment_id' => null,
        ]);
    }
}
