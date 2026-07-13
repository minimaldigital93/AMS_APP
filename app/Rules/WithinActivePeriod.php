<?php

namespace App\Rules;

use App\Models\FiscalPeriods;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reject a transaction/payment/expense date outside the ACTIVE fiscal period's
 * range. Ledger rows are stamped with the active period's id, but every
 * windowed report (monthly breakdowns, period totals, exports) filters by the
 * period's opening–closing range — a row dated outside it lands in the books
 * yet appears in no report, silently desyncing the totals.
 *
 * FiscalPeriods is account-scoped (BelongsToAccount): an admin resolves their
 * own open period, a supervisor resolves their admin's. With no open period
 * the rule passes — the fiscal.period middleware already redirects that case.
 */
class WithinActivePeriod implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $date = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return; // the 'date' rule reports the format error
        }

        $period = FiscalPeriods::where('status', 'open')
            ->orderBy('opening_date', 'desc')
            ->first();

        if ($period === null) {
            return;
        }

        $start = Carbon::parse($period->opening_date)->startOfDay();
        $end = Carbon::parse($period->closing_date)->endOfDay();

        if ($date->lt($start) || $date->gt($end)) {
            $fail(__('messages.validation_date_outside_period', [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ]));
        }
    }
}
