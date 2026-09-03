<?php

namespace App\Services\RevenueExpense;

use App\Models\Accounts;
use App\Models\MonthlyPeriod;
use App\Models\Payments;
use App\Models\Utilities;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Undo a payment that was recorded by mistake.
 *
 * Nothing about rent is stored as an invoice — every status on the collection
 * page, the tenant index badge and the dashboard tiles is derived from the
 * `Payments` row and the `utilities.paid_status` flag. So reversing a payment
 * is exactly: drop the payment row, drop the ledger rows it booked, and put the
 * charge rows it settled back to unpaid. The statuses walk backwards on their
 * own — a full "Paid" row falls to "Rent Paid" when the charges payment goes,
 * and to "Pending"/"Overdue" when the rent payment goes.
 *
 * CLOSING THE MONTH IS THE DEADLINE — nothing else is.
 *
 * A reversal deletes ledger rows, so the only thing it must never do is restate
 * money that has been read, reported and carried forward. That is precisely
 * what closing a month (or a fiscal period) means here: `closeMonth()` freezes
 * the month's totals and forwards its closing balance to the next month, so
 * from that moment the live `Accounts` rows are no longer free to move. While
 * the month is still open nothing has been frozen, so a mistake found three
 * weeks later is corrected the same way one found the same afternoon is.
 *
 * This used to also refuse anything outside the current calendar month, on the
 * reasoning that an older month's revenue had already been acted on. But the
 * app already has a first-class answer to "has this month been acted on?" — the
 * monthly period's status — and the calendar rule contradicted it: an account
 * that had not closed July yet still could not fix July's mis-keyed rent on
 * Aug 1, and was told to book an adjustment against a month it had every right
 * to correct. The rule is now the close, and only the close. Reopen the month
 * (`MonthlyPeriodManager::reopenMonth()`) to reach anything past it.
 *
 * The Payments row is SOFT-deleted (it stays in the table as history); the
 * Accounts rows are removed outright, the way every other ledger-undo path
 * here works (removeTenantCharge, deleteOtherExpense, deleteBusinessExpense) —
 * income that was never received must not sit in the books.
 */
class PaymentReversalService
{
    /** Reason codes returned by blockReason(). */
    public const REASON_NOT_PAID = 'not_paid';

    public const REASON_CLOSED_PERIOD = 'closed_period';

    public const REASON_CLOSED_MONTH = 'closed_month';

    public const REASON_CHARGES_UNMATCHED = 'charges_unmatched';

    public function __construct(private AuditLogger $audit) {}

    /**
     * Why this payment cannot be reversed, or null when it can be.
     */
    public function blockReason(Payments $payment): ?string
    {
        if ($payment->payment_status !== 'paid') {
            return self::REASON_NOT_PAID;
        }

        $rows = $this->ledgerRows($payment);

        // Broadest rule first: a closed fiscal period takes its months with it,
        // so "reopen the month" would be the wrong advice.
        foreach ($rows as $row) {
            if ($row->fiscalPeriod && $row->fiscalPeriod->status !== 'open') {
                return self::REASON_CLOSED_PERIOD;
            }
        }

        foreach ($this->bookedMonths($payment, $rows) as $booked) {
            if ($this->monthIsClosed($booked['date'], $booked['fiscal_period_id'])) {
                return self::REASON_CLOSED_MONTH;
            }
        }

        if ($payment->payment_type === 'utilities' && $this->settledCharges($payment) === null) {
            return self::REASON_CHARGES_UNMATCHED;
        }

        return null;
    }

    public function canReverse(Payments $payment): bool
    {
        return $this->blockReason($payment) === null;
    }

