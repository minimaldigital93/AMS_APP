<?php

namespace App\Services\Dashboard;

use App\Models\Floors;
use App\Models\Payments;
use Carbon\Carbon;

/**
 * Expected vs collected rent grouped by floor + apartment, for the dashboard's
 * per-floor revenue comparison panel.
 *
 * Supervisor scope: only floors with at least one apartment in scope appear
 * in the result. Admin scope: every floor is included.
 */
class ApartmentRevenueComparisonService
{
    public function __construct(private ?array $apartmentIds = null) {}

    public function build(Carbon $selectedMonth): array
    {
        $currentMonth = $selectedMonth->month;
        $currentYear = $selectedMonth->year;
        $apartmentIds = $this->apartmentIds;

        $floorsQuery = Floors::with(['apartments' => function ($q) use ($apartmentIds) {
            $q->select('id', 'floor_id', 'apartment_number', 'monthly_rent', 'status')
                ->orderBy('apartment_number');
            if ($apartmentIds !== null) {
                $q->whereIn('id', $apartmentIds);
            }
        }])->orderBy('id');

        $result = [];
        foreach ($floorsQuery->get() as $floor) {
            if ($floor->apartments->isEmpty()) {
                if ($this->apartmentIds !== null) {
                    continue; // supervisor: hide empty floors
                }
            }

            $floorExpected = 0.0;
            $floorActual = 0.0;
            $apartments = [];

            foreach ($floor->apartments as $apt) {
                $expected = (float) ($apt->monthly_rent ?? 0);
                $actual = (float) Payments::whereHas('rental', fn ($q) => $q->where('apartment_id', $apt->id))
                    ->where('payment_status', 'paid')
                    ->where('payment_type', 'rent')
                    ->whereMonth('paid_at', $currentMonth)
                    ->whereYear('paid_at', $currentYear)
                    ->sum('amount');

                $floorExpected += $expected;
                $floorActual += $actual;

                $apartments[] = [
                    'apartment' => $apt->apartment_number ?? "Apt {$apt->id}",
                    'expected' => round($expected, 2),
                    'actual' => round($actual, 2),
                    'percentage' => $expected > 0 ? round(($actual / $expected) * 100, 1) : 0,
                    'status' => $apt->status,
                ];
            }

            $result[] = [
                'floor' => $floor->floor_name ?? "Floor {$floor->id}",
                'expected' => round($floorExpected, 2),
                'actual' => round($floorActual, 2),
                'percentage' => $floorExpected > 0 ? round(($floorActual / $floorExpected) * 100, 1) : 0,
                'apartments' => $apartments,
            ];
        }

        return $result;
    }
}
