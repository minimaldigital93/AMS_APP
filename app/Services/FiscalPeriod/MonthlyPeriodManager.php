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
        $endDate   = Carbon::parse($fiscalPeriod->closing_date)->endOfDay();
        $openingBalance = $fiscalPeriod->opening_balance;
        $isFirst = true;

        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $monthStart = $isFirst ? $startDate->copy() : $current->copy()->startOfMonth();
            $monthEnd   = $current->copy()->endOfMonth();
            if ($monthEnd->gt($endDate)) {
                $monthEnd = $endDate->copy();
            }

            MonthlyPeriod::create([
                'fiscal_period_id' => $fiscalPeriod->id,
                'user_id'          => $fiscalPeriod->user_id,
                'name'             => $monthStart->format('F Y'),
                'month_number'     => $monthStart->month,
                'year'             => $monthStart->year,
                'start_date'       => $monthStart->format('Y-m-d'),
                'end_date'         => $monthEnd->format('Y-m-d'),
                'opening_balance'  => $isFirst ? $openingBalance : 0,
                'closing_balance'  => 0,
                'total_income'     => 0,
                'total_expenses'   => 0,
                'net_income'       => 0,
                'status'           => 'open',
            ]);

            $isFirst = false;
            $current->addMonth()->startOfMonth();
        }
    }

    /**
     * Freeze a month's totals from live Accounts data, mark it closed, and
     * carry the closing balance forward to the next open month.
     *
     * @return array{closing_balance: float, next_month: MonthlyPeriod|null}
     */
    public function closeMonth(FiscalPeriods $fiscalPeriod, MonthlyPeriod $monthlyPeriod): array
    {
        $financials = $this->financials->forMonth($fiscalPeriod, $monthlyPeriod);
        $closingBalance = $monthlyPeriod->opening_balance + $financials['net_income'];

        $nextMonth = DB::transaction(function () use ($fiscalPeriod, $monthlyPeriod, $financials, $closingBalance) {
            $monthlyPeriod->update([
                'total_income'    => $financials['total_income'],
                'total_expenses'  => $financials['total_expenses'],
                'net_income'      => $financials['net_income'],
                'closing_balance' => $closingBalance,
                'status'          => 'closed',
                'closed_at'       => now(),
            ]);

            $next = $fiscalPeriod->nextMonthlyPeriod($monthlyPeriod);
            if ($next && $next->isOpen()) {
                $next->update(['opening_balance' => $closingBalance]);
            }
            return $next;
        });

        return [
            'closing_balance' => $closingBalance,
            'next_month'      => $nextMonth,
        ];
    }

    /**
     * Reopen a closed month. Refuses (returns false) if the next month is
     * already closed — reopening would break the carry-forward chain.
     */
    public function reopenMonth(FiscalPeriods $fiscalPeriod, MonthlyPeriod $monthlyPeriod): bool|MonthlyPeriod
    {
        $nextMonth = $fiscalPeriod->nextMonthlyPeriod($monthlyPeriod);
        if ($nextMonth && $nextMonth->isClosed()) {
            return $nextMonth; // return the blocker for the controller to message
        }

        $monthlyPeriod->update([
            'status'    => 'open',
            'closed_at' => null,
        ]);

        return true;
    }

    /**
     * Cascade-recompute every month's opening/closing balance from the live
     * Accounts ledger, then sync the fiscal period's closing balance with the
     * final carry-forward.
     *
     * @return float  the final running balance
     */
    public function recalculateBalances(FiscalPeriods $fiscalPeriod): float
    {
        return DB::transaction(function () use ($fiscalPeriod) {
            $monthlyPeriods = $fiscalPeriod->monthlyPeriods()->orderBy('start_date')->get();
            $running = (float) $fiscalPeriod->opening_balance;

            foreach ($monthlyPeriods as $month) {
                $financials = $this->financials->forMonth($fiscalPeriod, $month);

                $month->update([
                    'opening_balance' => $running,
                    'total_income'    => $financials['total_income'],
                    'total_expenses'  => $financials['total_expenses'],
                    'net_income'      => $financials['net_income'],
                    'closing_balance' => $running + $financials['net_income'],
                ]);

                $running = $month->closing_balance;
            }

            $fiscalPeriod->update(['closing_balance' => $running]);

            return $running;
        });
    }
}
