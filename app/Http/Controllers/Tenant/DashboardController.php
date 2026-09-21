<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Services\Tenants\TenantObligationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly TenantObligationService $obligations) {}

    /**
     * Display tenant dashboard with their apartment, rental, and payment info.
     */
    public function index(): View
    {
        $obligations = $this->obligations;

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
            'outstanding' => 0,
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

                // Rent owed is DERIVED, never rentals.rent_amount — a prorated
                // move-in month is billed at the prorated figure, so reading
                // the raw column reported a shortfall on a fully paid month.
                // And the progress bar is a RENT question: summing every
                // payment type let a utilities payment push rent to 100%.
                $obligation = $obligations->forRental($rental, $currentMonth, $currentYear);

                $rentPaid = (float) $currentMonthPayments
                    ->where('payment_type', 'rent')
                    ->sum('amount');
                $rentDue = (float) $obligation['rent_amount'];
                $percent = $rentDue > 0 ? min(round(($rentPaid / $rentDue) * 100, 1), 100) : 0;

                $paymentStats = [
                    'this_month_paid' => $rentPaid,
                    'this_month_total' => $rentDue,
                    'this_month_percent' => $percent,
                    // The same three-bucket answer the collection page gives,
                    // charges included — so the tenant's dashboard and their
                    // landlord's page cannot disagree about the same month.
                    'this_month_status' => $obligation['status'],
                    'outstanding' => (float) $obligation['total_outstanding'],
                    'all_time_paid' => Payments::where('rental_id', $rental->id)
                        ->where('payment_status', 'paid')
                        ->sum('amount'),
                ];
            }
        }

        return view('tenant.dashboard', compact('tenant', 'rental', 'currentMonthPayments', 'recentPayments', 'paymentStats'));
    }
}
