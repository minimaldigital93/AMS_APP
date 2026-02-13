<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Get dashboard for web view.
     */
    public function index(): View
    {
        $stats = $this->getStats();
        return view('admin.dashboard', ['stats' => $stats]);
    }

    /**
     * Get admin dashboard statistics (API).
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->getStats();
        return response()->json($stats);
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

    /**
     * Get recent activities for admin dashboard.
     */
    public function recentActivities(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);

        $recentPayments = Payments::with(['rental.tenant', 'rental.apartment'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'type' => 'payment',
                'message' => "Payment of {$p->amount} for " . ($p->rental->tenant->name ?? 'Unknown'),
                'status' => $p->payment_status,
                'created_at' => $p->created_at,
            ]);

        $recentTenants = Tenants::with('apartment')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($t) => [
                'type' => 'tenant',
                'message' => "New tenant: {$t->name}",
                'status' => $t->status,
                'created_at' => $t->created_at,
            ]);

        $activities = collect()
            ->merge($recentPayments)
            ->merge($recentTenants)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();

        return response()->json(['data' => $activities]);
    }
}
