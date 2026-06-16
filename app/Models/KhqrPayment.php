<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single KHQRPay dynamic-QR payment attempt.
 *
 * Holds the full checkout context (`checkout_payload`) so the booked payment
 * can be replayed server-side once Bakong confirms — see KhqrPaymentService.
 */
class KhqrPayment extends Model
{
    protected $fillable = [
        'transaction_id',
        'public_token',
        'provider',
        'rental_id',
        'subscription_id',
        'fiscal_period_id',
        'user_id',
        'amount',
        'currency',
        'status',
        'settlement_target',
        'channel',
        'checkout_payload',
        'qr_url',
        'provider_ref',
        'paid_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'checkout_payload' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every row gets an unguessable URL token, regardless of how it's created.
        static::creating(function (self $payment) {
            if (blank($payment->public_token)) {
                $payment->public_token = Str::random(40);
            }
        });
    }

    /** Resolve a row by its public URL token (checkout/status pages). */
    public static function findByPublicToken(string $token): ?self
    {
        return static::where('public_token', $token)->first();
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rentals::class, 'rental_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid->value;
    }

    public function statusEnum(): PaymentStatus
    {
        return PaymentStatus::from($this->status);
    }

    /** Still in flight — may still settle to paid. */
    public function isOpen(): bool
    {
        return $this->statusEnum()->isOpen();
    }

    /**
     * Move the row to a new status, enforcing the legal state machine
     * (App\Enums\PaymentStatus::canTransitionTo). Mutates in memory only —
     * the caller saves, usually inside the same locked transaction.
     *
     * @return bool true if the status changed; false if it was already $to
     */
    public function transitionTo(PaymentStatus $to): bool
    {
        $from = $this->statusEnum();

        if ($from === $to) {
            return false;
        }

        if (! $from->canTransitionTo($to)) {
            throw new \LogicException(
                "Illegal payment status transition {$from->value} → {$to->value} (tx {$this->transaction_id})."
            );
        }

        $this->forceFill(['status' => $to->value]);

        return true;
    }
}
