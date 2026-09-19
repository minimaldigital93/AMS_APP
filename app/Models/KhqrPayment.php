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
     * Is this row an ACTIVE KHQR PAYMENT SESSION — the only thing that justifies
     * an outbound request to khqr.cc?
     *
     * A database row is not a payment. "status is open" is not enough either: an
     * open row can be a QR that died an hour ago, a manual bank transfer that
     * the gateway has never heard of, or a mint that failed before any session
     * existed at the provider. Asking Bakong about any of those spends a metered
     * request on a question that cannot be answered, which is how a token rated
     * ~100/day went missing overnight with nobody touching the app.
     *
     * Each condition excludes a class of row that used to be verified:
     *
     *  - channel 'api' — a manual-channel row is settled by the landlord in
     *    their banking app; the gateway has no record of it at all.
     *  - a transaction id and a known settlement target — nothing to ask about,
     *    or no budget (and no token) the request could honestly be charged to.
     *  - status still open, and not stamped paid — terminal rows (paid/expired/
     *    failed/cancelled/refunded/rejected) are already decided.
     *  - status past 'pending' — pending means the QR was never successfully
     *    minted, so there is no session at the gateway to ask about. This is the
     *    line between "a billing row exists" and "a payment session exists".
     *  - it came out of a checkout flow — see originatedFromCheckout(): a row a
     *    confirmed payment could not even be booked against is not worth a call.
     *  - the session is still live — its own expires_at is in the future, or
     *    within $graceMinutes of having passed — and never older than a day,
     *    whatever expires_at says.
     *
     * $graceMinutes is the khqr:reconcile rescue window and nothing else: a
     * payment can land in the last seconds before expiry and its webhook can
     * still fail, and then the net is the only thing that will ever find it. The
     * browser pollers pass 0 — an elapsed QR is expired locally instead, for
     * free.
     *
     * Rows minted before expires_at existed fall back to created_at + the
     * configured QR lifetime, the same fallback khqr:reconcile uses.
     *
     * Credentials are the one condition not checked here — they live outside the
     * row, and KhqrProviderClient refuses a request it has nothing to sign with.
     */
    public function isActiveKhqrSession(int $graceMinutes = 0): bool
    {
        $status = PaymentStatus::tryFrom((string) $this->status);

        if ($status === null || ! $status->isOpen() || $status === PaymentStatus::Pending) {
            return false;
        }

        if (! $this->hasGatewayIdentity() || $this->paid_at !== null || ! $this->originatedFromCheckout()) {
            return false;
        }

        $deadline = $this->expires_at
            ?? $this->created_at?->copy()->addMinutes(max(1, (int) config('services.khqrpay.qr_ttl', 30)));

        if ($deadline === null) {
            return false;
        }

        // Hard ceiling, the same one khqr:reconcile's window has always had: a
        // row with a bad expires_at must not stay askable indefinitely.
        if ($this->created_at !== null && $this->created_at->lt(now()->subDay())) {
            return false;
        }

        return $deadline->copy()->addMinutes(max(0, $graceMinutes))->isFuture();
    }

    /**
     * Is this row an ACTIVE DIRECT-BAKONG PAYMENT SESSION — the only thing that
     * justifies a metered request to the NBC Open API about it?
     *
     * The same principle as isActiveKhqrSession() — a database row is not a
     * payment — but the evidence differs, because the two providers make a
     * session in opposite ways.
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
     * May the QR for this row be minted at the gateway right now?
     *
     * The creation-side twin of isActiveKhqrSession(): a mint is only justified
     * for a row a checkout has JUST created — still `pending`, still inside its
     * own lifetime, owned by something the payment would be booked against. A
     * row that is already minted, failed, or abandoned is not re-minted.
     */
    public function isMintableKhqrSession(): bool
    {
        return $this->status === PaymentStatus::Pending->value
            && $this->hasGatewayIdentity()
            && $this->paid_at === null
            && $this->hasCheckoutOwner()
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Was this row produced by one of the checkout flows, with something a
     * confirmed payment can be booked against?
     *
     *  - platform: KhqrPaymentService::createSubscriptionQr() — a subscription.
     *    Hosted checkout mints no QR image here, so there is nothing more to
     *    require.
     *  - merchant: KhqrPaymentService::createQr() — a rental and its fiscal
     *    period, plus evidence the mint actually succeeded at the gateway (a QR
     *    url or a provider reference). A tenant cannot pay a QR that was never
     *    shown, so there is nothing at the gateway to ask about.
     */
    public function originatedFromCheckout(): bool
    {
        if (! $this->hasCheckoutOwner()) {
            return false;
        }

        return $this->settlement_target !== 'merchant'
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

    /** An api-channel row with an id to ask about and a token to charge it to. */
    private function hasGatewayIdentity(): bool
    {
        return $this->channel === 'api'
            && filled($this->transaction_id)
            && in_array($this->settlement_target, ['platform', 'merchant'], true);
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
