<?php

namespace App\Services\Bakong;

use App\Models\BakongToken;
use Illuminate\Support\Facades\Log;

/**
 * The Bakong access token's whole life: issue, verify, renew, report.
 *
 * Bakong's token flow has a HUMAN IN THE MIDDLE, which shapes everything here:
 *
 *   POST /v1/request_token {email, organization, project}  → emails a 20-char code
 *   POST /v1/verify        {code}                          → {data:{token}}
 *   POST /v1/renew_token   {email}                         → {data:{token}}
 *
 * Nobody can automate the middle step, because the code arrives in a mailbox.
 * So issuance is an operator command (`bakong:token`), not something a checkout
 * can trigger — and a deployment that has never run it simply has no token,
 * which BakongProviderClient refuses on rather than discovering mid-payment.
 *
 * THE ONE RULE THIS SERVICE EXISTS TO ENFORCE: do not spend metered requests on
 * token housekeeping. A token states its own expiry in its JWT, so:
 *
 *  - expiry is DECODED LOCALLY, never asked for;
 *  - renewal happens only inside the configured window before that expiry, so
 *    the steady-state cost is roughly four requests a year;
 *  - nothing requests a token per operation, per request or per payment.
 *
 * Every call still goes through BakongProviderClient like any other, so the
 * master switch, the ceiling and the backoffs all apply to token traffic too.
 * A token endpoint is not exempt from the budget: if the day's allowance is
 * gone, renewing tomorrow is better than failing a payment today.
 */
class BakongTokenService
{
    public function __construct(private ?BakongProviderClient $provider = null)
    {
        $this->provider = $provider ?? new BakongProviderClient;
    }

    /**
     * Step 1 — register the integrator and ask Bakong to email a code.
     *
     * The row is written BEFORE the code arrives, with verified_at null, so a
     * pending registration is visible in the database rather than living only
     * in whoever ran the command's memory. Re-running is safe: it updates the
     * same row rather than stacking duplicates, which the unique index on email
     * would reject anyway.
     *
     * @return array{ok: bool, message: string, blocked: ?string}
     */
    public function requestCode(): array
    {
        $email = (string) config('bakong.integrator.email');
        $organization = (string) config('bakong.integrator.organization');
        $project = (string) config('bakong.integrator.project');

        if ($email === '' || $organization === '' || $project === '') {
            return $this->fail(__('messages.bakong_token_identity_missing'));
        }

        $result = $this->provider->call(
            reason: BakongProviderClient::REASON_TOKEN_REQUEST,
            endpoint: BakongProviderClient::EP_REQUEST_TOKEN,
            payload: [
                'email' => $email,
                'organization' => $organization,
                'project' => $project,
            ],
        );

        if (! $result->succeeded()) {
            return $this->fromRefusal($result);
        }

        BakongToken::updateOrCreate(
            ['email' => $email],
            ['organization' => $organization, 'project' => $project],
        );

        return [
            'ok' => true,
            'message' => $result->message() ?: __('messages.bakong_token_code_sent'),
            'blocked' => null,
        ];
    }

    /**
     * Step 2 — exchange the emailed code for the access token.
     *
     * The document constrains the code to exactly 20 characters. Checking that
     * locally costs nothing and saves a metered request on the commonest
     * mistake there is: a code pasted with the surrounding whitespace, or only
     * half selected.
     *
     * @return array{ok: bool, message: string, blocked: ?string}
     */
    public function verifyCode(string $code): array
    {
        $code = trim($code);

        if (strlen($code) !== 20) {
            return $this->fail(__('messages.bakong_token_code_length'));
        }

        $result = $this->provider->call(
            reason: BakongProviderClient::REASON_TOKEN_VERIFY,
            endpoint: BakongProviderClient::EP_VERIFY,
            payload: ['code' => $code],
        );

        if (! $result->succeeded()) {
            return $this->fromRefusal($result);
        }

        $token = (string) ($result->data()['token'] ?? '');

        if ($token === '') {
            // A success envelope with no token in it is not a success. Storing
            // the empty string would make every later request fail the no_token
            // gate while the row claimed to be verified.
            return $this->fail(__('messages.bakong_token_missing_in_response'));
        }

        $this->store($token, verified: true);

        return [
            'ok' => true,
            'message' => __('messages.bakong_token_issued'),
            'blocked' => null,
        ];
    }

