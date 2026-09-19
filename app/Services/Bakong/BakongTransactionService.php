<?php

namespace App\Services\Bakong;

use App\Enums\PaymentStatus;
use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Creating and confirming direct-Bakong payments.
 *
 * TWO THINGS ARE STRUCTURALLY DIFFERENT FROM THE KHQRPay FLOW, and both change
 * what this class has to be careful about.
 *
 * 1. MINTING IS FREE. There is no QR endpoint to call: the payload is built
 *    locally (BakongQrService) and the transaction does not exist at Bakong
 *    until the payer actually pays it. Creating a checkout therefore costs ZERO
 *    metered requests, where KHQRPay spent one on the mint and two more on the
 *    handoff preflight. The whole preflight apparatus — probeCheckTransaction,
 *    probeHandoff, platformCheckoutFault — exists because redirect()->away() to
 *    a hosted page was a one-way door. There is no door here, so there is
 *    nothing to preflight.
 *
 * 2. CONFIRMING IS THE ONLY COST, AND THERE IS NO WEBHOOK. Under KHQRPay a
 *    signed callback was the primary settlement path and polling was the safety
 *    net. Here polling is the ONLY path, so the thing that spends the allowance
 *    is also the only thing that can see money arrive. That inverts the
 *    priority: every refusal must be preserved as a refusal rather than read as
 *    a verdict, because there is no second channel to catch what a wrong guess
 *    discards.
 *
 * Hence the three-valued result, exactly as KhqrPaymentService learned it:
 * VERIFY_PAID / VERIFY_UNPAID / VERIFY_REFUSED, and ONLY a 2xx answer from
 * Bakong may say "unpaid". A blocked call, a timeout, a 429, a 5xx and a
 * rejected token all describe OUR ACCESS, not the payer's money. Expiring a row
 * on one of those is a payment written out of the books with no way back —
 * and with no webhook, no way for it to be found again either.
 *
 * Booking the money is NOT reimplemented here: KhqrPaymentService::finalize()
 * and finalizeSubscription() never touch khqr.cc — they book the ledger rows,
 * activate the subscription, promote the owner and lock the row against
 * double-booking. That orchestration is provider-agnostic, which is precisely
 * what the PaymentGateway contract was drawn around, so this service confirms
 * and hands over. (Phase 21 renames it; it does not rewrite it.)
 */
class BakongTransactionService
{
    public const VERIFY_PAID = 'paid';

    public const VERIFY_UNPAID = 'unpaid';

    public const VERIFY_REFUSED = 'refused';

    /**
     * Bakong errorCode 1 — "Transaction could not be found. Please check and
     * try again." This is the honest pre-payment answer and by far the most
     * common response this integration will ever see: the transaction does not
     * exist at Bakong until the payer pays it.
     */
    private const ERROR_NOT_FOUND = 1;

    /** errorCode 3 — "Transaction failed." A real answer about a real attempt. */
    private const ERROR_FAILED = 3;

    /** Floating-point money: compare within half a cent, never with ===. */
    private const AMOUNT_EPSILON = 0.005;

    private bool $lastPollRefused = false;

    private ?string $lastBlock = null;

    public function __construct(
        private ?BakongProviderClient $provider = null,
        private ?BakongQrService $qr = null,
        private ?KhqrPaymentService $payments = null,
    ) {
        $this->provider = $provider ?? new BakongProviderClient;
        $this->qr = $qr ?? new BakongQrService;
        $this->payments = $payments ?? new KhqrPaymentService;
    }

    // ------------------------------------------------------------- creation

