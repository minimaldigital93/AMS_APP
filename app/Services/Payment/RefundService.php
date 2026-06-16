<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\KhqrPayment;
use App\Models\Refund;
use App\Models\Subscription;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Records a refund against a paid payment. KHQR has no programmatic refund, so
 * the super admin performs the bank transfer out-of-band and then records it
 * here; this flips the payment to REFUNDED (idempotent under a lock), optionally
 * revokes subscription access, and writes an audit entry.
 */
class RefundService
{
    public function __construct(private AuditLogger $audit) {}

    public function record(
        KhqrPayment $payment,
        float $amount,
        string $reason,
        ?string $providerRef = null,
        bool $revokeAccess = false,
        ?Authenticatable $actor = null,
    ): Refund {
        if (! $payment->isPaid()) {
            throw new \RuntimeException('Only a paid payment can be refunded.');
        }

        if ($amount <= 0 || $amount - (float) $payment->amount > 0.01) {
            throw new \InvalidArgumentException('Refund amount must be greater than 0 and not exceed the captured amount.');
        }

        return DB::transaction(function () use ($payment, $amount, $reason, $providerRef, $revokeAccess, $actor) {
            // Lock so two concurrent refund clicks can't both flip the row.
            $locked = KhqrPayment::whereKey($payment->getKey())->lockForUpdate()->first();
            if (! $locked || $locked->statusEnum() !== PaymentStatus::Paid) {
                throw new \RuntimeException('Payment is not in a refundable state.');
            }

            $refund = Refund::create([
                'khqr_payment_id' => $locked->id,
                'subscription_id' => $locked->subscription_id,
                'amount' => $amount,
                'currency' => $locked->currency,
                'reason' => $reason,
                'status' => Refund::STATUS_COMPLETED,
                'initiated_by' => $actor?->getAuthIdentifier(),
                'provider_ref' => $providerRef,
                'requested_at' => now(),
                'completed_at' => now(),
            ]);

            $locked->transitionTo(PaymentStatus::Refunded);
            $locked->save();

            if ($revokeAccess && $locked->subscription_id) {
                $subscription = Subscription::find($locked->subscription_id);
                $subscription?->forceFill([
                    'status' => SubscriptionStatus::Cancelled->value,
                    'cancelled_at' => now(),
                    'cancel_reason' => 'refund',
                    'expires_at' => now(),
                ])->save();
            }

            $this->audit->record('payment.refunded', $locked, [
                'amount' => $amount,
                'currency' => $locked->currency,
                'reason' => $reason,
                'provider_ref' => $providerRef,
                'revoked_access' => $revokeAccess,
            ], $actor);

            return $refund;
        });
    }
}
