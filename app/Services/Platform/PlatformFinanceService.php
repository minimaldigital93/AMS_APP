<?php

namespace App\Services\Platform;

use App\Models\KhqrPayment;
use App\Models\PlatformExpense;
use App\Models\PlatformFiscalPeriod;
use App\Models\PlatformMonthlyClose;
use App\Models\PlatformWithdrawal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Computes the platform (SaaS) profit & loss for the superadmin: subscription
 * revenue (confirmed KHQR payments) minus recorded platform expenses, broken
 * down month-by-month across a fiscal period's date range and rolled up to a
 * period total.
 *
 * Revenue mirrors the superadmin dashboard's `platformRevenue` source so the
 * two pages never disagree — confirmed subscription payments only.
 */
class PlatformFinanceService
{
    /**
     * Full P&L for a fiscal period, broken into the calendar months it spans
     * (start month → end month).
     *
     * Each month carries its close state: whether it is closed, the owner
     * withdrawal taken, and the running carried-forward cash balance (opening +
     * profit − withdrawal), so the superadmin can close months and decide to
     * withdraw or carry the profit forward.
     *
     * @return array{
     *   months:array<int,array{key:string,label:string,year:int,month:int,revenue:float,
     *     expense:float,profit:float,closed:bool,opening:float,owner_withdrawal:float,
     *     carried:?float,withdrawal_note:?string,available:float,closeable:bool,reopenable:bool}>,
     *   revenue:float, expense:float, profit:float, margin:float,
     *   carried_total:float, withdrawn_total:float, opening_balance:float,
     *   period:\App\Models\PlatformFiscalPeriod,
     *   period_closed:bool, period_closeable:bool, open_months:int
     * }
     */
    public function forPeriod(PlatformFiscalPeriod $period): array
    {
        // Exact period bounds (day-level) drive what counts as revenue/expense;
        // the breakdown still rolls up by calendar month, so the first and last
        // months can be partial.
        $start = Carbon::parse($period->start_date)->startOfDay();
        $end = Carbon::parse($period->end_date)->endOfDay();

        // Revenue per calendar month — confirmed subscription payments. Keyed by
        // "Y-m" because a period can span more than one calendar year.
        $revenueByMonth = [];
        KhqrPayment::query()
            ->whereNotNull('subscription_id')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->get(['amount', 'paid_at'])
            ->each(function ($p) use (&$revenueByMonth) {
                $k = Carbon::parse($p->paid_at)->format('Y-m');
                $revenueByMonth[$k] = ($revenueByMonth[$k] ?? 0.0) + (float) $p->amount;
            });

        // Expense per calendar month — recorded platform expenses.
        $expenseByMonth = [];
        PlatformExpense::query()
            ->whereBetween('spent_at', [$start->toDateString(), $end->toDateString()])
            ->get(['amount', 'spent_at'])
            ->each(function ($e) use (&$expenseByMonth) {
                $k = Carbon::parse($e->spent_at)->format('Y-m');
                $expenseByMonth[$k] = ($expenseByMonth[$k] ?? 0.0) + (float) $e->amount;
            });

        // Close decisions for the years the period spans, keyed by "Y-m"
        // (a row exists only for closed months).
        $closes = PlatformMonthlyClose::query()
            ->whereIn('year', range($start->year, $end->year))
            ->get()
            ->keyBy(fn ($c) => sprintf('%04d-%02d', $c->year, $c->month));

        $periodClosed = $period->isClosed();
        $openingBalance = (float) $period->opening_balance;

        // Ordered list of "Y-m" keys the period covers (whole months touched).
        $keys = [];
        $cursor = $start->copy()->startOfMonth();
        $lastMonth = $end->copy()->startOfMonth();
        while ($cursor->lessThanOrEqualTo($lastMonth)) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        // The earliest open month is the only one that may be closed, and the
        // latest closed month the only one that may be reopened — this keeps the
        // carry-forward chain unambiguous (mirrors the admin MonthlyPeriod flow).
        $firstOpenKey = null;
        $lastClosedKey = null;
        foreach ($keys as $k) {
            if ($closes->has($k)) {
                $lastClosedKey = $k;
            } elseif ($firstOpenKey === null) {
                $firstOpenKey = $k;
            }
        }

        $now = now();
        $months = [];
        $totalRevenue = $totalExpense = 0.0;
        $carry = $openingBalance;   // running carried-forward cash balance (seeded by the period)
        $withdrawnTotal = 0.0;
        $openBegunMonths = 0;       // begun-but-not-closed months blocking the period close
        foreach ($keys as $k) {
            [$yy, $mm] = array_map('intval', explode('-', $k));
            $rev = round($revenueByMonth[$k] ?? 0.0, 2);
            $exp = round($expenseByMonth[$k] ?? 0.0, 2);
            $profitM = round($rev - $exp, 2);
            $totalRevenue += $rev;
            $totalExpense += $exp;

            $opening = round($carry, 2);
            $available = round($opening + $profitM, 2);
            $close = $closes->get($k);
            $closed = $close !== null;
            $withdrawal = $closed ? (float) $close->owner_withdrawal : 0.0;
            $carried = null;
            if ($closed) {
                $carried = round($opening + $profitM - $withdrawal, 2);
                $carry = $carried;
                $withdrawnTotal += $withdrawal;
            }

            // A month is closeable once it has begun (no closing the future).
            $hasBegun = Carbon::create($yy, $mm, 1)->lessThanOrEqualTo($now);
            if (! $closed && $hasBegun) {
                $openBegunMonths++;
            }

            $months[] = [
                'key' => $k,
                'label' => Carbon::create($yy, $mm, 1)->format('M Y'),
                'year' => $yy,
                'month' => $mm,
                'revenue' => $rev,
                'expense' => $exp,
                'profit' => $profitM,
                'closed' => $closed,
                'opening' => $opening,
                'owner_withdrawal' => round($withdrawal, 2),
                'carried' => $carried,
                'withdrawal_note' => $close?->withdrawal_note,
                'available' => $available,
                // A closed period locks all month actions.
                'closeable' => ! $periodClosed && ! $closed && $k === $firstOpenKey && $hasBegun,
                'reopenable' => ! $periodClosed && $closed && $k === $lastClosedKey,
            ];
        }

        $profit = round($totalRevenue - $totalExpense, 2);

        // Withdrawals booked at month-close (accumulated in the loop above).
        $monthCloseWithdrawn = round($withdrawnTotal, 2);

        // Ad-hoc owner withdrawals against this period's cash — taken any time the
        // period is open, on top of the month-close decisions above. They reduce
        // the cash position and add to the total withdrawn.
        $adhocWithdrawn = round((float) $period->withdrawals()->sum('amount'), 2);

        // Current cash position: opening balance plus ALL profit realised so far
        // (including the still-open month) minus everything already withdrawn. The
        // owner can draw this down at any time, not only the carried-forward cash
        // from closed months. Once every month is closed this equals the old
        // carried-forward figure, so the period-close carry chain is unchanged.
        $carriedTotal = round($openingBalance + $profit - $monthCloseWithdrawn - $adhocWithdrawn, 2);
        $withdrawnTotal = round($monthCloseWithdrawn + $adhocWithdrawn, 2);

        return [
            'months' => $months,
            'revenue' => round($totalRevenue, 2),
            'expense' => round($totalExpense, 2),
            'profit' => $profit,
            'margin' => $totalRevenue > 0 ? round($profit / $totalRevenue * 100, 1) : 0.0,
            'carried_total' => $carriedTotal,
            'withdrawn_total' => $withdrawnTotal,
            'adhoc_withdrawn' => $adhocWithdrawn,
            // Cash the owner can still take out right now (never negative).
            'available_to_withdraw' => max(0.0, $carriedTotal),
            'opening_balance' => round($openingBalance, 2),
            'period' => $period,
            'period_closed' => $periodClosed,
            // The period may be closed only once every begun month is closed, so
            // the carry-forward chain is complete before it gets locked.
            'period_closeable' => ! $periodClosed && $openBegunMonths === 0,
            'open_months' => $openBegunMonths,
        ];
    }

