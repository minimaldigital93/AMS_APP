<?php

namespace App\Services\Billing;

use Carbon\Carbon;

/**
 * Partial-period rent maths. Pure — no DB, no settings, no clock — so the
 * 28/29/30/31-day and leap-year matrix can be tested exhaustively.
 *
 * Periods are half-open, [start, end): the end date is the first day of the
 * next period. Aug 8 → Sep 2 is therefore 25 days, and two consecutive cycles
 * never double-charge their shared boundary day.
 *
 * The daily rate is `monthly rent ÷ days in the month the period STARTS in`.
 * That divisor is what makes a full cycle come out at exactly one month's rent
 * whatever the month length:
 *
 *     Sep 2 → Oct 2 = 30 days ÷ 30-day September × $300 = $300.00
 *     Oct 2 → Nov 2 = 31 days ÷ 31-day October   × $300 = $300.00
 *     Feb 2 → Mar 2 = 28 days ÷ 28-day February  × $300 = $300.00
 *
 * (Full cycles still bypass this and take the rent verbatim — see
 * BillingCycleService — so no rounding drift can accumulate over a tenancy.)
 */
class ProrationCalculator
{
    /**
     * Length of the half-open period [start, end) in days. Never negative.
     */
    public function daysBetween(Carbon $start, Carbon $end): int
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->startOfDay();

        if ($to->lte($from)) {
            return 0;
        }

        return (int) $from->diffInDays($to);
    }

    /**
     * Rent per day for a period starting on $periodStart: the monthly rent
     * spread across that month's actual length (28/29/30/31).
     */
    public function dailyRate(float $monthlyRent, Carbon $periodStart): float
    {
        $daysInMonth = $periodStart->daysInMonth;

        if ($daysInMonth <= 0 || $monthlyRent <= 0) {
            return 0.0;
        }

        return $monthlyRent / $daysInMonth;
    }

    /**
     * Rent for the partial period [start, end), rounded to cents.
     *
     * Example: $300/month, Aug 8 → Sep 2 → 25 days × ($300 ÷ 31) = $241.94.
     */
    public function prorate(float $monthlyRent, Carbon $start, Carbon $end): float
    {
        $days = $this->daysBetween($start, $end);

        if ($days <= 0) {
            return 0.0;
        }

        return round($days * $this->dailyRate($monthlyRent, $start), 2);
    }
}
