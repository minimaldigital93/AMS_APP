<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display admin dashboard with statistics.
     */
    public function index(): View
    {
        $stats = $this->getStats();
        return view('admin.dashboard', ['stats' => $stats]);
    }

    /**
     * Fetch all dashboard statistics from database.
     */
    private function getStats(): array
    {
        return [
            'floors_count' => Floors::count(),
            'apartments' => [
                'total' => Apartments::count(),
                'available' => Apartments::where('status', 'available')->count(),
                'occupied' => Apartments::where('status', 'occupied')->count(),
                'maintenance' => Apartments::where('status', 'maintenance')->count(),
            ],
            'tenants' => [
                'total' => Tenants::count(),
                'active' => Tenants::where('status', 'active')->count(),
                'inactive' => Tenants::where('status', 'inactive')->count(),
                'pending' => Tenants::where('status', 'pending')->count(),
            ],
            'rentals' => [
                'total' => Rentals::count(),
                'active' => Rentals::where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })->count(),
            ],
            'payments' => [
                'pending' => Payments::where('payment_status', 'pending')->count(),
                'overdue' => Payments::where('due_date', '<', now())
                    ->whereNull('paid_at')
                    ->where('payment_status', '!=', 'cancelled')
                    ->count(),
                'total_collected' => Payments::where('payment_status', 'paid')->sum('amount'),
                'total_pending' => Payments::where('payment_status', 'pending')->sum('amount'),
            ],
            'revenue' => [
                'total_monthly_rent' => Apartments::where('status', 'occupied')->sum('monthly_rent'),
            ],
        ];
    }
}
