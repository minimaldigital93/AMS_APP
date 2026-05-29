<?php

namespace App\Services\FiscalPeriod;

use App\Models\FiscalPeriods;
use App\Models\MonthlyPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Write-side service for the monthly period lifecycle:
 *
 *   generateForFiscalPeriod()  — create month rows when a fiscal period is opened
 *   closeMonth()               — freeze totals and carry balance forward
 *   reopenMonth()              — undo a close (if the next month is still open)
 *   recalculateBalances()      — cascade carry-forward from opening balance
 *                                across every month (and back into the period)
 *
 * Authorization stays in the controller — this service trusts its inputs.
 */
class MonthlyPeriodManager
{
    public function __construct(private FiscalPeriodFinancialsService $financials) {}

    /**
     * Generate one MonthlyPeriod row for each calendar month touched by the
     * fiscal period. The first month inherits the fiscal period's opening
     * balance; all later months open at 0 (filled in by closeMonth() /
     * recalculateBalances()).
     */
    public function generateForFiscalPeriod(FiscalPeriods $fiscalPeriod): void
    {
        $startDate = Carbon::parse($fiscalPeriod->opening_date)->startOfDay();
        $endDate = Carbon::parse($fiscalPeriod->closing_date)->endOfDay();
        $openingBalance = $fiscalPeriod->opening_balance;
        $isFirst = true;

        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $monthStart = $isFirst ? $startDate->copy() : $current->copy()->startOfMonth();
            $monthEnd = $current->copy()->endOfMonth();
            if ($monthEnd->gt($endDate)) {
                $monthEnd = $endDate->copy();
            }

            MonthlyPeriod::create([
                'fiscal_period_id' => $fiscalPeriod->id,
                'user_id' => $fiscalPeriod->user_id,
                'name' => $monthStart->format('F Y'),
                'month_number' => $monthStart->month,
                'year' => $monthStart->year,
                'start_date' => $monthStart->format('Y-m-d'),
                'end_date' => $monthEnd->format('Y-m-d'),
                'opening_balance' => $isFirst ? $openingBalance : 0,
                'closing_balance' => 0,
                'total_income' => 0,
                'total_expenses' => 0,
                'net_income' => 0,
                'status' => 'open',
            ]);

            $isFirst = false;
            $current->addMonth()->startOfMonth();
        }
    }

    /**
     * Freeze a month's totals from live Accounts data, mark it closed, and
     * carry the closing balance forward to the next open month.
     *
     * An optional owner profit withdrawal (a "draw"/distribution) can be taken
     * at close. Accounting-wise a draw is NOT an expense: it never touches
     * net_income or the Accounts ledger (the income statement stays correct).
     * It only reduces the cash carried forward and the owner's equity, so it is
     * subtracted from the closing balance:
     *
     *     closing_balance = opening_balance + net_income − owner_withdrawal
     *
     * @return array{closing_balance: float, net_income: float, owner_withdrawal: float, next_month: MonthlyPeriod|null}
     */
    public function closeMonth(
        FiscalPeriods $fiscalPeriod,
        MonthlyPeriod $monthlyPeriod,
        float $ownerWithdrawal = 0,
        ?string $withdrawalNote = null,
    ): array {
        $financials = $this->financials->forMonth($fiscalPeriod, $monthlyPeriod);
        $ownerWithdrawal = round(max(0, $ownerWithdrawal), 2);
        $closingBalance = $monthlyPeriod->opening_balance + $financials['net_income'] - $ownerWithdrawal;

        $nextMonth = DB::transaction(function () use ($fiscalPeriod, $monthlyPeriod, $financials, $closingBalance, $ownerWithdrawal, $withdrawalNote) {
            $monthlyPeriod->update([
                'total_income' => $financials['total_income'],
                'total_expenses' => $financials['total_expenses'],
                'net_income' => $financials['net_income'],
                'owner_withdrawal' => $ownerWithdrawal,
                'withdrawal_note' => $ownerWithdrawal > 0 ? $withdrawalNote : null,
                'closing_balance' => $closingBalance,
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            $next = $fiscalPeriod->nextMonthlyPeriod($monthlyPeriod);
            if ($next && $next->isOpen()) {
                $next->update(['opening_balance' => $closingBalance]);
            }

            return $next;
        });

        return [
            'closing_balance' => $closingBalance,
            'net_income' => $financials['net_income'],
            'owner_withdrawal' => $ownerWithdrawal,
            'next_month' => $nextMonth,
        ];
    }

    /**
     * Reopen a closed month. Refuses (returns false) if the next month is
     * already closed — reopening would break the carry-forward chain.
     *
     * Reopening un-forwards the balance: a month only carries its closing
     * balance forward once it is closed, so while it is open the next month
     * has "nothing forwarded yet". We roll the next open month's opening
     * balance back to this month's opening balance (the last firm running
     * total) — it will be re-forwarded when this month is closed again.
     */
    public function reopenMonth(FiscalPeriods $fiscalPeriod, MonthlyPeriod $monthlyPeriod): bool|MonthlyPeriod
    {
        $nextMonth = $fiscalPeriod->nextMonthlyPeriod($monthlyPeriod);
        if ($nextMonth && $nextMonth->isClosed()) {
            return $nextMonth; // return the blocker for the controller to message
        }

        DB::transaction(function () use ($monthlyPeriod, $nextMonth) {
            // Reopening undoes the close, so it also undoes the draw taken at
            // close. The owner re-enters a withdrawal when re-closing the month.
            $monthlyPeriod->update([
                'status' => 'open',
                'closed_at' => null,
                'owner_withdrawal' => 0,
                'withdrawal_note' => null,
            ]);

            // Un-forward: the next month no longer receives this month's
            // (now undone) closing balance.
            if ($nextMonth && $nextMonth->isOpen()) {
                $nextMonth->update(['opening_balance' => $monthlyPeriod->opening_balance]);
            }
        });

        return true;
    }

    /**
     * Cascade-recompute every month's opening/closing balance from the live
     * Accounts ledger, then sync the fiscal period's closing balance with the
     * final carry-forward.
     *
     * @return float the final running balance
     */
    public function recalculateBalances(FiscalPeriods $fiscalPeriod): float
    {
        return DB::transaction(function () use ($fiscalPeriod) {
            $monthlyPeriods = $fiscalPeriod->monthlyPeriods()->orderBy('start_date')->get();
            $running = (float) $fiscalPeriod->opening_balance;

            foreach ($monthlyPeriods as $month) {
                $financials = $this->financials->forMonth($fiscalPeriod, $month);

                // Owner draws reduce carried-forward cash but not net income.
                $withdrawal = (float) $month->owner_withdrawal;

                $month->update([
                    'opening_balance' => $running,
                    'total_income' => $financials['total_income'],
                    'total_expenses' => $financials['total_expenses'],
                    'net_income' => $financials['net_income'],
                    'closing_balance' => $running + $financials['net_income'] - $withdrawal,
                ]);

                $running = $month->closing_balance;
            }

            $fiscalPeriod->update(['closing_balance' => $running]);

            return $running;
        });
    }
}