    /**
     * Create a subscription checkout and render its KHQR — without contacting
     * anyone.
     *
     * @param  Plan|null  $plan  what the customer is BUYING (not what they have)
     */
    public function createSubscriptionQr(Subscription $subscription, float $amount, ?Plan $plan = null, ?string $cycle = null): KhqrPayment
    {
        if (! BakongProviderClient::featureEnabled()) {
            // Refused before any row exists: a session that can never be
            // confirmed is worse than no session, because it shows the customer
            // a QR nobody is watching and is then swept by every net that looks
            // for open rows.
            throw new \RuntimeException(__('messages.bakong_payment_disabled'));
        }

        $accountId = (string) config('bakong.account_id');

        if ($accountId === '') {
            throw new \RuntimeException(__('messages.bakong_account_missing'));
        }

        // At most one payable QR per subscription at a time. Two live QRs for
        // one subscription is a double-payment waiting to happen, and here it
        // would also mean two md5s to poll — twice the metered cost for one
        // sale.
        KhqrPayment::where('subscription_id', $subscription->id)
            ->where('settlement_target', 'platform')
            ->whereIn('status', PaymentStatus::openValues())
            ->get()
            ->each(fn (KhqrPayment $open) => $this->expireRow($open));

        $transactionId = 'SUB-'.$subscription->id.'-'.now()->format('YmdHis').'-'.random_int(100, 999);

        $row = KhqrPayment::create([
            'transaction_id' => $transactionId,
            'provider' => 'bakong',
            'subscription_id' => $subscription->id,
            'amount' => $amount,
            'currency' => (string) config('bakong.currency', 'USD'),
            'status' => 'pending',
            'settlement_target' => 'platform',
            'channel' => 'api',
            // What the customer is buying rides on the PAYMENT, never on the
            // live subscription, so an abandoned upgrade grants nothing. See
            // finalizeSubscription(), which is the one place it is applied.
            'checkout_payload' => [
                'type' => 'subscription',
                'subscription_id' => $subscription->id,
                'plan_id' => $plan?->id ?? $subscription->plan_id,
                'billing_cycle' => $cycle ?? $subscription->billing_cycle,
            ],
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ]);

        if ((bool) config('bakong.demo')) {
            // Demo still builds a REAL payload — it is the flow being
            // demonstrated, and a fake string would not scan. It simply never
            // gets verified against anyone.
            return $this->attachQr($row, $accountId);
        }

        return $this->attachQr($row, $accountId);
    }

    /**
     * Build the payload, store it, and mark the row payable.
     *
     * The payload is STORED rather than rebuilt on demand because it is the
     * verification key: check_transaction_by_md5 takes the md5 of this exact
     * string, so a later settings edit — a renamed merchant, a changed city —
     * would otherwise make an already-paid transaction permanently
     * unverifiable.
     */
    private function attachQr(KhqrPayment $row, string $accountId): KhqrPayment
    {
        $qr = $this->qr->build(
            billNumber: $row->transaction_id,
            amount: (float) $row->amount,
            bakongAccountId: $accountId,
            merchantName: (string) config('bakong.merchant_name'),
            merchantCity: (string) config('bakong.merchant_city'),
            currency: (string) $row->currency,
        );

        $row->forceFill([
            'qr_payload' => $qr->payload,
            'qr_md5' => $qr->md5,
        ]);

        $row->transitionTo(PaymentStatus::QrGenerated);
        $row->save();

        return $row;
    }

    /** The QR as an inline SVG data URI, for the checkout page or a printed bill. */
    public function qrImage(KhqrPayment $row, int $size = 280): ?string
    {
        return blank($row->qr_payload) ? null : $this->qr->dataUri($row->qr_payload, $size);
    }

    // --------------------------------------------------------- verification

