<?php

namespace App\Services\Tenants;

use App\Models\Rentals;
use App\Services\Billing\BillingCycleService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * What one tenant owes for one month, stated the same way the rent collection
 * page states it.
 *
 * Rent here is DERIVED, never invoiced — so this asks BillingCycleService the
 * same question Shared\RevenueExpenseController::recordIncome() asks, applies
 * the same three-bucket vocabulary (paid / pending / overdue) and the same
 * two-sided rent/charges split. It is a second CALL SITE of the same
 * primitives, not a second derivation: every figure that decides money comes
 * from BillingCycleService and the utilities rows, exactly as the collection
 * page, the dashboard tiles and the printable bill already do.
 *
 * tests/Feature/Tenants/TenantObligationParityTest.php pins it against the
 * collection page scenario by scenario, because the one thing a tenant-facing
 * balance must never do is disagree with the landlord's.
 */
class TenantObligationService
{
    public function __construct(private readonly BillingCycleService $cycles) {}

    /**
     * @return array{
     *     month: int, year: int, label: string, is_current: bool,
     *     rent_amount: float, billing_period: ?\App\Services\Billing\BillingPeriod,
     *     due_date: Carbon, rent_status: string, rent_paid: bool, rent_paid_at: ?Carbon,
     *     is_upcoming: bool, overdue_days: int, late_fee_suggested: float,
     *     charges: Collection, charges_status: string, charges_settled: bool,
     *     unpaid_charge_total: float, total_charges: float,
     *     status: string, has_outstanding: bool, rent_outstanding: float, total_outstanding: float
     * }
     */
    public function forRental(Rentals $rental, int $month, int $year): array
    {
        $selectedDate = Carbon::create($year, $month, 1);
        $monthStart = $selectedDate->copy()->startOfMonth();
        $monthEnd = $selectedDate->copy()->endOfMonth();

        $isCurrentMonth = ($month === now()->month && $year === now()->year);
        $isFutureMonth = $monthStart->gt(now()->copy()->startOfMonth());
        $isPastMonth = ! $isCurrentMonth && ! $isFutureMonth;

        // Overdue is judged from inside the month being viewed, so a past month
        // is measured at its own end and a future one at its start — otherwise
        // every historical month reads overdue and every future one does too.
        $referenceNow = match (true) {
            $isCurrentMonth => now(),
            $isPastMonth => $monthEnd->copy(),
            default => $monthStart->copy(),
        };

        // A tenant's screen asks about every month of their tenancy at once, so
        // an already-loaded relation is filtered in memory rather than re-queried
        // per month — the same shape recordIncome() uses on its eager set.
        $monthPayments = $rental->relationLoaded('payments')
            ? $rental->payments->filter(
                fn ($p) => $p->payment_status === 'paid'
                    && $p->paid_at
                    && $p->paid_at->between($monthStart, $monthEnd)
            )
            : $rental->payments()
                ->where('payment_status', 'paid')
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->get();

        $notStartedYet = $rental->start_date && $rental->start_date->gt($monthEnd);

        $period = $this->cycles->periodFor($rental, $month, $year);
        $rentDue = $period ? $period->amount : (float) $rental->rent_amount;

        $rentPayment = $monthPayments->firstWhere('payment_type', 'rent');
        $paidThisMonth = $rentPayment !== null;

        $dueDate = $this->dueDateFor($rental, $period, $month, $year);

        $graceDays = $this->cycles->overdueDays();
        $overdueAfter = $dueDate->copy()->addDays($graceDays);
        $isOverdue = ! $paidThisMonth && ! $notStartedYet && $referenceNow->gt($overdueAfter);

        $isUpcoming = false;
        if ($paidThisMonth) {
            $rentStatus = 'paid';
        } elseif ($notStartedYet) {
            $rentStatus = 'pending';
            $isUpcoming = true;
        } elseif ($isOverdue) {
            $rentStatus = 'overdue';
        } else {
            $rentStatus = 'pending';
        }

        $charges = $rental->relationLoaded('utilities')
            ? $rental->utilities
                ->filter(fn ($u) => (int) $u->billing_month === $month && (int) $u->billing_year === $year)
                ->sortBy('utility_type')
                ->values()
            : $rental->utilities()
                ->forMonth($month, $year)
                ->orderBy('utility_type')
                ->get();

        $unpaidCharges = $charges->filter(fn ($u) => ! $u->paid_status);
        $unpaidChargeTotal = (float) $unpaidCharges->sum('charge_amount');

        // 'none' is not 'paid': it means the meters have not been read yet, so
        // nothing is owed AND the month is not finished.
        $chargesStatus = $charges->isEmpty()
            ? 'none'
            : ($unpaidCharges->isEmpty() ? 'paid' : 'pending');

        $chargesSettled = $chargesStatus === 'paid'
            || ($chargesStatus === 'none' && ($isPastMonth || $isFutureMonth));

        $status = ($rentStatus === 'paid' && ! $chargesSettled) ? 'pending' : $rentStatus;

        $lateFeePercent = (float) settings('late_fee_percent', 0);
        $overdueDays = $isOverdue ? (int) $overdueAfter->diffInDays($referenceNow) : 0;
        $suggestedLateFee = ($lateFeePercent > 0 && $overdueDays > 0 && ! $paidThisMonth)
            ? round($rentDue * ($lateFeePercent / 100) * $overdueDays, 2)
            : 0.0;

        $rentOutstanding = (! $paidThisMonth && ! $notStartedYet) ? $rentDue : 0.0;

        return [
            'month' => $month,
            'year' => $year,
            'label' => $selectedDate->format('F Y'),
            'is_current' => $isCurrentMonth,
            'rent_amount' => $rentDue,
            'billing_period' => $period,
            'due_date' => $dueDate,
            'rent_status' => $rentStatus,
            'rent_paid' => $paidThisMonth,
            'rent_paid_at' => $rentPayment?->paid_at,
            'is_upcoming' => $isUpcoming,
            'overdue_days' => $overdueDays,
            'late_fee_suggested' => $suggestedLateFee,
            'charges' => $charges,
            'charges_status' => $chargesStatus,
            'charges_settled' => $chargesSettled,
            'unpaid_charge_total' => $unpaidChargeTotal,
            'total_charges' => (float) $charges->sum('charge_amount'),
            'status' => $status,
            'has_outstanding' => ! $notStartedYet && (! $paidThisMonth || $unpaidChargeTotal > 0),
            'rent_outstanding' => $rentOutstanding,
            'total_outstanding' => round($rentOutstanding + $unpaidChargeTotal, 2),
        ];
    }

