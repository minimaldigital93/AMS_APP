<?php

namespace App\Services\FiscalPeriod;

use App\Models\MonthlyPeriod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * How many finished months the account has left un-closed, and whether that
 * backlog has grown far enough to stop new money going into the books.
 *
 * Closing a month is what makes its figures real: `MonthlyPeriodManager::closeMonth()`
 * freezes the totals and carries the closing balance into the next month. Until
 * that happens the month's net income is a live sum that every later entry keeps
 * moving, no balance is carried forward, and nothing downstream — the balance
 * sheet, the period close, a reversal's guard rail — can rely on it. One month
 * behind is a reminder. Two is an account whose books have stopped being books,
 * and by then the carry-forward chain has to be rebuilt across every month at
 * once instead of one at a time.
 *
 * A month is DUE TO CLOSE once it has ended (`end_date` before today) and is
 * still open, inside a fiscal period that is itself still open. The month in
 * progress is never due — there is nothing to freeze yet — so on the 1st of a
 * new month exactly one month becomes due, and the account has until the end of
 * that month to close it before a second joins it.
 *
 * The two counts are deliberately the same question asked twice: `count` drives
 * the dashboard banner and `blocking` (more than ALLOWED_OPEN_MONTHS) drives the
 * middleware. Adjacency is NOT required — closing out of order is allowed, so
 * "two months are unclosed" is the condition, not "two consecutive months".
 *
 * An account with no MonthlyPeriod rows at all has no backlog and sees nothing:
 * the rows are minted when a fiscal period is opened, so that null is the
 * backward-compatibility seam for books that predate them.
 */
class MonthCloseBacklog
{
    /**
     * Finished months that may sit open before money entry is blocked. One:
     * the month just ended is a reminder, a second one is a stop.
     */
    public const ALLOWED_OPEN_MONTHS = 1;

    /**
     * The account's un-closed finished months, oldest first, or null when
     * there are none.
     *
     * Every model here is account-scoped (BelongsToAccount), so this answers
     * for whoever is making the request — an admin, a co-admin and a supervisor
     * all resolve to the same owner's books, which is the point: they share one
     * set of months and one backlog.
     *
     * @return array{
     *     months: \Illuminate\Support\Collection<int, MonthlyPeriod>,
     *     oldest: MonthlyPeriod,
     *     count: int,
     *     blocking: bool,
     *     names: list<string>,
     *     close_url: ?string,
     * }|null
     */
    public function build(?CarbonInterface $asOf = null): ?array
    {
        $today = ($asOf ? Carbon::instance($asOf) : Carbon::now())->startOfDay();

        $months = MonthlyPeriod::where('status', 'open')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->whereHas('fiscalPeriod', fn ($q) => $q->where('status', 'open'))
            ->orderBy('start_date')
            ->get();

        if ($months->isEmpty()) {
            return null;
        }

        $oldest = $months->first();

        return [
            'months' => $months,
            'oldest' => $oldest,
            'count' => $months->count(),
            'blocking' => $months->count() > self::ALLOWED_OPEN_MONTHS,
            'names' => $months->pluck('name')->all(),
            'close_url' => $this->closeUrlFor($oldest, Auth::user()),
        ];
    }

    /**
     * Where the viewer goes to close the oldest month, or null when they can't.
     *
     * Only an admin owns the close — a supervisor records into the admin's
     * books but has no month-close page of their own, so their banner has to
     * say "ask the owner" rather than offer a button that 403s. An admin
     * previewing the supervisor panel still gets the link, which is why this
     * reads the user's role rather than the panel it is rendered in.
     */
    private function closeUrlFor(MonthlyPeriod $oldest, ?User $user): ?string
    {
        if (! $user || ! $oldest->fiscal_period_id || ! $user->hasRole('admin')) {
            return null;
        }

        return route('admin.fiscalperiod.monthly-period.show', [
            $oldest->fiscal_period_id,
            $oldest->id,
        ]);
    }
}