    /**
     * Reverse the payment. Returns the reason code when it was refused, so the
     * caller can say why — the check is repeated here because the button that
     * posted may have been rendered before the month was closed.
     *
     * @return array{reversed: bool, reason: ?string, amount: float, charges: int}
     */
    public function reverse(Payments $payment): array
    {
        $reason = $this->blockReason($payment);

        if ($reason !== null) {
            return ['reversed' => false, 'reason' => $reason, 'amount' => 0.0, 'charges' => 0];
        }

        $amount = (float) $payment->amount + (float) $payment->late_fee;

        $charges = DB::transaction(function () use ($payment) {
            $charges = $this->settledCharges($payment) ?? collect();

            foreach ($charges as $charge) {
                $charge->update(['paid_status' => false, 'paid_at' => null]);
            }

            // Rent, late fee and the utility/other income split all hang off
            // payment_id — one delete clears every row this payment booked.
            Accounts::where('payment_id', $payment->id)->delete();

            $payment->delete();

            return $charges;
        });

        $this->audit->record('payment.reversed', $payment, [
            'rental_id' => $payment->rental_id,
            'payment_type' => $payment->payment_type,
            'payment_method' => $payment->payment_method,
            'amount' => (float) $payment->amount,
            'late_fee' => (float) $payment->late_fee,
            'paid_at' => $payment->paid_at?->toDateTimeString(),
            'charges_unsettled' => $charges->pluck('id')->all(),
        ]);

        return [
            'reversed' => true,
            'reason' => null,
            'amount' => round($amount, 2),
            'charges' => $charges->count(),
        ];
    }

    /**
     * The ledger rows this payment booked, fiscal period eager-loaded.
     *
     * @return \Illuminate\Support\Collection<int, Accounts>
     */
    private function ledgerRows(Payments $payment): Collection
    {
        return Accounts::with('fiscalPeriod')->where('payment_id', $payment->id)->get();
    }

    /**
     * Every month this payment's money sits in — the months a reversal would
     * restate, each with the fiscal period that booked it.
     *
     * The ledger row's transaction_date is the authority: income is recognised
     * when it is received, so that date is the month whose totals a reversal
     * moves. (It is not always the billed month: July's rent collected on Aug 3
     * is anchored to July on the Payments row but booked as August income, and
     * it is August's close that must let go of it.) A payment carrying no
     * ledger rows has nothing in the books to restate, but it still flips a
     * bill's status, so it is placed by its own paid_at rather than waved
     * through. One that can be placed nowhere at all is left alone.
     *
     * @param  \Illuminate\Support\Collection<int, Accounts>  $rows
     * @return \Illuminate\Support\Collection<int, array{date: CarbonInterface, fiscal_period_id: ?int}>
     */
    private function bookedMonths(Payments $payment, Collection $rows): Collection
    {
        $booked = $rows
            ->filter(fn (Accounts $row) => $row->transaction_date !== null)
            ->map(fn (Accounts $row) => [
                'date' => $row->transaction_date,
                'fiscal_period_id' => $row->fiscal_period_id,
            ])
            ->values();

        if ($booked->isNotEmpty() || ! $payment->paid_at) {
            return $booked;
        }

        return collect([['date' => $payment->paid_at, 'fiscal_period_id' => null]]);
    }

    /**
     * Whether the monthly period covering a booked date has been closed (or
     * locked). A month with no MonthlyPeriod row at all counts as open — not
     * every account generates them, and an account that never closes a month
     * never loses the ability to correct one.
     *
     * The fiscal period narrows the lookup when the ledger row names one; a row
     * without one still gets checked against the account's own months, since
     * this is the only guard left standing between a reversal and frozen money.
     */
    private function monthIsClosed(?CarbonInterface $date, ?int $fiscalPeriodId): bool
    {
        if (! $date) {
            return false;
        }

        $query = MonthlyPeriod::forMonth($date->month, $date->year);

        if ($fiscalPeriodId) {
            $query->where('fiscal_period_id', $fiscalPeriodId);
        }

        return $query->get()->contains(fn (MonthlyPeriod $month) => ! $month->isOpen());
    }

    /**
     * The charge rows this payment settled, or null when they cannot be told
     * apart from someone else's.
     *
     * Utilities carry no payment_id — settleUtilityRows() stamps their paid_at
     * from the same date as the Payments row, so that timestamp is the join
     * (the same one printReceipt() uses). An empty set is a legitimate answer:
     * a hand-recorded utilities payment settles no charge rows. A non-empty set
     * whose total doesn't reconcile to the payment means two batches share the
     * timestamp — un-settling then would free charges this payment never paid,
     * so the reversal is refused instead of guessed.
     *
     * @return \Illuminate\Support\Collection<int, Utilities>|null
     */
    private function settledCharges(Payments $payment): ?Collection
    {
        if ($payment->payment_type !== 'utilities' || ! $payment->paid_at) {
            return collect();
        }

        $rows = Utilities::where('rental_id', $payment->rental_id)
            ->where('paid_status', true)
            ->where('paid_at', $payment->paid_at)
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        return abs($rows->sum('charge_amount') - (float) $payment->amount) < 0.01
            ? $rows
            : null;
    }
}