    /**
     * Renew the token for the registered email.
     *
     * renew_token takes only the email — the OLD token is not required and is
     * not sent, which matters because the commonest reason to renew is that the
     * old one is already dead.
     *
     * @return array{ok: bool, message: string, blocked: ?string}
     */
    public function renew(): array
    {
        $email = (string) config('bakong.integrator.email');

        if ($email === '') {
            return $this->fail(__('messages.bakong_token_identity_missing'));
        }

        $result = $this->provider->call(
            reason: BakongProviderClient::REASON_TOKEN_RENEW,
            endpoint: BakongProviderClient::EP_RENEW_TOKEN,
            payload: ['email' => $email],
        );

        if (! $result->succeeded()) {
            return $this->fromRefusal($result);
        }

        $token = (string) ($result->data()['token'] ?? '');

        if ($token === '') {
            return $this->fail(__('messages.bakong_token_missing_in_response'));
        }

        $this->store($token, verified: true, renewed: true);

        return [
            'ok' => true,
            'message' => __('messages.bakong_token_renewed'),
            'blocked' => null,
        ];
    }

    /**
     * Renew only if the token is inside its renewal window.
     *
     * This is what the scheduler calls, and it is deliberately the ONLY
     * automatic path to a token request. It answers "no" on almost every run —
     * a token lasting ~93 days needs renewing about four times a year — so the
     * standing cost of keeping a credential alive is four requests, not one per
     * day and certainly not one per payment.
     *
     * A token with no readable expiry is left alone: renewing on a schedule we
     * cannot justify would be a standing metered request, and a genuinely dead
     * token surfaces as a 401 that trips the failure backoff anyway.
     *
     * @return array{ok: bool, message: string, blocked: ?string}
     */
    public function renewIfDue(): array
    {
        $row = BakongToken::current();

        if ($row === null || ! $row->needsRenewal()) {
            return [
                'ok' => true,
                'message' => __('messages.bakong_token_not_due'),
                'blocked' => null,
            ];
        }

        Log::info('Bakong token renewal due', [
            'expires_at' => $row->expires_at?->toIso8601String(),
            'token' => $row->fingerprint(),
        ]);

        return $this->renew();
    }

    /**
     * What an operator or a diagnostics page may be told about the token.
     *
     * Deliberately never includes the token itself — only a stable fingerprint,
     * so two reports can be shown to be about the same credential without the
     * credential appearing in either.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $row = BakongToken::current();

        return [
            'configured' => filled(config('bakong.integrator.email')),
            'email' => (string) config('bakong.integrator.email'),
            'registered' => $row !== null,
            'verified' => $row?->verified_at !== null,
            'usable' => (bool) $row?->isUsable(),
            'needs_renewal' => (bool) $row?->needsRenewal(),
            'expires_at' => $row?->expires_at?->toIso8601String(),
            'renewed_at' => $row?->renewed_at?->toIso8601String(),
            'fingerprint' => $row?->fingerprint() ?? 'none',
        ];
    }

    private function store(string $token, bool $verified = false, bool $renewed = false): void
    {
        $attributes = [
            'organization' => (string) config('bakong.integrator.organization'),
            'project' => (string) config('bakong.integrator.project'),
            'token' => $token,
            // Read out of the JWT. Null when it cannot be decoded, which leaves
            // the token usable (isUsable treats a null expiry as "unknown, try
            // it") but never auto-renewed.
            'expires_at' => BakongToken::expiryFromJwt($token),
        ];

        if ($verified) {
            $attributes['verified_at'] = now();
        }

        if ($renewed) {
            $attributes['renewed_at'] = now();
        }

        BakongToken::updateOrCreate(
            ['email' => (string) config('bakong.integrator.email')],
            $attributes,
        );

        // A fresh credential deserves a clean slate: an operator who has just
        // fixed the token should not wait out a backoff earned by the dead one.
        $this->provider->ledger()->clearBackoffs('platform');
    }

    /**
     * Turn a refusal into something a human can act on, without ever quoting a
     * credential back at them.
     *
     * @return array{ok: bool, message: string, blocked: ?string}
     */
    private function fromRefusal(BakongResult $result): array
    {
        if ($result->wasBlocked()) {
            return [
                'ok' => false,
                'message' => __('messages.bakong_request_blocked', ['reason' => $result->blockedReason]),
                'blocked' => $result->blockedReason,
            ];
        }

        if (! $result->hasResponse()) {
            return $this->fail(__('messages.bakong_unreachable'));
        }

        $detail = trim(sprintf(
            'HTTP %s%s%s',
            $result->status(),
            $result->errorCode() !== null ? ' · errorCode '.$result->errorCode() : '',
            $result->message() !== '' ? ' · '.BakongProviderClient::redact($result->message(), 160) : '',
        ));

        return $this->fail($detail);
    }

    /** @return array{ok: bool, message: string, blocked: ?string} */
    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'blocked' => null];
    }
}