    /**
     * Ask Bakong whether this payment has settled — three-valued.
     *
     * ONLY a 2xx answer may say UNPAID. Everything else is REFUSED.
     */
    public function verifyOutcome(KhqrPayment $row, int $sessionGrace = 0): string
    {
        $this->lastBlock = null;

        if ($row->isPaid()) {
            return self::VERIFY_PAID;
        }

        if (blank($row->qr_md5)) {
            // Nothing to ask about. Not a statement about the payer.
            return self::VERIFY_REFUSED;
        }

        $result = $this->provider->call(
            reason: BakongProviderClient::REASON_PAYMENT_VERIFICATION,
            endpoint: BakongProviderClient::EP_CHECK_MD5,
            payload: ['md5' => $row->qr_md5],
            row: $row,
            target: $row->settlement_target ?: 'platform',
            sessionGrace: $sessionGrace,
        );

        if ($result->wasBlocked()) {
            $this->lastBlock = $result->blockedReason;

            return self::VERIFY_REFUSED;
        }

        if (! $result->hasResponse()) {
            // A transport failure says nothing about the money.
            return self::VERIFY_REFUSED;
        }

        if (! $result->response->successful()) {
            // 401/403 (token), 429 (allowance), 5xx (Bakong unwell) all describe
            // OUR ACCESS. The client has already backed the credential off.
            $this->logRefusal($row, (string) $result->status(), $result->message());

            return self::VERIFY_REFUSED;
        }

        if ($result->body() === null) {
            // A 2xx that is not the documented envelope — an HTML error page, a
            // proxy challenge, a truncated body — is not an answer. Reading it
            // as an empty envelope is what makes it look like "not paid", and
            // "not paid" is the verdict that expires a QR.
            $this->logRefusal($row, 'malformed', BakongProviderClient::redact(strip_tags((string) $result->response->body()), 120));

            return self::VERIFY_REFUSED;
        }

        if ($result->succeeded()) {
            return $this->readSettlement($row, $result);
        }

        // responseCode 1: Bakong answered, and declined. WHICH refusal decides
        // whether this is news about the payment or news about us.
        $errorCode = $result->errorCode();

        if ($errorCode === self::ERROR_NOT_FOUND) {
            // The everyday answer: the payer has not paid yet, so no transaction
            // exists. Genuinely unpaid.
            return self::VERIFY_UNPAID;
        }

        if ($errorCode === self::ERROR_FAILED) {
            // A real attempt that did not go through. Also genuinely unpaid —
            // and the payer can simply scan again, so the row stays open for
            // its remaining lifetime rather than being failed here.
            Log::info('Bakong reports transaction failed', ['transaction' => $row->transaction_id]);

            return self::VERIFY_UNPAID;
        }

        // Anything else (6 unauthorized, 10 not registered, or an errorCode not
        // in the published list) is not a statement we can read as "unpaid".
        $this->logRefusal($row, 'errorCode '.$errorCode, $result->message());

        return self::VERIFY_REFUSED;
    }

    /**
     * A success envelope. Confirm it is about THIS payment before believing it.
     *
     * The md5 is derived from a payload that already contains the amount and
     * currency, so a matching md5 nearly implies a matching sum — "nearly" is
     * why this is checked anyway. Booking a payment for the wrong amount is
     * unrecoverable in a way that asking again is not.
     */
    private function readSettlement(KhqrPayment $row, BakongResult $result): string
    {
        $data = $result->data();

        $amount = isset($data['amount']) && is_numeric($data['amount']) ? (float) $data['amount'] : null;
        $currency = strtoupper((string) ($data['currency'] ?? ''));

        if ($amount !== null && abs($amount - (float) $row->amount) > self::AMOUNT_EPSILON) {
            Log::warning('Bakong settlement amount mismatch', [
                'transaction' => $row->transaction_id,
                'expected' => (float) $row->amount,
                'reported' => $amount,
            ]);

            return self::VERIFY_REFUSED;
        }

        if ($currency !== '' && $currency !== strtoupper((string) $row->currency)) {
            Log::warning('Bakong settlement currency mismatch', [
                'transaction' => $row->transaction_id,
                'expected' => $row->currency,
                'reported' => $currency,
            ]);

            return self::VERIFY_REFUSED;
        }

        // Bakong's own transaction hash: kept for audit, and it is the key
        // check_transaction_by_hash accepts long after this md5's QR is gone.
        if (filled($data['hash'] ?? null)) {
            $row->forceFill(['provider_hash' => (string) $data['hash']])->save();
        }

        return self::VERIFY_PAID;
    }

