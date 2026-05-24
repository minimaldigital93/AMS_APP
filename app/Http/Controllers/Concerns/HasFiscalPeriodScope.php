<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Apartments;
use App\Models\FiscalPeriods;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared fiscal-period helpers for admin/supervisor revenue-expense controllers.
 *
 * The only thing that differs between roles is *which* fiscal periods are visible:
 *   - Admin reads its own periods (where user_id = Auth::id()).
 *   - Supervisor reads the admin's periods (whereHas('user', role:admin)).
 *
 * Each controller supplies that scope via fiscalPeriodsQuery(); everything else
 * (active-period lookup, period switcher, month dropdown, date clamping) is shared.
 */
trait HasFiscalPeriodScope
{
    /**
     * Base query for fiscal periods the current role is allowed to read.
     * Implementing controllers supply the user filter.
     */
    abstract protected function fiscalPeriodsQuery(): Builder;

    /**
     * Active (most recent open) fiscal period in scope, or null if none.
     */
    protected function getActiveFiscalPeriod(): ?FiscalPeriods
    {
        return $this->fiscalPeriodsQuery()
            ->where('status', 'open')
            ->orderBy('opening_date', 'desc')
            ->first();
    }

    /**
     * Resolve a specific period by ID (if visible), else fall back to the active one.
     */
    protected function resolveActivePeriod(?int $periodId = null): ?FiscalPeriods
    {
        if ($periodId) {
            $period = $this->fiscalPeriodsQuery()->where('id', $periodId)->first();
            if ($period) {
                return $period;
            }
        }

        return $this->getActiveFiscalPeriod();
    }

    /**
     * All fiscal periods in scope, newest first — used by the period-switcher dropdown.
     */
    protected function getAllFiscalPeriods(): Collection
    {
        return $this->fiscalPeriodsQuery()
            ->orderBy('opening_date', 'desc')
            ->get();
    }

    /**
     * All apartments visible to the current role.
     *
     * Per CLAUDE.md: supervisors see all admin-wide data; `apartments.supervisor_id`
     * is only an "assigned by" tag, not an access filter.
     */
    protected function scopeApartments(): Builder
    {
        return Apartments::query();
    }

    /**
     * Months covered by the given fiscal period, for the dropdown filter.
     *
     * @return list<array{month: int, year: int, label: string}>
     */
    protected function buildPeriodMonths(FiscalPeriods $period): array
    {
        $months = [];
        $cursor = Carbon::parse($period->opening_date)->startOfMonth();
        $end = Carbon::parse($period->closing_date)->endOfMonth();

        while ($cursor->lte($end)) {
            $months[] = [
                'month' => $cursor->month,
                'year'  => $cursor->year,
                'label' => $cursor->format('F Y'),
            ];
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Clamp a requested (month, year) to the fiscal period's start/end bounds.
     * When month/year is null, returns the whole-period range.
     *
     * @return array{start: \Carbon\Carbon|string, end: \Carbon\Carbon|string}
     */
    protected function getFilteredDateRange(FiscalPeriods $period, ?int $month, ?int $year): array
    {
        if ($month && $year) {
            $filterStart = Carbon::create($year, $month, 1)->startOfMonth();
            $filterEnd   = $filterStart->copy()->endOfMonth();

            return [
                'start' => $filterStart->lt($period->opening_date) ? Carbon::parse($period->opening_date) : $filterStart,
                'end'   => $filterEnd->gt($period->closing_date) ? Carbon::parse($period->closing_date) : $filterEnd,
            ];
        }

        return [
            'start' => $period->opening_date,
            'end'   => $period->closing_date,
        ];
    }
}
