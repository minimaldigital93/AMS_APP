<?php

namespace App\Rules;

use App\Models\MonthlyPeriod;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reject a transaction/payment/expense date that falls inside a CLOSED monthly
 * period. A closed month's totals and carried-forward balance are frozen at
 * close; letting a backdated ledger row land inside it silently desyncs the
 * frozen figures from the live Accounts data. The month must be reopened first
 * (which un-freezes it) before historical entries can be added.
 *
 * MonthlyPeriod is account-scoped (BelongsToAccount), so this transparently
 * checks the current account's own months for both admin and supervisor actors.
 */
class NotInClosedMonth implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $date = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return; // the 'date' rule reports the format error
        }

        $closedMonth = MonthlyPeriod::where('status', 'closed')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if ($closedMonth !== null) {
            $fail(__('messages.validation_date_in_closed_month', ['month' => $closedMonth->name]));
        }
    }
}
