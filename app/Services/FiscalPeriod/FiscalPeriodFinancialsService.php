<?php

namespace App\Services\FiscalPeriod;

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\MonthlyPeriod;
use Carbon\Carbon;

/**
 * Read-only financial calculations for a fiscal period or one of its monthly
 * sub-periods. Source of truth is the Accounts ledger — every paid Payment
 * creates a matching Accounts.income row, so reading from Accounts gives a
 * complete picture without double-counting payments + accruals.
 *
 * Returned shape is stable so views and exports (income statement, cash flow,
 * monthly PDF, etc.) can all consume the same keys.
 */
class FiscalPeriodFinancialsService
{
    /**
     * Totals for a single monthly period. Pass $propertyId to scope the figures
     * to one property's books (null = consolidated across all properties).
     */
    public function forMonth(FiscalPeriods $fiscalPeriod, MonthlyPeriod $month, ?int $propertyId = null): array
    {
        return $this->forRange(
            $fiscalPeriod,
            Carbon::parse($month->start_date),
            Carbon::parse($month->end_date),
            $propertyId
        );
    }

    /**
     * Totals for the whole fiscal period. Pass $propertyId to scope the figures
     * to one property's books (null = consolidated across all properties).
     */
    public function forPeriod(FiscalPeriods $fiscalPeriod, ?int $propertyId = null): array
    {
        return $this->forRange(
            $fiscalPeriod,
            Carbon::parse($fiscalPeriod->opening_date),
            Carbon::parse($fiscalPeriod->closing_date),
            $propertyId
        );
    }

    /**
     * Aggregate income + expense from the Accounts ledger for the given range.
     *
     * When $propertyId is given the ledger is scoped to that property (plus any
     * account-wide rows with a null property_id, per the scopeForProperty
     * convention); null aggregates every property.
     */
    public function forRange(FiscalPeriods $fiscalPeriod, Carbon $start, Carbon $end, ?int $propertyId = null): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $incomeRecords = $fiscalPeriod->accounts()
            ->where('account_type', Accounts::TYPE_INCOME)
            ->forProperty($propertyId)
            ->whereBetween('transaction_date', [$start, $end])
            ->get();

        $expenseRecords = $fiscalPeriod->accounts()
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->forProperty($propertyId)
            ->whereBetween('transaction_date', [$start, $end])
            ->get();

        $rentIncome = $incomeRecords->where('category', Accounts::CAT_RENT_INCOME)->sum('amount');
        $lateFees = $incomeRecords->where('category', Accounts::CAT_LATE_FEE_INCOME)->sum('amount');
        $otherIncome = $incomeRecords->whereIn('category', [
            Accounts::CAT_UTILITY_INCOME,
            Accounts::CAT_OTHER_INCOME,
            Accounts::CAT_DEPOSIT_INCOME,
        ])->sum('amount');
        $totalIncome = $incomeRecords->sum('amount');

        $utilityExpensesTotal = $expenseRecords->where('category', Accounts::CAT_UTILITIES_EXPENSE)->sum('amount');
        $fixedExpenses = $expenseRecords->where('category', '!=', Accounts::CAT_UTILITIES_EXPENSE)->sum('amount');
        $totalExpenses = $expenseRecords->sum('amount');

        $utilityExpensesByCategory = $expenseRecords
            ->where('category', Accounts::CAT_UTILITIES_EXPENSE)
            ->groupBy('description')
            ->map(fn ($items) => round($items->sum('amount'), 2))
            ->toArray();
        if (empty($utilityExpensesByCategory) && $utilityExpensesTotal > 0) {
            $utilityExpensesByCategory = ['utilities' => round($utilityExpensesTotal, 2)];
        }

        $paymentCount = $incomeRecords->whereNotNull('payment_id')->pluck('payment_id')->unique()->count();
        $netIncome = $totalIncome - $totalExpenses;

        return [
            'rent_income' => round($rentIncome, 2),
            'late_fees' => round($lateFees, 2),
            'other_income' => round($otherIncome, 2),
            'total_income' => round($totalIncome, 2),
            'utility_expenses' => $utilityExpensesByCategory,
            'total_util_expenses' => round($utilityExpensesTotal, 2),
            'fixed_expenses' => round($fixedExpenses, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_income' => round($netIncome, 2),
            'is_profitable' => $netIncome >= 0,
            'payment_count' => $paymentCount,
        ];
    }
}
