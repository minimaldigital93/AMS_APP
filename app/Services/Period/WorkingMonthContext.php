<?php

namespace App\Services\Period;

use Carbon\Carbon;

/**
 * The "working month" — the month of business the user last navigated to.
 *
 * Every business screen (dashboard, revenue & expense, record income, record
 * expense, break-even, the monthly calendar) is month-navigated, but the month
 * used to live only in the URL: stepping back to July on one page and then
 * following a sidebar link — which carries no ?month= — dropped the user back
 * on the current month, so a month's collection work had to be re-navigated on
 * every page. This context remembers the choice for the session so the whole
 * panel stays on the month the user is working in.
 *
 * Mirrors PropertyContext (the other global selection): a request-scoped
 * singleton whose value is memoized, resolved from the session, and written
 * back by the SetWorkingMonth middleware from any ?month=&year= it sees.
 *
 * Two rules carry the design:
 *
 *  - **Nothing remembered means null, never "now".** Callers keep their own
 *    default (now() for most pages, the whole fiscal period for the income
 *    statement), so a session that never navigated behaves exactly as before.
 *  - **The fiscal period still clamps it.** This says which month the user is
 *    looking at, not which months exist — a remembered month outside the active
 *    period is discarded by the same period logic that has always run.
 */
class WorkingMonthContext
{
    /** Session key holding the selection as 'YYYY-MM'. */
    public const SESSION_KEY = 'working_month';

    /** `false` = not yet resolved; `null` = resolved to "nothing remembered". */
    private Carbon|null|false $cache = false;

    /**
     * Remember a month the user navigated to. Anything that isn't a real
     * month/year pair is ignored, so a stray or tampered query string leaves
     * the current selection alone.
     */
    public function remember(?int $month, ?int $year): void
    {
        if (! $this->isValidMonth($month, $year)) {
            return;
        }

        session([self::SESSION_KEY => sprintf('%04d-%02d', $year, $month)]);
        $this->cache = Carbon::create($year, $month, 1)->startOfMonth();
    }

    /**
     * The remembered month (first day of it), or null when the user has not
     * navigated anywhere this session.
     */
    public function selected(): ?Carbon
    {
        if ($this->cache === false) {
            $this->cache = $this->fromSession();
        }

        return $this->cache?->copy();
    }

    /** Month number of the selection, or null. */
    public function month(): ?int
    {
        return $this->selected()?->month;
    }

    /** Year of the selection, or null. */
    public function year(): ?int
    {
        return $this->selected()?->year;
    }

    /** Drop the selection (back to each page's own default). */
    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->cache = null;
    }

    private function fromSession(): ?Carbon
    {
        $raw = session(self::SESSION_KEY);

        if (! is_string($raw) || ! preg_match('/^(\d{4})-(\d{2})$/', $raw, $m)) {
            return null;
        }

        $year = (int) $m[1];
        $month = (int) $m[2];

        if (! $this->isValidMonth($month, $year)) {
            return null;
        }

        return Carbon::create($year, $month, 1)->startOfMonth();
    }

    private function isValidMonth(?int $month, ?int $year): bool
    {
        return $month !== null && $year !== null
            && $month >= 1 && $month <= 12
            && $year >= 2000 && $year <= 2100;
    }
}