    /**
     * Close a month, recording the owner's decision to withdraw part of the
     * profit or carry it all forward. Only the earliest open month may be closed.
     */
    public function closeMonth(PlatformFiscalPeriod $period, int $year, int $month, float $ownerWithdrawal, ?string $note): void
    {
        $pnl = $this->forPeriod($period);
        $row = collect($pnl['months'])->first(fn ($r) => $r['year'] === $year && $r['month'] === $month);

        abort_unless($row && $row['closeable'], 422, 'This month cannot be closed yet.');

        // Cap at this month's own profit and at the cash the period still has on
        // hand — the latter stops the same profit being withdrawn twice when it
        // was already taken as an ad-hoc withdrawal.
        $cap = max(0.0, min($row['available'], $pnl['available_to_withdraw']));
        $withdrawal = max(0.0, min($ownerWithdrawal, $cap));

        PlatformMonthlyClose::create([
            'year' => $year,
            'month' => $month,
            'net_income' => $row['profit'],
            'owner_withdrawal' => $withdrawal,
            'withdrawal_note' => $withdrawal > 0 ? $note : null,
            'closed_by' => Auth::id(),
        ]);
    }

    /** Reopen the most recently closed month (undoes the close decision). */
    public function reopenMonth(PlatformFiscalPeriod $period, int $year, int $month): void
    {
        $row = $this->monthRow($period, $year, $month);

        abort_unless($row && $row['reopenable'], 422, 'Only the latest closed month can be reopened.');

        PlatformMonthlyClose::where('year', $year)->where('month', $month)->delete();
    }

