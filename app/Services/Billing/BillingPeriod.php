<?php

namespace App\Services\Billing;

use Carbon\Carbon;

/**
 * One month's rent obligation for a lease on a fixed collection day: the
 * half-open span [start, end) it covers, what it costs, and when it is due.
 *
 * Nothing is stored — BillingCycleService derives this on the fly from the
 * lease's move-in date and the account's collection-day setting, exactly the
 * way rent has always been derived from the calendar here.
 */
class BillingPeriod
{
    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly Carbon $dueDate,
        public readonly int $days,
        public readonly float $amount,
        public readonly bool $isProrated,
    ) {}

    /** e.g. "Aug 8 – Sep 2, 2026" — the span the tenant is paying for. */
    public function label(): string
    {
        return $this->start->format('M j').' – '.$this->end->format('M j, Y');
    }
}
