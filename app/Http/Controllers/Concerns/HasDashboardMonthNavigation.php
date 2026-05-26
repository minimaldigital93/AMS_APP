<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FiscalPeriods;
use Carbon\Carbon;

/**
 * Shared month/date-range resolution for dashboard-style screens that show
 * "this month" data with previous/next navigation, scoped to the active
 * fiscal period.
 *
 * Used together with HasFiscalPeriodScope (which supplies buildPeriodMonths).
 * All four helpers below are identical across admin and supervisor — they
 * deal in pure date math and contain no role-specific logic.
 */
trait HasDashboardMonthNavigation
{
    /**
     * Resolve which month the dashboard should show, given the user's
     * ?month=&year= query (if any) and the active fiscal period bounds.
     *
     * Priority:
     *   1. Requested month/year, if it falls inside the period.
     *   2. Current calendar month, if it falls inside the period.
     *   3. First month of the period.
     *   4. Requested month/year (no period at all).
     *   5. Current month (no period and no request).
     */
    protected function resolveSelectedMonth(?FiscalPeriods $activePeriod, ?int $month, ?int $year): Carbon
    {
        if ($activePeriod) {
            $periodMonths = $this->buildPeriodMonths($activePeriod);

            if ($this->isValidMonth($month, $year)) {
                foreach ($periodMonths as $pm) {
                    if ($pm['month'] === $month && $pm['year'] === $year) {
                        return Carbon::create($year, $month, 1)->startOfMonth();
                    }
                }
            }

            foreach ($periodMonths as $pm) {
                if ($pm['month'] === now()->month && $pm['year'] === now()->year) {
                    return now()->startOfMonth();
                }
            }

            if (!empty($periodMonths)) {
                return Carbon::create($periodMonths[0]['year'], $periodMonths[0]['month'], 1)->startOfMonth();
            }
        }

        if ($this->isValidMonth($month, $year)) {
            return Carbon::create($year, $month, 1)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    /**
     * Date range the dashboard should query.
     *
     * In "full period" mode (?view=all), returns the entire fiscal period.
     * Otherwise returns the bounds of the selected month (or current month
     * as a fallback).
     *
     * @return array{start: Carbon, end: Carbon}
     */
    protected function resolveDateRange(?FiscalPeriods $activePeriod, ?Carbon $selectedMonth, bool $isFullPeriod): array
    {
        if ($activePeriod && $isFullPeriod) {
            return [
                'start' => Carbon::parse($activePeriod->opening_date)->startOfDay(),
                'end'   => Carbon::parse($activePeriod->closing_date)->endOfDay(),
            ];
        }

        $month = $selectedMonth ?: now()->startOfMonth();

        return [
            'start' => $month->copy()->startOfMonth(),
            'end'   => $month->copy()->endOfMonth(),
        ];
    }

    /**
     * The "default" month to highlight when no month is selected — the
     * current calendar month if it's in the period, otherwise the first
     * month of the period (or simply the current month if there's no
     * period at all).
     */
    protected function resolveDisplayMonth(?FiscalPeriods $activePeriod, array $periodMonths): Carbon
    {
        if ($activePeriod) {
            foreach ($periodMonths as $pm) {
                if ($pm['month'] === now()->month && $pm['year'] === now()->year) {
                    return now()->startOfMonth();
                }
            }

            if (!empty($periodMonths)) {
                return Carbon::create($periodMonths[0]['year'], $periodMonths[0]['month'], 1)->startOfMonth();
            }
        }

        return now()->startOfMonth();
    }

    /**
     * Build the prev/next month navigation bundle for the dashboard header.
     * Returns null for previous/next when the bound is hit (or full-period mode).
     *
     * @return array{
     *   previousMonth: array|null,
     *   nextMonth: array|null,
     *   isCurrentMonth: bool,
     *   isFullPeriod: bool,
     *   currentMonthInPeriod: array|null
     * }
     */
    protected function getMonthNavigation(array $periodMonths, Carbon $selectedMonth, bool $isFullPeriod): array
    {
        $currentIndex = null;
        foreach ($periodMonths as $index => $pm) {
            if ($pm['month'] === $selectedMonth->month && $pm['year'] === $selectedMonth->year) {
                $currentIndex = $index;
                break;
            }
        }

        $hasPrev = !$isFullPeriod && $currentIndex !== null && $currentIndex > 0;
        $hasNext = !$isFullPeriod && $currentIndex !== null && $currentIndex < count($periodMonths) - 1;

        return [
            'previousMonth'        => $hasPrev ? $periodMonths[$currentIndex - 1] : null,
            'nextMonth'            => $hasNext ? $periodMonths[$currentIndex + 1] : null,
            'isCurrentMonth'       => $selectedMonth->month === now()->month && $selectedMonth->year === now()->year,
            'isFullPeriod'         => $isFullPeriod,
            'currentMonthInPeriod' => collect($periodMonths)->first(
                fn ($pm) => $pm['month'] === now()->month && $pm['year'] === now()->year
            ),
        ];
    }

    private function isValidMonth(?int $month, ?int $year): bool
    {
        return $month !== null && $year !== null && $month >= 1 && $month <= 12;
    }
}
