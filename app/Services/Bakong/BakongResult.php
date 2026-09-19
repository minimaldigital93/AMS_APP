<?php

namespace App\Services\Bakong;

use Illuminate\Http\Client\Response;

/**
 * The outcome of one attempted Bakong request.
 *
 * Three states, and keeping them apart is the point:
 *
 *  - ANSWERED   — the request was made and Bakong replied (with anything at all,
 *                 including a refusal).
 *  - BLOCKED    — a gate refused before the request left. Nothing was spent.
 *  - FAILED     — the request left and the transport broke (timeout, DNS, TLS).
 *
 * A caller acting on a NEGATIVE — expiring a QR, giving up on a payment — may
 * only do so on an ANSWERED result. A blocked or failed call is not evidence
 * about the payer's money, and reading it as one is how a payment that landed
 * gets written out of the books with no way back.
 */
final class BakongResult
{
    private function __construct(
        public readonly ?Response $response,
        public readonly ?string $blockedReason,
        public readonly ?\Throwable $error,
    ) {}

    public static function answered(Response $response): self
    {
        return new self($response, null, null);
    }

    public static function blocked(string $reason): self
    {
        return new self(null, $reason, null);
    }

    public static function failed(\Throwable $error): self
    {
        return new self(null, null, $error);
    }

    public function hasResponse(): bool
    {
        return $this->response !== null;
    }

    public function wasBlocked(): bool
    {
        return $this->blockedReason !== null;
    }

    // ---- the Bakong envelope -------------------------------------------
    //
    // Every documented response is {data, errorCode, responseCode,
    // responseMessage}. responseCode is 0 for success and 1 for failure;
    // errorCode narrows the failure (1 = transaction not found, 3 = transaction
    // failed, 6 = unauthorized, ...). They answer different questions and are
    // deliberately never collapsed into one another here.

    /** @return array<mixed>|null the decoded body, or null when it was not JSON */
    public function body(): ?array
    {
        if ($this->response === null) {
            return null;
        }

        $body = $this->response->json();

        return is_array($body) ? $body : null;
    }

    public function responseCode(): ?int
    {
        $body = $this->body();

        return isset($body['responseCode']) && is_numeric($body['responseCode'])
            ? (int) $body['responseCode']
            : null;
    }

    public function errorCode(): ?int
    {
        $body = $this->body();

        return isset($body['errorCode']) && is_numeric($body['errorCode'])
            ? (int) $body['errorCode']
            : null;
    }

    public function message(): string
    {
        $body = $this->body();

        return (string) ($body['responseMessage'] ?? '');
    }

    /** @return array<mixed> the `data` envelope, or an empty array */
    public function data(): array
    {
        $body = $this->body();

        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    /**
     * Did Bakong both answer AND report success?
     *
     * A 2xx alone is not enough: the envelope's responseCode is where the
     * verdict lives, and a 200 carrying responseCode 1 is a refusal wearing a
     * success status. Requiring both is what stops a "transaction not found"
     * being read as a settled payment.
     */
    public function succeeded(): bool
    {
        return $this->response !== null
            && $this->response->successful()
            && $this->responseCode() === 0;
    }

    public function status(): ?int
    {
        return $this->response?->status();
    }
}
