<?php

namespace App\Services\Tenants;

use App\Models\Rentals;
use App\Models\Tenants;
use App\Services\Billing\BillingCycleService;
use Carbon\Carbon;

/**
 * Calculates the CURRENT MONTH's rent progress card data for the tenant index
 * page — the same question the rent collection page answers, so the two must
 * give the same answer.
 *
 * Two rules this depends on, both learned the hard way:
 *
 *   - The payment window is **one calendar month**, never a fiscal period. The
 *     supervisor panel used to pass its active period here, which summed every
 *     rent payment in the year against ONE month's rent: a tenant who had paid
 *     Jan–Jul read 100% "paid" in an unpaid August, permanently, for exactly
 *     the people whose job is collecting it. The admin panel, passing no
 *     period, said "overdue" for the same tenant at the same moment.
 *   - Rent owed is **derived** (BillingCycleService), never `rentals.rent_amount`
 *     — a prorated move-in month is paid in full at the prorated figure, and
 *     dividing by the full rent leaves it stuck at "partial".
 *
 * Returned shape per tenant id:
 *   ['rent', 'paid', 'percent', 'status', 'paid_date', 'days_stayed',
 *    'total_days', 'day_percent', 'due_date',
 *    'cycle_percent', 'days_left', 'next_renewal_label', 'stay_label',
 *    'months_stayed']
 *
 * Status values: 'paid' | 'pending' | 'overdue' | 'upcoming'.
 *
 * Three buckets, deliberately — the same vocabulary as the dashboard tiles and
 * the rent collection page, so one tenant never reads "Paying" on one screen
 * and "Pending" on another. Anything short of the month's rent is pending; the
 * percentage bar beside the badge is where "how far along" is expressed.
 * ('upcoming' is not a fourth payment state: it marks a tenancy that has not
 * begun, so nothing is owed yet.)
 */
class TenantRentProgressCalculator
{
    public function __construct(private readonly BillingCycleService $cycles) {}

    /**
     * @param  iterable<Tenants>  $tenants
     * @return array<int, array<string, mixed>>
     */
    public function map(iterable $tenants): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $tenantIds = [];
        foreach ($tenants as $tenant) {
            $tenantIds[] = $tenant->id;
        }

        if ($tenantIds === []) {
            return [];
        }

        // Batch-load every candidate rental for the paginated tenants in one
        // query (avoids an N+1 of one lookup per tenant). Ordered newest-first
        // so the first row seen per tenant is the latest rental.
        $rentals = Rentals::whereIn('tenant_id', $tenantIds)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->with(['payments' => function ($q) use ($currentMonth, $currentYear) {
                // One calendar month, both panels. See the class docblock for
                // what widening this to a fiscal period did.
                $q->where('payment_type', 'rent')
                    ->where('payment_status', 'paid')
                    ->whereMonth('paid_at', $currentMonth)
                    ->whereYear('paid_at', $currentYear);
            }])
            ->orderByDesc('start_date')
            ->get();

        $rentProgressMap = [];
        foreach ($rentals as $rental) {
            // Keep only the latest rental per tenant (first one wins).
            if (isset($rentProgressMap[$rental->tenant_id])) {
                continue;
            }

            $rentProgressMap[$rental->tenant_id] = $this->progressFor($rental, $currentMonth, $currentYear)
                + $rental->stayProgress();
        }

        return $rentProgressMap;
    }

    /**
     * Per-tenant progress card values.
     *
     * @return array<string, mixed>
     */
    private function progressFor(Rentals $rental, int $currentMonth, int $currentYear): array
    {
        $paidAmount = $rental->payments->sum('amount');
        $paidDate = $rental->payments->first()?->paid_at;

        // What this lease actually owes for this month — prorated in the
        // move-in month when the account bills on a collection day. Null
        // period = no collection day set, so the full rent, as before.
        $period = $this->cycles->periodFor($rental, $currentMonth, $currentYear);
        $monthlyRent = $period ? $period->amount : (float) $rental->rent_amount;

        $monthStart = Carbon::create($currentYear, $currentMonth, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $totalDaysInMonth = $monthStart->daysInMonth;

        $rentalStart = Carbon::parse($rental->start_date)->startOfDay();
        $stayStart = $rentalStart->gt($monthStart) ? $rentalStart : $monthStart;
        $stayEnd = now()->gt($monthEnd) ? $monthEnd : now();

        $daysStayed = max((int) $stayStart->diffInDays($stayEnd) + 1, 0);
        $daysStayed = min($daysStayed, $totalDaysInMonth);

        $dayPercent = $totalDaysInMonth > 0 ? round(($daysStayed / $totalDaysInMonth) * 100) : 0;
        $payPercent = $monthlyRent > 0 ? min(round(($paidAmount / $monthlyRent) * 100, 1), 100) : 0;

        // Due date, and the grace the collection page (and the printed
        // contract's ប្រការ៥) promises before rent counts late.
        if ($period) {
            $dueDate = $period->dueDate->copy()->endOfDay();
        } else {
            $dueDay = min($rentalStart->day, $totalDaysInMonth);
            $dueDate = Carbon::create($currentYear, $currentMonth, $dueDay)->endOfDay();
        }
        $isFirstMonth = ($rentalStart->month === $currentMonth && $rentalStart->year === $currentYear);
        $isPastDue = now()->gt($dueDate->copy()->addDays($this->cycles->overdueDays()));

        // A tenancy that only begins in a later month isn't billable yet — it
        // must read as "upcoming", never overdue/unpaid. Mirrors the guard the
        // dashboard (DashboardStatsService) and rent-collection page use.
        $notStartedYet = $rentalStart->gt($monthEnd);

        $status = match (true) {
            $notStartedYet => 'upcoming',
            $payPercent >= 100 => 'paid',
            $isPastDue && ! $isFirstMonth => 'overdue',
            default => 'pending',
        };

        return [
            'rent' => $monthlyRent,
            'paid' => $paidAmount,
            'percent' => $payPercent,
            'status' => $status,
            'paid_date' => $paidDate ? Carbon::parse($paidDate)->format('M d') : null,
            'days_stayed' => $daysStayed,
            'total_days' => $totalDaysInMonth,
            'day_percent' => $dayPercent,
            'due_date' => $dueDate,
        ];
    }
}
