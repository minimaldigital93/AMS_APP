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
        'initiated_by_user_id',
        'amount',
        'currency',
        'status',
        'settlement_target',
        'channel',
        'checkout_payload',
        'qr_url',
        'qr_payload',
        'qr_md5',
        'provider_ref',
        'provider_hash',
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

    /** Who started the payment — null on rows minted before tenants could. */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /**
     * Rent sessions a tenant started and nobody has settled yet.
     *
     * This is the landlord's queue: money the payer says they have sent, which
     * only the landlord can confirm against their own bank. A landlord-started
     * session is deliberately excluded — it is already on the screen that
     * created it, and listing it twice invites a double confirmation.
     */
    public function scopeAwaitingLandlord($query)
    {
        return $query->whereNotNull('rental_id')
            ->whereNotNull('initiated_by_user_id')
            ->whereIn('status', PaymentStatus::openValues());
    }

    /**
     * The plan this payment is BUYING — not the one the account has.
     *
     * checkout_payload is the authority: plan_id is deliberately never written
     * to the live subscription before the money lands, so subscription->plan is
     * the OLD plan for the whole life of an upgrade checkout, and a page that
     * read it would tell the payer they are buying what they already have.
     * Falls back to the subscription for rows minted before the payload
     * existed, exactly as KhqrPaymentService::finalizeSubscription() resolves
     * it — this is that same read, so the two cannot disagree.
     */
    public function purchasedPlan(): ?Plan
    {
        $id = $this->checkout_payload['plan_id'] ?? null;

        return ($id ? Plan::find((int) $id) : null) ?? $this->subscription?->plan;
    }

    /** The billing cycle this payment is buying. Same rule as purchasedPlan(). */
    public function purchasedCycle(): ?string
    {
        return $this->checkout_payload['billing_cycle'] ?? $this->subscription?->billing_cycle;
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

    /*
     * isActiveKhqrSession() and isMintableKhqrSession() used to live here.
     *
     * They answered "is this row worth a metered khqr.cc request?" — the rule
     * that a DATABASE ROW IS NOT A PAYMENT WORTH ASKING ABOUT, which is what
     * the quota leak of 2026-08 came down to. The provider is gone, so no rent
     * row is ever asked about again; the rule itself survives, applied to the
     * only gateway left, as isActiveBakongSession() below.
     */

    /**
     * Is this row an ACTIVE DIRECT-BAKONG PAYMENT SESSION — the only thing that
     * justifies a metered request to the NBC Open API about it?
     *
     * The same principle the retired isActiveKhqrSession() applied — a database
     * row is not a payment — but the evidence differs, because the two
     * providers made a session in opposite ways.
     *
     * Under KHQRPay a session existed once THE GATEWAY minted one, so the proof
     * was a provider_ref coming back and the row leaving `pending`. Bakong has
     * no QR endpoint: AMS builds the payload itself, and the transaction only
     * exists at Bakong once the payer actually pays it. So the proof that there
     * is something to ask about is that WE rendered a payable QR — a payload
     * and its md5, which is literally the lookup key check_transaction_by_md5
     * takes. Without an md5 there is no question that could be asked, and the
     * request could only be charged and refused.
     *
     *  - provider 'bakong'  — a khqr.cc row is a different gateway's business.
     *  - channel 'api'      — a manual-channel row is settled by the landlord in
     *                         their banking app; polling Bakong for it asks
     *                         about a transaction nobody generated.
     *  - a qr_md5           — the lookup key exists, so a question exists.
     *  - status open, past `pending`, not stamped paid — a pending row was never
     *                         rendered to anyone, and terminal rows are decided.
     *  - originatedFromCheckout() — a row a confirmed payment could not be
     *                         booked against is not worth a metered call.
     *  - still live         — inside its own expiry plus $graceMinutes, and
     *                         never older than a day whatever expires_at says.
     *
     * $graceMinutes is the reconcile rescue window and nothing else. The browser
     * poller passes 0: an elapsed QR is expired locally, for free.
     */
    public function isActiveBakongSession(int $graceMinutes = 0): bool
    {
        if ($this->provider !== 'bakong' || $this->channel !== 'api') {
            return false;
        }

        $status = PaymentStatus::tryFrom((string) $this->status);

        if ($status === null || ! $status->isOpen() || $status === PaymentStatus::Pending) {
            return false;
        }

        if (blank($this->qr_md5) || $this->paid_at !== null || ! $this->originatedFromCheckout()) {
            return false;
        }

        $deadline = $this->expires_at
            ?? $this->created_at?->copy()->addMinutes(max(1, (int) config('bakong.qr_ttl', 6)));

        if ($deadline === null) {
            return false;
        }

        // Hard ceiling: a row with a bad expires_at must not stay askable
        // indefinitely, however generous the grace window is.
        if ($this->created_at !== null && $this->created_at->lt(now()->subDay())) {
            return false;
        }

        return $deadline->copy()->addMinutes(max(0, $graceMinutes))->isFuture();
    }

    /** Was this row minted against the direct Bakong integration? */
    public function usesBakong(): bool
    {
        return $this->provider === 'bakong';
    }

    /**
     * Was this row produced by one of the checkout flows, with something a
     * confirmed payment can be booked against?
     *
     *  - platform: BakongTransactionService::createSubscriptionQr() — a
     *    subscription.
     *  - merchant: KhqrPaymentService::createQr() — a rental and its fiscal
     *    period, plus evidence something payable was actually rendered: a QR
     *    payload (built here), a QR url (the landlord's static image, or a
     *    hosted image on a legacy khqr.cc row) or a provider reference. A tenant
     *    cannot pay a QR that was never shown.
     */
    public function originatedFromCheckout(): bool
    {
        if (! $this->hasCheckoutOwner()) {
            return false;
        }

        return $this->settlement_target !== 'merchant'
            || filled($this->qr_payload)
            || filled($this->qr_url)
            || filled($this->provider_ref);
    }

    private function hasCheckoutOwner(): bool
    {
        return match ($this->settlement_target) {
            'platform' => $this->subscription_id !== null,
            'merchant' => $this->rental_id !== null && $this->fiscal_period_id !== null,
            default => false,
        };
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
