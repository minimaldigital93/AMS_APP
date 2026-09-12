<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\Response;

/**
 * The outcome of one attempt to talk to khqr.cc, routed through
 * KhqrProviderClient.
 *
 * Three states, and the distinction between the last two is the whole point:
 *
 *  - ANSWERED: the request went out and the gateway replied (any HTTP status).
 *  - BLOCKED:  the request was never made, because a guard refused it. Costs
 *              nothing, and says nothing about the payment.
 *  - FAILED:   the request went out and the transport died (timeout, DNS). It
 *              HAS been charged to the Bakong allowance, and still says nothing
 *              about the payment.
 *
 * Callers must never read a blocked or failed result as "the payer has not
 * paid" — that reading is what expires a QR somebody already paid. See
 * KhqrPaymentService::verifyOutcome()'s VERIFY_REFUSED.
 */
final readonly class KhqrProviderResult
{
    private function __construct(
        public bool $allowed,
        public ?string $blockedReason,
        public ?Response $response,
        public ?\Throwable $error,
    ) {}

    /** A guard refused before any request was made — no quota spent. */
    public static function blocked(string $reason): self
    {
        return new self(false, $reason, null, null);
    }

    /** The gateway replied. Any status: 2xx, 4xx and 5xx all land here. */
    public static function answered(Response $response): self
    {
        return new self(true, null, $response, null);
    }

    /** The request was made and the transport failed. Quota was still spent. */
    public static function failed(\Throwable $error): self
    {
        return new self(true, null, null, $error);
    }

    /** True when there is a response to read (of any status). */
    public function hasResponse(): bool
    {
        return $this->response !== null;
    }

    /** True when a guard stopped this request before it cost anything. */
    public function wasBlocked(): bool
    {
        return ! $this->allowed;
    }
}