    /** The thin bool the PaymentGateway contract asks for. */
    public function verify(KhqrPayment $row): bool
    {
        return $this->verifyOutcome($row) === self::VERIFY_PAID;
    }

    // --------------------------------------------------------------- polling

    /**
     * What a checkout page's status endpoint calls.
     *
     * Deliberately mirrors KhqrPaymentService::pollAndAdvance(), including the
     * bounded deadline rescue — but the stakes are higher here, because there
     * is no webhook behind it. Under KHQRPay a row left open after a refusal
     * could still be settled by a callback; here, leaving it open is the ONLY
     * thing that keeps a landed payment findable.
     */
    public function pollAndAdvance(KhqrPayment $row): KhqrPayment
    {
        $this->markWaiting($row);
        $this->lastPollRefused = false;

        // ONE post-expiry verify per session, not one per poll. A payment can
        // land in the last seconds before a QR expires, and expiring the row is
        // what shuts the door on it — finalize() refuses a row that is not open.
        // But an abandoned tab must not re-ask about a dead QR once per cooldown
        // forever, which at a 60s cooldown is a metered request a minute,
        // indefinitely, for a QR nobody can pay.
        $grace = ($row->isPaid() || $row->isActiveBakongSession())
            ? 0
            : $this->claimPostExpiryVerify($row);

        $outcome = $this->verifyOutcome($row, $grace);

        if ($outcome === self::VERIFY_PAID) {
            $this->payments->finalize($row);

            return $row->refresh();
        }

        if ($outcome === self::VERIFY_REFUSED) {
            // A poll that merely arrived inside the cooldown has nothing new to
            // say — warning the customer about it would make ordinary polling
            // look like a broken gateway. Every other refusal is worth showing
            // beside the spinner, because otherwise a gateway refusing every
            // request is indistinguishable from a payer who has not paid.
            $this->lastPollRefused = $this->lastBlock !== BakongProviderClient::BLOCK_COOLDOWN;

            return $row;
        }

        if ($this->expireIfElapsed($row)) {
            return $row->refresh();
        }

        return $row;
    }

    public function lastPollRefused(): bool
    {
        return $this->lastPollRefused;
    }

    /** Which gate refused the last verification, if one did. */
    public function lastBlock(): ?string
    {
        return $this->lastBlock;
    }

    /**
     * Claim the one post-expiry verify this session is allowed.
     *
     * A cache failure yields 0 — no rescue is better than a leak that cannot be
     * bounded.
     */
    private function claimPostExpiryVerify(KhqrPayment $row): int
    {
        $grace = (int) config('bakong.reconcile_grace', 30);

        if ($grace <= 0 || ! $row->isActiveBakongSession($grace)) {
            return 0;
        }

        try {
            return Cache::add('bakong:post-expiry:'.sha1($row->transaction_id), 1, now()->addDay())
                ? $grace
                : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function markWaiting(KhqrPayment $row): void
    {
        if ($row->status === PaymentStatus::QrGenerated->value) {
            $row->transitionTo(PaymentStatus::WaitingPayment);
            $row->save();
        }
    }

    /** Close a QR whose own deadline has passed. Local; costs nothing. */
    public function expireIfElapsed(KhqrPayment $row): bool
    {
        if (! $row->isOpen() || $row->expires_at === null || $row->expires_at->isFuture()) {
            return false;
        }

        return $this->expireRow($row);
    }

    private function expireRow(KhqrPayment $row): bool
    {
        if (! $row->isOpen()) {
            return false;
        }

        $row->transitionTo(PaymentStatus::Expired);
        $row->save();

        return true;
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('bakong.qr_ttl', 6));
    }

    private function logRefusal(KhqrPayment $row, string $what, string $message): void
    {
        Log::warning('Bakong verification refused', [
            'transaction' => $row->transaction_id,
            'what' => $what,
            'message' => BakongProviderClient::redact($message, 160),
        ]);
    }
}
