<?php

namespace App\Services\Payment;

use App\Models\KhqrPayment;
use App\Models\PaymentWebhook;
use App\Services\RevenueExpense\KhqrPaymentService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ingests an inbound payment webhook: persists every delivery, rejects replays
 * and stale/forged ones, and finalizes exactly once.
 *
 * Defence in depth — three independent guards before any money is booked:
 *   1. event_id idempotency  → a duplicate/replayed delivery is acked (200)
 *      without re-running finalize (unique index on payment_webhooks.event_id).
 *   2. req_time freshness     → a delivery older than the tolerance window is
 *      rejected even if its signature is valid (captured-and-replayed payloads).
 *   3. signature + amount/currency/status (KhqrPaymentService::isValidCallbackFor)
 *      → cryptographic auth against the specific row the QR was minted for.
 * finalize() then applies its own row lock, so even a race past (1)/(2) cannot
 * double-book.
 *
 * Returned as [status, body] so the controller stays a one-liner and the whole
 * thing is trivially unit-testable.
 */
class WebhookIngestService
{
    public function __construct(
        private PaymentManager $gateways,
        private KhqrPaymentService $payments,
    ) {}

    /**
     * @return array{status:int, body:array}
     */
    public function ingest(array $payload, string $provider = 'khqrpay'): array
    {
        $eventId = $this->eventId($payload);

        // (1) Idempotency: we've already seen this exact event → ack, don't re-run.
        if (PaymentWebhook::where('event_id', $eventId)->exists()) {
            return $this->ok(['ok' => true, 'duplicate' => true]);
        }

        try {
            $webhook = PaymentWebhook::create([
                'provider' => $provider,
                'event_id' => $eventId,
                'transaction_id' => $payload['transaction_id'] ?? null,
                'status' => PaymentWebhook::STATUS_RECEIVED,
                'payload' => $payload,
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Two identical deliveries raced the unique index — the other won.
            if ($this->isUniqueViolation($e)) {
                return $this->ok(['ok' => true, 'duplicate' => true]);
            }
            throw $e;
        }

        // Resolve the row FIRST so the signature is checked against the right
        // secret (platform vs merchant). Unknown tx → same 403 as a bad signature
        // (don't reveal which transaction ids exist).
        $row = isset($payload['transaction_id'])
            ? KhqrPayment::where('transaction_id', $payload['transaction_id'])->first()
            : null;

        if (! $row) {
            return $this->reject($webhook, 'unknown transaction');
        }

        $webhook->forceFill(['khqr_payment_id' => $row->id])->save();

        // (2) Freshness — reject obviously-replayed deliveries.
        if (! $this->isFresh($payload)) {
            return $this->reject($webhook, 'stale req_time');
        }

        // (3) Signature + amount + currency + status, against this exact row —
        // delegated to whichever provider's gateway minted the charge.
        if (! $this->gateways->for($row)->validateWebhook($row, $payload)) {
            return $this->reject($webhook, 'invalid signature/amount/currency/status');
        }

        $this->payments->finalize($row);

        $webhook->forceFill([
            'status' => PaymentWebhook::STATUS_PROCESSED,
            'signature_valid' => true,
            'http_status' => 200,
            'processed_at' => now(),
        ])->save();

        return $this->ok(['ok' => true]);
    }

    /** Stable idempotency key: provider event id, else the signature hash. */
    private function eventId(array $payload): string
    {
        return (string) ($payload['event_id'] ?? $payload['hash'] ?? 'nohash-'.Str::random(24));
    }

    /**
     * True when req_time is within the tolerance window (or absent/unparseable —
     * we never reject a delivery purely because the timestamp format surprised us;
     * the signature is still the authoritative check).
     */
    private function isFresh(array $payload): bool
    {
        $reqTime = $payload['req_time'] ?? null;
        if ($reqTime === null || $reqTime === '') {
            return true;
        }

        try {
            $ts = is_numeric($reqTime) ? Carbon::createFromTimestamp((int) $reqTime) : Carbon::parse($reqTime);
        } catch (\Throwable) {
            Log::warning('KHQRPay webhook req_time unparseable', ['req_time' => $reqTime]);

            return true;
        }

        $tolerance = (int) config('services.khqrpay.webhook_tolerance', 600);

        return abs($ts->diffInSeconds(now(), false)) <= $tolerance;
    }

    private function reject(PaymentWebhook $webhook, string $reason): array
    {
        $webhook->forceFill([
            'status' => PaymentWebhook::STATUS_INVALID,
            'http_status' => 403,
            'error' => $reason,
        ])->save();

        Log::warning('Payment webhook rejected', ['event_id' => $webhook->event_id, 'tran' => $webhook->transaction_id, 'reason' => $reason]);

        return ['status' => 403, 'body' => ['message' => 'Invalid signature']];
    }

    private function ok(array $body): array
    {
        return ['status' => 200, 'body' => $body];
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23000' || str_contains($e->getMessage(), 'UNIQUE');
    }
}
