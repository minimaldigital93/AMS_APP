<?php

namespace App\Services\Platform;

use App\Models\KhqrPayment;
use App\Models\PlatformExpense;
use App\Models\PlatformMonthlyClose;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Computes the platform (SaaS) profit & loss for the superadmin: subscription
 * revenue (confirmed KHQR payments) minus recorded platform expenses, broken
 * down by month within a year and rolled up to a yearly total.
 *
 * Revenue mirrors the superadmin dashboard's `platformRevenue` source so the
 * two pages never disagree — confirmed subscription payments only.
 */
class PlatformFinanceService
{
    /**
     * Full P&L for a given calendar year.
     *
     * Each month also carries its close state: whether it is closed, the owner
     * withdrawal taken, and the running carried-forward cash balance (opening +
     * profit − withdrawal), so the superadmin can close months and decide to
     * withdraw or carry the profit forward.
     *
     * @return array{
     *   year:int,
     *   months:array<int,array{label:string,month:int,revenue:float,expense:float,profit:float,
     *     closed:bool,opening:float,owner_withdrawal:float,carried:?float,withdrawal_note:?string,
     *     available:float,closeable:bool,reopenable:bool}>,
     *   revenue:float, expense:float, profit:float, margin:float,
     *   carried_total:float, withdrawn_total:float
     * }
     */
    public function forYear(int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfYear();
        $end = $start->copy()->endOfYear();

        // Revenue per month — confirmed subscription payments.
        $revenueByMonth = array_fill(1, 12, 0.0);
        KhqrPayment::query()
            ->whereNotNull('subscription_id')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->get(['amount', 'paid_at'])
            ->each(function ($p) use (&$revenueByMonth) {
                $revenueByMonth[(int) Carbon::parse($p->paid_at)->month] += (float) $p->amount;
            });

        // Expense per month — recorded platform expenses.
        $expenseByMonth = array_fill(1, 12, 0.0);
        PlatformExpense::query()
            ->whereBetween('spent_at', [$start->toDateString(), $end->toDateString()])
            ->get(['amount', 'spent_at'])
            ->each(function ($e) use (&$expenseByMonth) {
                $expenseByMonth[(int) Carbon::parse($e->spent_at)->month] += (float) $e->amount;
            });

        // Close decisions keyed by month (a row exists only for closed months).
        $closes = PlatformMonthlyClose::query()
            ->where('year', $year)
            ->get()
            ->keyBy('month');

        // The earliest open month is the only one that may be closed, and the
        // latest closed month the only one that may be reopened — this keeps the
        // carry-forward chain unambiguous (mirrors the admin MonthlyPeriod flow).
        $firstOpenMonth = null;
        $lastClosedMonth = null;
        for ($m = 1; $m <= 12; $m++) {
            if ($closes->has($m)) {
                $lastClosedMonth = $m;
            } elseif ($firstOpenMonth === null) {
                $firstOpenMonth = $m;
            }
        }

        $now = now();
        $months = [];
        $totalRevenue = $totalExpense = 0.0;
        $carry = 0.0;          // running carried-forward cash balance
        $withdrawnTotal = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $rev = round($revenueByMonth[$m], 2);
            $exp = round($expenseByMonth[$m], 2);
            $profitM = round($rev - $exp, 2);
            $totalRevenue += $rev;
            $totalExpense += $exp;

            $opening = round($carry, 2);
            $available = round($opening + $profitM, 2);
            $close = $closes->get($m);
            $closed = $close !== null;
            $withdrawal = $closed ? (float) $close->owner_withdrawal : 0.0;
            $carried = null;
            if ($closed) {
                $carried = round($opening + $profitM - $withdrawal, 2);
                $carry = $carried;
                $withdrawnTotal += $withdrawal;
            }

            // A month is closeable once it has begun (no closing the future).
            $hasBegun = Carbon::create($year, $m, 1)->lessThanOrEqualTo($now);

            $months[$m] = [
                'label' => Carbon::create($year, $m, 1)->format('M'),
                'month' => $m,
                'revenue' => $rev,
                'expense' => $exp,
                'profit' => $profitM,
                'closed' => $closed,
                'opening' => $opening,
                'owner_withdrawal' => round($withdrawal, 2),
                'carried' => $carried,
                'withdrawal_note' => $close?->withdrawal_note,
                'available' => $available,
                'closeable' => ! $closed && $m === $firstOpenMonth && $hasBegun,
                'reopenable' => $closed && $m === $lastClosedMonth,
            ];
        }

        $profit = round($totalRevenue - $totalExpense, 2);

        return [
            'year' => $year,
            'months' => $months,
            'revenue' => round($totalRevenue, 2),
            'expense' => round($totalExpense, 2),
            'profit' => $profit,
            'margin' => $totalRevenue > 0 ? round($profit / $totalRevenue * 100, 1) : 0.0,
            'carried_total' => round($carry, 2),
            'withdrawn_total' => round($withdrawnTotal, 2),
        ];
    }

    /**
     * Close a month, recording the owner's decision to withdraw part of the
     * profit or carry it all forward. Only the earliest open month may be closed.
     */
    public function closeMonth(int $year, int $month, float $ownerWithdrawal, ?string $note): void
    {
        $pnl = $this->forYear($year);
        $row = $pnl['months'][$month] ?? null;

        abort_unless($row && $row['closeable'], 422, 'This month cannot be closed yet.');

        $withdrawal = max(0.0, min($ownerWithdrawal, max(0.0, $row['available'])));

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
    public function reopenMonth(int $year, int $month): void
    {
        $pnl = $this->forYear($year);
        $row = $pnl['months'][$month] ?? null;

        abort_unless($row && $row['reopenable'], 422, 'Only the latest closed month can be reopened.');

        PlatformMonthlyClose::where('year', $year)->where('month', $month)->delete();
    }

    /** Distinct years that have either revenue or expense activity (newest first). */
    public function activeYears(): array
    {
        $years = collect();

        KhqrPayment::query()
            ->whereNotNull('subscription_id')->where('status', 'paid')->whereNotNull('paid_at')
            ->get(['paid_at'])
            ->each(fn ($p) => $years->push((int) Carbon::parse($p->paid_at)->year));

        PlatformExpense::query()->get(['spent_at'])
            ->each(fn ($e) => $years->push((int) Carbon::parse($e->spent_at)->year));

        $years->push((int) now()->year);

        return $years->unique()->sortDesc()->values()->all();
    }
}
