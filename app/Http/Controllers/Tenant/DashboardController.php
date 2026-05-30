<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display tenant dashboard with their apartment, rental, and payment info.
     */
    public function index(): View
    {
        $user = Auth::user();

        // Find the active tenant record linked to this user account
        $tenant = Tenants::where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->with(['apartment.floor'])
            ->first();

        $rental = null;
        $currentMonthPayments = collect();
        $recentPayments = collect();
        $paymentStats = [
            'this_month_paid' => 0,
            'this_month_total' => 0,
            'this_month_percent' => 0,
            'this_month_status' => 'unpaid',
            'all_time_paid' => 0,
        ];

        if ($tenant) {
            // Get the active rental for this tenant
            $rental = Rentals::where('tenant_id', $tenant->id)
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                })
                ->latest('start_date')
                ->first();

            if ($rental) {
                $currentMonth = now()->month;
                $currentYear = now()->year;

                // Payments made this month
                $currentMonthPayments = Payments::where('rental_id', $rental->id)
                    ->where('payment_status', 'paid')
                    ->whereMonth('paid_at', $currentMonth)
                    ->whereYear('paid_at', $currentYear)
                    ->orderBy('paid_at', 'desc')
                    ->get();

                // Recent payments (last 5)
                $recentPayments = Payments::where('rental_id', $rental->id)
                    ->where('payment_status', 'paid')
                    ->orderBy('paid_at', 'desc')
                    ->limit(5)
                    ->get();

                $paidThisMonth = $currentMonthPayments->sum('amount');
                $monthlyRent = $rental->rent_amount;
                $percent = $monthlyRent > 0 ? min(round(($paidThisMonth / $monthlyRent) * 100, 1), 100) : 0;

                $paymentStats = [
                    'this_month_paid' => $paidThisMonth,
                    'this_month_total' => $monthlyRent,
                    'this_month_percent' => $percent,
                    'this_month_status' => $percent >= 100 ? 'paid' : ($percent > 0 ? 'partial' : 'unpaid'),
                    'all_time_paid' => Payments::where('rental_id', $rental->id)
                        ->where('payment_status', 'paid')
                        ->sum('amount'),
                ];
            }
        }

        return view('tenant.dashboard', compact('tenant', 'rental', 'currentMonthPayments', 'recentPayments', 'paymentStats'));
    }
}
