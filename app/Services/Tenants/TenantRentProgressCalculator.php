<?php

namespace App\Services\Tenants;

use App\Models\FiscalPeriods;
use App\Models\Rentals;
use App\Models\Tenants;
use Carbon\Carbon;

/**
 * Calculates the current-month rent progress card data for the tenant index
 * page. When a fiscal period is given, the supervisor scope sums payments
 * within the period; otherwise (admin scope) it falls back to the current
 * calendar month.
 *
 * Returned shape per tenant id:
 *   ['rent', 'paid', 'percent', 'status', 'paid_date', 'days_stayed',
 *    'total_days', 'day_percent', 'due_date',
 *    'cycle_percent', 'days_left', 'next_renewal_label', 'stay_label',
 *    'months_stayed']
 *
 * Status values: 'paid' | 'partial' | 'overdue' | 'unpaid'.
 */
class TenantRentProgressCalculator
{
    /**
     * @param  iterable<Tenants>  $tenants
     * @return array<int, array<string, mixed>>
     */
    public function map(iterable $tenants, ?FiscalPeriods $activePeriod = null): array
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
            ->with(['payments' => function ($q) use ($activePeriod, $currentMonth, $currentYear) {
                $q->where('payment_type', 'rent')
                    ->where('payment_status', 'paid');

                if ($activePeriod) {
                    $q->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);
                } else {
                    $q->whereMonth('paid_at', $currentMonth)
                        ->whereYear('paid_at', $currentYear);
                }
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
        $monthlyRent = $rental->rent_amount;
        $paidDate = $rental->payments->first()?->paid_at;

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

        $dueDay = min($rentalStart->day, $totalDaysInMonth);
        $dueDate = Carbon::create($currentYear, $currentMonth, $dueDay)->endOfDay();
        $isFirstMonth = ($rentalStart->month === $currentMonth && $rentalStart->year === $currentYear);
        $isPastDue = now()->gt($dueDate);

        $status = match (true) {
            $payPercent >= 100 => 'paid',
            $payPercent > 0 => 'partial',
            $isPastDue && ! $isFirstMonth => 'overdue',
            default => 'unpaid',
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
