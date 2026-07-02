<?php

namespace App\Enums;

/**
 * Lifecycle of a single payment attempt (a `khqr_payments` row).
 *
 * Stored as a plain VARCHAR in the DB (see the status-to-string migration) and
 * validated through this enum + transitionTo() on the model — NOT a DB enum, so
 * a new state never needs a schema change and a typo can't silently truncate
 * under MySQL strict mode (the bug that previously broke manual `rejected`).
 *
 *   pending          tx row created, no QR minted yet
 *   qr_generated     gateway returned a QR (or manual QR/bank shown) — awaiting payer
 *   waiting_payment  payer opened checkout and the client is polling for the result
 *   paid             confirmed by webhook/poll (amount + currency matched)
 *   failed           gateway never returned a usable QR (mint error)
 *   expired          QR lifetime passed with no payment
 *   cancelled         abandoned by the user/admin before paying
 *   refunded         money returned to the payer (super admin, out-of-band)
 *   rejected         manual channel only: landlord confirms the money never arrived
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case QrGenerated = 'qr_generated';
    case WaitingPayment = 'waiting_payment';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Rejected = 'rejected';

    /** States where the payment is still in flight and may still transition to Paid. */
    public static function open(): array
    {
        return [self::Pending, self::QrGenerated, self::WaitingPayment];
    }

    /** Open states as raw strings — for `whereIn('status', ...)` queries. */
    public static function openValues(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::open());
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /**
     * Legal forward transitions. Anything not listed throws in
     * KhqrPayment::transitionTo() so an out-of-order webhook/poll/cron can never
     * resurrect a terminal row (e.g. re-pay a refunded one).
     */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Pending => in_array($to, [self::QrGenerated, self::WaitingPayment, self::Paid, self::Failed, self::Expired, self::Cancelled], true),
            self::QrGenerated => in_array($to, [self::WaitingPayment, self::Paid, self::Expired, self::Cancelled, self::Rejected], true),
            self::WaitingPayment => in_array($to, [self::Paid, self::Expired, self::Cancelled, self::Rejected], true),
            self::Paid => $to === self::Refunded,
            // Failed / Expired / Cancelled / Refunded / Rejected are terminal.
            default => false,
        };
    }
}