    /**
     * The current month plus every earlier month of this tenancy that still has
     * something outstanding, newest first.
     *
     * The current month is always included — a tenant needs to see "Paid" as
     * much as they need to see what is due. Earlier months appear only while
     * they are unsettled, so a tenancy in good standing shows exactly one card.
     *
     * @return list<array>
     */
    public function outstandingFor(Rentals $rental, int $lookbackMonths = 24): array
    {
        $rental->loadMissing(['payments', 'utilities']);

        $cursor = now()->startOfMonth();
        $floor = $rental->start_date
            ? $rental->start_date->copy()->startOfMonth()
            : $cursor->copy()->subMonths($lookbackMonths);

        if ($floor->lt($cursor->copy()->subMonths($lookbackMonths))) {
            $floor = $cursor->copy()->subMonths($lookbackMonths);
        }

        $out = [];
        while ($cursor->gte($floor)) {
            $obligation = $this->forRental($rental, $cursor->month, $cursor->year);

            if ($obligation['is_current'] || $obligation['has_outstanding']) {
                $out[] = $obligation;
            }

            $cursor->subMonth();
        }

        return $out;
    }

    /**
     * The legacy (no collection day) arms mirror recordIncome() exactly,
     * including its move-in-month special case — a tenant who moved in this
     * month owes on the anniversary a month later, not on the move-in day.
     */
    private function dueDateFor(Rentals $rental, mixed $period, int $month, int $year): Carbon
    {
        if ($period) {
            return $period->dueDate->copy()->endOfDay();
        }

        if (! $rental->start_date) {
            return Carbon::create($year, $month)->endOfMonth()->endOfDay();
        }

        $isFirstMonth = $rental->start_date->month === $month
            && $rental->start_date->year === $year;

        if ($isFirstMonth) {
            return $rental->start_date->copy()->addMonth()->endOfDay();
        }

        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        return Carbon::create($year, $month, min($rental->start_date->day, $daysInMonth))->endOfDay();
    }
}
