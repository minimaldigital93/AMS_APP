<?php

namespace App\Services\Billing;

use App\Models\Rentals;
use Carbon\Carbon;

/**
 * Rent collection day.
 *
 * An account can nominate one day of the month (1–28) on which every tenant's
 * rent falls due. Two rules, and that is the whole feature:
 *
 *   1. The FIRST bill runs from the move-in date to the collection day of the
 *      following month, prorated.
 *   2. Every bill after that runs collection day → collection day, at the full
 *      monthly rent.
 *
 * Worked example — $300/month, moved in Aug 8, collection day 2:
 *
 *     August    Aug 8 → Sep 2   25 days   $241.94   due Sep 2
 *     September Sep 2 → Oct 2   30 days   $300.00   due Sep 2
 *     October   Oct 2 → Nov 2   31 days   $300.00   due Oct 2
 *
 * Because rule 1 always lands in the month AFTER move-in, exactly one period
 * starts in each calendar month — which is what lets the month-navigated rent
 * collection page keep working unchanged.
 *
 * Nothing is stored: no invoice table, no scheduler, no per-lease columns. Rent
 * owed is derived on the fly, exactly as it always has been here. Leaving the
 * setting blank keeps the account on the original behaviour (rent due on each
 * tenant's own move-in day), so nothing changes until an owner opts in.
 */
class BillingCycleService
{
    /**
     * The 29th, 30th and 31st don't exist in every month, so they can never be
     * a monthly anchor. Capping at 28 is why February and leap years need no
     * special-casing anywhere.
     */
    public const MAX_COLLECTION_DAY = 28;

    /**
     * Days of grace after the due date before rent counts as late. 3 is what
     * the printed contract (ប្រការ៥) has always promised.
     */
    public const DEFAULT_OVERDUE_DAYS = 3;

    public function __construct(private readonly ProrationCalculator $proration) {}

    /**
     * The account's rent collection day, or null when it has none — which is
     * the signal to every caller to keep using the legacy move-in-day billing.
     */
    public function collectionDay(): ?int
    {
        $day = (int) settings('billing_cycle_day', 0);

        return ($day >= 1 && $day <= self::MAX_COLLECTION_DAY) ? $day : null;
    }

    /**
     * Grace days between the due date and rent counting as overdue. Drives the
     * overdue badge, the late-fee day count, and ប្រការ៥ of the contract.
     */
    public function overdueDays(): int
    {
        $value = settings('billing_overdue_days');

        return ($value === null || $value === '')
            ? self::DEFAULT_OVERDUE_DAYS
            : max(0, (int) $value);
    }

    /**
     * What this lease owes for the given calendar month, or null when the
     * account has no collection day set (or the lease has no move-in date), in
     * which case the caller keeps its existing calendar-month behaviour.
     */
    public function periodFor(Rentals $rental, int $month, int $year): ?BillingPeriod
    {
        $day = $this->collectionDay();

        if ($day === null || ! $rental->start_date) {
            return null;
        }

        $moveIn = $rental->start_date->copy()->startOfDay();
        $rent = (float) $rental->rent_amount;

        // Rule 1 — the move-in month. Anchoring on the 1st before adding a
        // month keeps this off the 31st-of-a-30-day-month rocks; the day is
        // capped at 28 so setting it after can never overflow either.
        if ($moveIn->month === $month && $moveIn->year === $year) {
            $end = $moveIn->copy()->startOfMonth()->addMonth()->day($day);

            return new BillingPeriod(
                start: $moveIn,
                end: $end,
                dueDate: $end,
                days: $this->proration->daysBetween($moveIn, $end),
                amount: $this->proration->prorate($rent, $moveIn, $end),
                // Moving in exactly on the collection day gives a whole month,
                // which prorates to the rent verbatim — not worth flagging.
                isProrated: $moveIn->day !== $day,
            );
        }

        // Rule 2 — every month after that is a full cycle at the full rent.
        $start = Carbon::create($year, $month, $day)->startOfDay();

        return new BillingPeriod(
            start: $start,
            end: $start->copy()->addMonthNoOverflow(),
            dueDate: $start,
            days: $this->proration->daysBetween($start, $start->copy()->addMonthNoOverflow()),
            amount: $rent,
            isProrated: false,
        );
    }
}