    /**
     * Take an ad-hoc owner withdrawal against the period's carried-forward cash.
     * Only allowed while the period is open; the amount is clamped to whatever
     * cash is actually available right now.
     */
    public function withdraw(PlatformFiscalPeriod $period, float $amount, ?string $note): void
    {
        abort_if($period->isClosed(), 422, 'Reopen the period before withdrawing.');

        $available = $this->forPeriod($period)['available_to_withdraw'];
        $amount = round(min($amount, $available), 2);

        abort_unless($amount > 0, 422, 'No cash available to withdraw.');

        $period->withdrawals()->create([
            'amount' => $amount,
            'note' => $note,
            'withdrawn_at' => now()->toDateString(),
            'created_by' => Auth::id(),
        ]);
    }

    /** Undo an ad-hoc withdrawal (returns the cash to the carried balance). */
    public function deleteWithdrawal(PlatformWithdrawal $withdrawal): void
    {
        abort_if($withdrawal->period?->isClosed() ?? false, 422, 'Reopen the period to remove a withdrawal.');

        $withdrawal->delete();
    }

    // ============================================================
    // FISCAL PERIOD CRUD
    // ============================================================

    /** All fiscal periods the superadmin has created (most recent first). */
    public function periods()
    {
        return PlatformFiscalPeriod::orderByDesc('start_date')->get();
    }

    /** Create a fiscal period spanning an exact start date to an end date. */
    public function createPeriod(string $name, Carbon $start, Carbon $end, float $openingBalance): PlatformFiscalPeriod
    {
        return PlatformFiscalPeriod::create([
            'name' => $name,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'opening_balance' => $openingBalance,
            'status' => 'open',
        ]);
    }

    /** Rename a period, adjust its date range, or change its opening balance. */
    public function updatePeriod(PlatformFiscalPeriod $period, string $name, Carbon $start, Carbon $end, float $openingBalance): void
    {
        $period->update([
            'name' => $name,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'opening_balance' => $openingBalance,
        ]);
    }

    /** Delete a fiscal period. Month close decisions in its range are left intact. */
    public function deletePeriod(PlatformFiscalPeriod $period): void
    {
        $period->delete();
    }

    /**
     * Close the whole fiscal period. Only allowed once every begun month is
     * closed; snapshots the final carried-forward balance and total withdrawn,
     * then locks the period's months.
     *
     * The closing balance carries forward into the next fiscal period: an
     * existing following period has its opening balance refreshed, otherwise a
     * new period is spun up to continue the chain. Returns that next period so
     * the caller can land the user on it.
     */
    public function closePeriod(PlatformFiscalPeriod $period): PlatformFiscalPeriod
    {
        $pnl = $this->forPeriod($period);

        abort_unless($pnl['period_closeable'], 422, 'Close all open months before closing the period.');

        $period->update([
            'status' => 'closed',
            'closing_balance' => $pnl['carried_total'],
            'withdrawn_total' => $pnl['withdrawn_total'],
            'closed_by' => Auth::id(),
        ]);

        return $this->carryForward($period, $pnl['carried_total']);
    }

    /**
     * Continue the carry-forward chain after a period closes. If a period
     * already follows this one, just refresh its opening cash; otherwise create
     * the next period spanning the same number of months, starting the day after.
     */
    private function carryForward(PlatformFiscalPeriod $period, float $carriedTotal): PlatformFiscalPeriod
    {
        $next = PlatformFiscalPeriod::query()
            ->where('start_date', '>', $period->end_date->toDateString())
            ->orderBy('start_date')
            ->first();

        if ($next) {
            $next->update(['opening_balance' => $carriedTotal]);

            return $next;
        }

        $start = $period->end_date->copy()->addDay();
        $months = max(1, (int) $period->start_date->diffInMonths($period->end_date));
        $end = $start->copy()->addMonths($months)->endOfMonth();

        // Roll the year in the name forward (e.g. "FY 2026" → "FY 2027"); fall
        // back to tagging the start year when the name has no year to bump.
        $name = preg_replace_callback('/\d{4}/', fn ($m) => (string) ($m[0] + 1), $period->name, 1, $count);
        if (! $count) {
            $name = $period->name.' — '.$start->year;
        }

        return PlatformFiscalPeriod::create([
            'name' => $name,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'opening_balance' => $carriedTotal,
            'status' => 'open',
        ]);
    }

    /** Reopen a closed period, unlocking its months. */
    public function reopenPeriod(PlatformFiscalPeriod $period): void
    {
        $period->update([
            'status' => 'open',
            'closing_balance' => 0,
            'withdrawn_total' => 0,
            'closed_by' => null,
        ]);
    }

    /** Find a single month row within a period (or null if it isn't in range). */
    private function monthRow(PlatformFiscalPeriod $period, int $year, int $month): ?array
    {
        foreach ($this->forPeriod($period)['months'] as $row) {
            if ($row['year'] === $year && $row['month'] === $month) {
                return $row;
            }
        }

        return null;
    }
}
