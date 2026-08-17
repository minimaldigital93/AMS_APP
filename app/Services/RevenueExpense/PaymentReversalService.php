<?php

namespace App\Services\RevenueExpense;

use App\Models\Accounts;
use App\Models\MonthlyPeriod;
use App\Models\Payments;
use App\Models\Utilities;
use App\Services\Audit\AuditLogger;
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
 * Only the CURRENT month can be undone. A reversal deletes ledger rows, so
 * reversing a payment booked in an earlier month would silently restate a month
 * whose revenue has already been read, reported and acted on. The mistake this
 * feature exists for is a fresh one — money recorded on the wrong tenant or the
 * wrong line today. Anything older is corrected as an adjustment in the open
 * month, not by editing history.
 *
 * Money that has been closed off is never restated either: a payment whose
 * ledger rows sit in a closed fiscal period, or in a closed monthly period,
 * cannot be reversed — the same rule LeaseSyncService follows for the deposit
 * row. Fix those by reopening the month first.
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

    public const REASON_PAST_MONTH = 'past_month';

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

        // Broadest rule first: an older month is out of reach whatever its
        // period status, so "reopen the month" would be the wrong advice.
        if (! $this->bookedThisMonth($payment, $rows)) {
            return self::REASON_PAST_MONTH;
        }

        foreach ($rows as $row) {
            if ($row->fiscalPeriod && $row->fiscalPeriod->status !== 'open') {
                return self::REASON_CLOSED_PERIOD;
            }

            if ($this->monthIsClosed($row)) {
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
     * Whether this payment's money still sits in the month we are in now.
     *
     * The ledger row's transaction_date is the authority — income is recognised
     * when it is received, so that date is the month a reversal would restate.
     * (It is not always the billed month: July's rent collected on Aug 3 is
     * anchored to July on the Payments row but booked as August income, and
     * undoing that mistake in August is exactly the case this feature is for.)
     * A payment carrying no ledger rows falls back to its own paid_at; one with
     * neither cannot be placed in time at all, so it is left alone.
     *
     * @param  \Illuminate\Support\Collection<int, Accounts>  $rows
     */
    private function bookedThisMonth(Payments $payment, Collection $rows): bool
    {
        $dates = $rows->pluck('transaction_date')->filter();

        if ($dates->isEmpty()) {
            $dates = collect([$payment->paid_at])->filter();
        }

        return $dates->isNotEmpty()
            && $dates->every(fn ($date) => $date->isSameMonth(now()));
    }

    /**
     * Whether the monthly period holding a ledger row has been closed. A month
     * with no MonthlyPeriod row at all counts as open — not every account
     * generates them.
     */
    private function monthIsClosed(Accounts $row): bool
    {
        if (! $row->transaction_date || ! $row->fiscal_period_id) {
            return false;
        }

        $month = MonthlyPeriod::where('fiscal_period_id', $row->fiscal_period_id)
            ->forMonth($row->transaction_date->month, $row->transaction_date->year)
            ->first();

        return $month !== null && ! $month->isOpen();
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
