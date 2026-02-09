<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Apartments;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;


class DashboardController extends Controller
{
    /**
     * Get supervisor dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $apartmentIds = Apartments::where('supervisor_id', Auth::id())->pluck('id')->toArray();
        $rentalIds = Rentals::whereIn('apartment_id', $apartmentIds)->pluck('id')->toArray();

        $stats = [
            'apartments' => [
                'total' => count($apartmentIds),
                'available' => Apartments::whereIn('id', $apartmentIds)->where('status', 'available')->count(),
                'occupied' => Apartments::whereIn('id', $apartmentIds)->where('status', 'occupied')->count(),
                'maintenance' => Apartments::whereIn('id', $apartmentIds)->where('status', 'maintenance')->count(),
            ],
            'tenants' => [
                'total' => Tenants::whereIn('apartment_id', $apartmentIds)->count(),
                'active' => Tenants::whereIn('apartment_id', $apartmentIds)->where('status', 'active')->count(),
            ],
            'rentals' => [
                'active' => Rentals::whereIn('apartment_id', $apartmentIds)
                    ->where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })->count(),
            ],
            'payments' => [
                'pending' => Payments::whereIn('rental_id', $rentalIds)
                    ->where('payment_status', 'pending')
                    ->count(),
                'overdue' => Payments::whereIn('rental_id', $rentalIds)
                    ->where('due_date', '<', now())
                    ->whereNull('paid_at')
                    ->where('payment_status', '!=', 'cancelled')
                    ->count(),
                'total_collected' => Payments::whereIn('rental_id', $rentalIds)
                    ->where('payment_status', 'paid')
                    ->sum('amount'),
            ],
            'revenue' => [
                'total_monthly_rent' => Apartments::whereIn('id', $apartmentIds)
                    ->where('status', 'occupied')
                    ->sum('monthly_rent'),
            ],
        ];

        return response()->json($stats);
    }
}
