<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payments;
use App\Models\Rentals;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display tenant dashboard with their specific data.
     */
    public function index(): View
    {
        $user = Auth::user();
        $stats = $this->getStats($user->id);
        return view('tenant.dashboard', ['stats' => $stats]);
    }

    /**
     * Fetch dashboard statistics for the logged-in tenant.
     */
    private function getStats($userId): array
    {
        return [
            'rentals' => [
                'active' => Rentals::where('tenant_id', $userId)
                    ->where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })->count(),
                'total' => Rentals::where('tenant_id', $userId)->count(),
            ],
            'payments' => [
                'pending' => Payments::whereHas('rental', fn($q) => $q->where('tenant_id', $userId))
                    ->where('payment_status', 'pending')->count(),
                'overdue' => Payments::whereHas('rental', fn($q) => $q->where('tenant_id', $userId))
                    ->where('due_date', '<', now())
                    ->whereNull('paid_at')
                    ->where('payment_status', '!=', 'cancelled')
                    ->count(),
                'total_pending' => Payments::whereHas('rental', fn($q) => $q->where('tenant_id', $userId))
                    ->where('payment_status', 'pending')->sum('amount'),
            ],
        ];
    }
}
