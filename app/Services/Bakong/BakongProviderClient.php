<?php

namespace App\Services\Bakong;

use App\Models\BakongApiCall;
use App\Models\BakongToken;
use App\Models\KhqrPayment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * THE ONLY PLACE IN THIS APPLICATION THAT MAY TALK TO THE BAKONG OPEN API.
 *
 * Every outbound request — token issuance and renewal, transaction checks,
 * deeplinks, account checks, diagnostics — goes through call(). Controllers,
 * jobs, commands, models, Blade views and scheduled tasks must never construct
 * a Bakong request of their own.
 *
 * THE CALL SITE SUPPLIES A PAYLOAD, NEVER A REQUEST. Every documented Bakong
 * endpoint is the same shape — POST JSON to {baseUrl}{path}, optionally bearing
 * the access token — so this class builds the request itself. The retired
 * khqr.cc client could not do that: its four endpoints had four different
 * shapes, so each call site handed in a closure and was merely trusted to
 * behave. Building it here is the stronger guarantee — a call site cannot
 * express a request this class has not agreed to.
 *
 * WHY ANY OF THIS EXISTS. Bakong meters the upstream token per CALENDAR DAY
 * (~100 requests on this account) and charges a REFUSED request exactly like a
 * successful one. And unlike KHQRPay there is NO WEBHOOK — polling is the only
 * settlement signal, so the thing that spends the allowance is also the only
 * thing that confirms a payment. Guards scattered across call sites were the
 * old design and what it produced was a token drained overnight with nobody
 * touching the app. The protection has to live where a request cannot avoid it.
 *
 * TEN GATES, ALL REFUSALS BEFORE THE REQUEST, cheapest and most absolute first
 * and the one that spends the day's allowance last:
 *
 *   1. disabled         — bakong.enabled is false. Absolute: nothing gets past.
 *   2. demo_mode        — the local simulation must never transmit.
 *   3. not_configured   — no base URL. NBC never publishes it in the document,
 *                         so it is not guessed; empty means "not set up yet".
 *   4. invalid_request  — unknown reason, unknown endpoint, unknown target, or
 *                         a row-bound reason with no row.
 *   5. no_token         — the endpoint needs an Authorization header and there
 *                         is no usable one. An expired token is ABSENT, not
 *                         sent anyway: Bakong charges a 401 like a sale.
 *   6. no_active_payment— the row is not a payment session this request can be
 *                         about. A database row is not a payment.
 *   7. rate_limited     — Bakong answered 429 recently.
 *   7b. upstream_quota_exhausted — NBC said the day's allowance is gone.
 *                         Latched until midnight; NOTHING is exempt, because
 *                         every request would be charged and refused.
 *   8. provider_backoff — a refusal that will still be true next time.
 *   9. verify_cooldown  — the same transaction was asked about inside the
 *                         cooldown, or is being asked about RIGHT NOW by
 *                         another process. Claimed atomically.
 *  10. max_attempts     — this one session has already cost as much as any
 *                         session needs.
 *  11. daily_budget     — the ceiling. Reserved atomically, FAILS CLOSED.
 *
 * Every allowed request and every refusal is written to bakong_api_calls with
 * the reason it happened or did not. That ledger is how the next accidental
 * call gets found — which is why `reason` is a required parameter and a request
 * nobody can account for is refused outright.
 */
class BakongProviderClient
{
    // ---- the documented endpoints, and nothing else ---------------------
    // Source: "Bakong OpenAPI Documentation" v1.0.2, May 2021, NBC. No path
    // here is inferred, and a path not in this list cannot be requested.

    public const EP_REQUEST_TOKEN = '/v1/request_token';

    public const EP_VERIFY = '/v1/verify';

    public const EP_RENEW_TOKEN = '/v1/renew_token';

    public const EP_DEEPLINK = '/v1/generate_deeplink_by_qr';

    public const EP_CHECK_MD5 = '/v1/check_transaction_by_md5';

    public const EP_CHECK_HASH = '/v1/check_transaction_by_hash';

    public const EP_CHECK_SHORT_HASH = '/v1/check_transaction_by_short_hash';

    public const EP_CHECK_ACCOUNT = '/v1/check_bakong_account';

    public const ENDPOINTS = [
        self::EP_REQUEST_TOKEN,
        self::EP_VERIFY,
        self::EP_RENEW_TOKEN,
        self::EP_DEEPLINK,
        self::EP_CHECK_MD5,
        self::EP_CHECK_HASH,
        self::EP_CHECK_SHORT_HASH,
        self::EP_CHECK_ACCOUNT,
    ];

    /**
     * Endpoints that carry "Authorization: Bearer <access token>".
     *
     * The three token endpoints are excluded because they are how a token is
     * OBTAINED — requiring one to get one would be a deadlock on first setup.
     * The deeplink endpoint is excluded because NBC's parameter table lists
     * only Content-Type for it; the token is attached opportunistically when we
     * happen to hold one (see authHeaders) rather than being required, since
     * inventing a requirement the document does not state is how an integration
     * ends up wrong in a way nobody can debug.
     */
    private const AUTHENTICATED_ENDPOINTS = [
        self::EP_CHECK_MD5,
        self::EP_CHECK_HASH,
        self::EP_CHECK_SHORT_HASH,
        self::EP_CHECK_ACCOUNT,
    ];

    // ---- why a request exists. Required, logged verbatim, never guessed --

    public const REASON_TOKEN_REQUEST = 'token_request';

    public const REASON_TOKEN_VERIFY = 'token_verify';

    public const REASON_TOKEN_RENEW = 'token_renew';

    public const REASON_PAYMENT_VERIFICATION = 'payment_verification';

    public const REASON_DEEPLINK = 'deeplink';

    public const REASON_ACCOUNT_CHECK = 'account_check';

    public const REASON_MANUAL_DIAGNOSTIC = 'manual_diagnostic';

    public const REASONS = [
        self::REASON_TOKEN_REQUEST,
        self::REASON_TOKEN_VERIFY,
        self::REASON_TOKEN_RENEW,
        self::REASON_PAYMENT_VERIFICATION,
        self::REASON_DEEPLINK,
        self::REASON_ACCOUNT_CHECK,
        self::REASON_MANUAL_DIAGNOSTIC,
    ];

    /**
     * Reasons a provider BACKOFF must not stop.
     *
     * The backoff exists to stop an application re-discovering the same dead
     * credential once per cooldown, each discovery a metered call. But the
     * token endpoints are how a dead credential gets REPLACED — a 401 backs the
     * token off, and if that backoff also blocked renewal the integration could
     * never recover on its own; it would need a human to clear a cache before
     * the scheduled renewal could even be attempted.
     *
     * A manual diagnostic is here for the same reason: it is an operator at the
     * keyboard deliberately re-testing something they have just tried to fix,
     * and refusing them locally leaves them no way to discover the fix worked.
     *
     * None of these are exempt from the 429 or the daily ceiling, which are
     * about the ALLOWANCE rather than the credential — and no amount of
     * urgency makes it sensible to spend a request the day cannot afford.
     */
    private const BACKOFF_EXEMPT_REASONS = [
        self::REASON_TOKEN_REQUEST,
        self::REASON_TOKEN_VERIFY,
        self::REASON_TOKEN_RENEW,
        self::REASON_MANUAL_DIAGNOSTIC,
    ];

    /** Reasons that are ABOUT one payment session and cannot be made without it. */
    private const ROW_BOUND_REASONS = [
        self::REASON_PAYMENT_VERIFICATION,
        self::REASON_DEEPLINK,
    ];

    /** The budgets a call can be charged to — one integrator token each. */
    public const TARGETS = ['platform', 'merchant'];

    // ---- why a request was refused --------------------------------------

    public const BLOCK_DISABLED = 'bakong_disabled';

    public const BLOCK_DEMO = 'demo_mode';

    public const BLOCK_NOT_CONFIGURED = 'not_configured';

    public const BLOCK_INVALID_REQUEST = 'invalid_request';

    public const BLOCK_NO_TOKEN = 'no_token';

    public const BLOCK_NO_ACTIVE_PAYMENT = 'no_active_payment';

    public const BLOCK_RATE_LIMITED = 'rate_limited';

    public const BLOCK_UPSTREAM_EXHAUSTED = 'upstream_quota_exhausted';

    public const BLOCK_PROVIDER_BACKOFF = 'provider_backoff';

    public const BLOCK_COOLDOWN = 'verify_cooldown';

    public const BLOCK_ATTEMPTS = 'max_attempts_reached';

    public const BLOCK_BUDGET = 'daily_budget_exhausted';

    public const BLOCK_BUDGET_UNAVAILABLE = 'budget_unavailable';

    /**
     * Bakong errorCode values that describe OUR ACCESS rather than the payer's
     * money — the ones worth backing a credential off for.
     *
     *   6  Unauthorized
     *   10 Not registered yet
     *
     * Deliberately NOT included: 1 (transaction could not be found), which is
     * the honest pre-payment answer and the most common response this
     * integration will ever see, and 3 (transaction failed), which is a
     * statement about a real transaction. Backing off on either would make a
     * working integration stop asking.
     */
    private const CREDENTIAL_ERROR_CODES = [6, 10];

    /**
     * Bakong errorCodes that mean THE DAY'S ALLOWANCE IS GONE.
     *
     * 17 is not in the v1.0.2 document — its published list stops at 11 — and
     * that gap cost real money here. The gateway answers HTTP 200 with
     * responseCode 1, errorCode 17 and "Daily request limit of 100 exceeded.
     * Please try again tomorrow.", which this client read as a generic refusal
     * and kept re-asking once per cooldown, all day, against a token that had
     * nothing left. Every one of those retries is charged exactly like a sale.
     *
     * Coding strictly to a published list is what made this possible, so the
     * message is matched as well as the code — see isQuotaRefusal().
     */
    private const QUOTA_ERROR_CODES = [17];

    public function __construct(private ?BakongQuotaLedger $ledger = null)
    {
        $this->ledger = $ledger ?? new BakongQuotaLedger;
    }

    public function ledger(): BakongQuotaLedger
    {
        return $this->ledger;
    }

    // ---------------------------------------------------------- feature gate

    /**
     * Is the Bakong integration switched on for this installation?
     *
     * Static because the answer is needed where resolving a service is not
     * appropriate — the scheduler's skip() closure, a command's early return, a
     * Blade condition.
     *
     * Demo mode counts as enabled: it is a purely local simulation that builds
     * a real KHQR payload in PHP and settles it on a timer, so it cannot reach
     * anyone however hard it tries, and calling it disabled would only break
     * local demonstrations without protecting anything.
     */
    public static function featureEnabled(): bool
    {
        return ((bool) config('bakong.enabled') && self::baseUrl() !== null)
            || (bool) config('bakong.demo');
    }

    /**
     * The API root, but ONLY if it is actually a URL.
     *
     * `filled()` was not enough, and the way that failed is worth recording: an
     * operator pasted their ACCESS TOKEN into BAKONG_API_BASE_URL. Every check
     * passed — the value was present, the token import had separately succeeded
     * — so diagnostics reported "this installation can take a Bakong payment"
     * while there was no endpoint configured at all. A false green on a payment
     * integration is worse than a red one.
     *
     * Two things follow from validating here rather than at the call site.
     * Nothing can be sent to a non-URL, so a mispaste is refused as
     * `not_configured` instead of failing somewhere deep in the HTTP client with
     * an unreadable message. And because the commonest reason this value is
     * malformed is that a SECRET was pasted into it, the value must never be
     * echoed back — see baseUrlForDisplay().
     */
    public static function baseUrl(): ?string
    {
        $url = trim((string) config('bakong.base_url'));

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        return rtrim($url, '/');
    }

    /**
     * How the configured base URL may be shown to a human.
     *
     * A VALID url is printed as-is — it is not a secret and an operator needs to
     * read it. An INVALID one is never printed, because the most likely reason
     * it is invalid is that a credential was pasted into the wrong variable, and
     * a diagnostics table that helpfully echoes it puts that credential into a
     * terminal scrollback, a screenshot and a support thread.
     */
    public static function baseUrlForDisplay(): string
    {
        if (self::baseUrl() !== null) {
            return (string) self::baseUrl();
        }

        return trim((string) config('bakong.base_url')) === ''
            ? __('messages.bakong_base_url_missing')
            : __('messages.bakong_base_url_invalid');
    }

    /**
     * May a request actually leave this server?
     *
     * Deliberately NARROWER than featureEnabled(): demo mode may run the flows
     * but must never transmit. Nothing legitimately reaches call() in demo
     * (every branch short-circuits into a local verdict first), so this is
     * defence against a future call site that forgets — which is the entire
     * reason the gate exists rather than being left to call sites.
     */
    public static function providerCallsPermitted(): bool
    {
        return (bool) config('bakong.enabled')
            && ! (bool) config('bakong.demo')
            && self::baseUrl() !== null;
    }

    // ------------------------------------------------------------ the gates

    /**
     * Make one Bakong request, or refuse it.
     *
     * @param  string  $reason  one of the REASON_* constants: why this request exists
     * @param  string  $endpoint  one of the EP_* constants
     * @param  array<string, mixed>  $payload  JSON body, exactly as documented
     * @param  KhqrPayment|null  $row  the payment this is about; required for row-bound reasons
     * @param  string  $target  the allowance this is charged to
     * @param  int  $sessionGrace  minutes past a QR's expiry that still count as live (reconcile's rescue window)
     */
    public function call(
        string $reason,
        string $endpoint,
        array $payload = [],
        ?KhqrPayment $row = null,
        string $target = 'platform',
        int $sessionGrace = 0,
        ?int $accountId = null,
    ): BakongResult {
        // A merchant-target call spends the LANDLORD's token against the
        // LANDLORD's allowance, so it must name the account. Refusing here
        // rather than falling back to the platform credential is the whole
        // point: NBC meters per token, and quietly borrowing the platform's
        // would put every landlord's tenants on the same ~80/day ceiling the
        // subscriptions depend on — and confirm one party's money with
        // another party's credential.
        if ($target === 'merchant' && $accountId === null) {
            return $this->refuse(self::BLOCK_INVALID_REQUEST, $reason, $endpoint, $target, $row, null);
        }
        // ---- Gate 1: the master switch. Nothing gets past this. ----
        if (! (bool) config('bakong.enabled')) {
            return $this->refuse(self::BLOCK_DISABLED, $reason, $endpoint, $target, $row, $accountId);
        }

        // ---- Gate 2: demo simulates the whole flow and must never transmit.
        // Reaching here in demo is a bug in the caller, not a configuration,
        // hence a distinct reason rather than folding it into 'disabled'.
        if ((bool) config('bakong.demo')) {
            return $this->refuse(self::BLOCK_DEMO, $reason, $endpoint, $target, $row, $accountId);
        }

        // ---- Gate 3: nowhere to send it. NBC writes the root as {{baseUrl}}
        // and never publishes it, so it is never guessed here — and a
        // half-configured .env is the ordinary state of a machine mid-setup.
        // Not merely "is something set" — is it a URL. An access token pasted
        // here passed the old check and produced a confident all-green report
        // with no endpoint configured.
        if (self::baseUrl() === null) {
            return $this->refuse(self::BLOCK_NOT_CONFIGURED, $reason, $endpoint, $target, $row, $accountId);
        }

        // ---- Gate 4: a request that cannot be accounted for is not made. ----
        if (! $this->wellFormed($reason, $endpoint, $target, $row)) {
            return $this->refuse(self::BLOCK_INVALID_REQUEST, $reason, $endpoint, $target, $row, $accountId);
        }

        // ---- Gate 5: nothing to authenticate with. An EXPIRED token counts as
        // absent: Bakong charges a 401 exactly like a successful call, so
        // sending a credential we can already see is dead spends the allowance
        // to be told what we knew for free.
        $token = $this->usableToken($target, $accountId);

        if (in_array($endpoint, self::AUTHENTICATED_ENDPOINTS, true) && $token === null) {
            return $this->refuse(self::BLOCK_NO_TOKEN, $reason, $endpoint, $target, $row, $accountId);
        }

        // ---- Gate 6: is there actually a payment session to ask about? ----
        if ($row !== null && in_array($reason, self::ROW_BOUND_REASONS, true)
            && ! $row->isActiveBakongSession($sessionGrace)) {
            return $this->refuse(self::BLOCK_NO_ACTIVE_PAYMENT, $reason, $endpoint, $target, $row, $accountId);
        }

        // ---- Gates 7 & 8: this credential already told us no. ----
        // See BACKOFF_EXEMPT_REASONS: the token endpoints and the operator's
        // manual diagnostic are the things that FIX a backed-off credential, so
        // a backoff must not be what stops them. Neither is exempt from the 429
        // or the budget, which are about the allowance, not the credential.
        if ($this->ledger->isRateLimited($target, $accountId)) {
            return $this->refuse(self::BLOCK_RATE_LIMITED, $reason, $endpoint, $target, $row, $accountId);
        }

        // NBC itself has said the day is over. NOTHING is exempt from this —
        // not a token renewal, not the operator's own diagnostic — because
        // every one of them would be charged and every one would be refused.
        // Unlike our own ceiling this counts spend we cannot see: the token is
        // shared, so the allowance can be gone while our ledger reads 6 of 80.
        if ($this->ledger->upstreamExhausted($target, $accountId) !== null) {
            return $this->refuse(self::BLOCK_UPSTREAM_EXHAUSTED, $reason, $endpoint, $target, $row, $accountId);
        }

        if (! in_array($reason, self::BACKOFF_EXEMPT_REASONS, true)
            && $this->ledger->activeBackoff($target, $accountId) !== null) {
            return $this->refuse(self::BLOCK_PROVIDER_BACKOFF, $reason, $endpoint, $target, $row, $accountId);
        }

        // ---- Gate 9: one question about one transaction at a time. ----
        // Claimed ATOMICALLY and BEFORE the request, so it holds across tabs,
        // PHP-FPM workers, queue workers and the reconcile run alike.
        $slotClaimed = false;

        if ($row !== null && $reason === self::REASON_PAYMENT_VERIFICATION) {
            if (! $this->ledger->claimVerifySlot($row->transaction_id)) {
                return $this->refuse(self::BLOCK_COOLDOWN, $reason, $endpoint, $target, $row, $accountId);
            }

            $slotClaimed = true;
        }

        // ---- Gate 10: this session has cost enough. ----
        // Checked AFTER the slot so a session at its cap does not also hold the
        // cooldown open, and BEFORE the budget so a capped session never
        // consumes a reservation it is not allowed to use.
        if ($row !== null && $this->attemptsExhausted($row)) {
            if ($slotClaimed) {
                $this->ledger->releaseVerifySlot($row->transaction_id);
            }

            return $this->refuse(self::BLOCK_ATTEMPTS, $reason, $endpoint, $target, $row, $accountId);
        }

        // ---- Gate 11: the day's allowance. LAST, and FAILS CLOSED. ----
        $reservation = $this->ledger->reserve($target, $reason, $endpoint, $row?->id, $accountId);

        if (is_string($reservation)) {
            if ($slotClaimed) {
                $this->ledger->releaseVerifySlot($row->transaction_id);
            }

            return $this->refuse($reservation, $reason, $endpoint, $target, $row, $accountId);
        }

        return $this->perform($reservation, $reason, $endpoint, $payload, $token, $target, $row, $slotClaimed, $accountId);
    }

    // ------------------------------------------------------------ the request

    /**
     * Every gate has passed and the call has been reserved. Make it.
     *
     * There is deliberately NO RETRY. The ceiling counts upstream requests, so
     * an automatic retry spends two against one reservation — and a request
     * that timed out has still been charged. A failure is left to whatever the
     * cooldown next allows.
     */
    private function perform(
        BakongApiCall $reservation,
        string $reason,
        string $endpoint,
        array $payload,
        ?string $token,
        string $target,
        ?KhqrPayment $row,
        bool $slotClaimed,
        ?int $accountId = null,
    ): BakongResult {
        $url = self::baseUrl().$endpoint;
        $startedAt = microtime(true);

        Log::info('Bakong provider request', [
            'endpoint' => $endpoint,
            'reason' => $reason,
            'target' => $target,
            'transaction' => $row?->transaction_id,
            'spent_today' => $this->ledger->spentToday($target, $accountId),
            'limit' => $this->ledger->limit(),
            // The token is never logged, not even truncated — only a stable
            // fingerprint, so two log lines can be told to be the same
            // credential without the credential being in the log.
            'token' => $token === null ? 'none' : substr(hash('sha256', $token), 0, 12),
        ]);

        try {
            /** @var Response $response */
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders($this->authHeaders($endpoint, $token))
                ->connectTimeout(max(1, (int) config('bakong.connect_timeout', 3)))
                ->timeout(max(1, (int) config('bakong.timeout', 8)))
                ->retry(0)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            $this->ledger->stamp($reservation, [
                'outcome' => 'error',
                'duration_ms' => $this->elapsed($startedAt),
            ]);

            if ($slotClaimed && $row !== null) {
                $this->ledger->releaseVerifySlot($row->transaction_id);
            }

            // A transport exception quotes the full request URL, which for some
            // endpoints carries identifiers we would rather not scatter through
            // the log. Redacted like any other provider text.
            Log::warning('Bakong provider transport failure', [
                'endpoint' => $endpoint,
                'reason' => $reason,
                'error' => self::redact($e->getMessage()),
            ]);

            // NOT a backoff: a timeout says nothing about the token, and the
            // cooldown already prevents an immediate retry.
            return BakongResult::failed($e);
        }

        $result = BakongResult::answered($response);

        $this->ledger->stamp($reservation, [
            'http_status' => $response->status(),
            'response_code' => $result->responseCode(),
            'error_code' => $result->errorCode(),
            'outcome' => $result->succeeded() ? 'ok' : 'refused',
            'duration_ms' => $this->elapsed($startedAt),
        ]);

        if ($slotClaimed && $row !== null) {
            $this->ledger->releaseVerifySlot($row->transaction_id);
        }

        Log::info('Bakong provider response', [
            'endpoint' => $endpoint,
            'reason' => $reason,
            'status' => $response->status(),
            'response_code' => $result->responseCode(),
            'error_code' => $result->errorCode(),
            'transaction' => $row?->transaction_id,
        ]);

        $this->reactToRefusal($result, $target, $reason, $accountId);

        return $result;
    }

    /**
     * Attach the access token, but only where the document says it belongs.
     *
     * The deeplink endpoint is the interesting case: NBC's parameter table
     * lists only Content-Type for it, yet every other data endpoint is
     * authenticated. Sending a token we already hold costs nothing and is
     * harmless if ignored; REQUIRING one would invent a rule the document does
     * not state and would fail setup on a step that may well not need it.
     *
     * @return array<string, string>
     */
    private function authHeaders(string $endpoint, ?string $token): array
    {
        if ($token === null) {
            return [];
        }

        $optional = $endpoint === self::EP_DEEPLINK;

        if (! in_array($endpoint, self::AUTHENTICATED_ENDPOINTS, true) && ! $optional) {
            // The three token endpoints: sending a (possibly stale) credential
            // to the endpoint whose job is issuing one can only confuse it.
            return [];
        }

        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * Did Bakong just say something that will be true again in a minute?
     *
     * Only findings about OUR ACCESS back the credential off. A 429 is the
     * allowance and gets its own, shorter backoff so a spent minute-rate does
     * not silence the token for a quarter of an hour.
     */
    private function reactToRefusal(BakongResult $result, string $target, string $reason, ?int $accountId = null): void
    {
        $status = $result->status();

        if ($status === 429) {
            $this->ledger->rateLimited($target, $result->message() ?: 'HTTP 429', $accountId);

            return;
        }

        if ($status !== null && ($status >= 500 || in_array($status, [401, 403], true))) {
            $this->ledger->backOff(
                $target,
                max(1, (int) config('bakong.failure_backoff', 15)),
                'HTTP '.$status.' '.self::redact($result->message(), 120),
                $accountId
            );

            return;
        }

        $errorCode = $result->errorCode();
        $message = $result->message();

        // THE DAY IS OVER. Latch it until midnight rather than re-discovering
        // it once per cooldown for the rest of the day — which is what this
        // integration did until the allowance ran out and the logs filled with
        // the same sentence 60 seconds apart.
        if (($errorCode !== null && in_array($errorCode, self::QUOTA_ERROR_CODES, true))
            || self::isQuotaRefusal($message)) {
            $this->ledger->markUpstreamExhausted($target, self::redact($message, 160), $accountId);

            return;
        }

        // A 2xx whose envelope reports an access problem. errorCode is what
        // distinguishes this from the honest "transaction could not be found"
        // that this integration will see far more often than anything else.
        if ($errorCode !== null && in_array($errorCode, self::CREDENTIAL_ERROR_CODES, true)) {
            $this->ledger->backOff(
                $target,
                max(1, (int) config('bakong.failure_backoff', 15)),
                'errorCode '.$errorCode.' '.self::redact($result->message(), 120),
                $accountId
            );
        }
    }

    /**
     * Does this message say the allowance is spent?
     *
     * The needles are deliberately PHRASES, never the bare word "limit". The
     * KHQRPay integration learned this the hard way: a gateway answering
     * "amount below minimum limit" is describing one request's amount, not the
     * account, and matching it would shut down checkout on a perfectly healthy
     * token. Every phrase here has to be about a COUNT over a PERIOD.
     */
    public static function isQuotaRefusal(string $message): bool
    {
        $haystack = strtolower($message);

        foreach ([
            'daily request limit', 'daily limit', 'request limit', 'limit exceeded',
            'exceeded limit', 'limit reached', 'out of limit', 'over limit',
            'quota', 'too many request', 'rate limit', 'ratelimit', 'throttl',
        ] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Is the upstream allowance known to be spent right now? @return array{until: \Carbon\Carbon, why: string}|null */
    public function upstreamExhausted(string $target = 'platform'): ?array
    {
        return $this->ledger->upstreamExhausted($target);
    }

    // ------------------------------------------------------------- the rules

    /**
     * Can this request be accounted for at all?
     *
     * An unaccountable request used to become a budget bucket of its own while
     * still being signed with the real credential — a second allowance nobody
     * had set, spent on the one token that mattered. So an unknown reason,
     * endpoint or target is refused outright rather than defaulted.
     */
    private function wellFormed(string $reason, string $endpoint, string $target, ?KhqrPayment $row): bool
    {
        if (! in_array($reason, self::REASONS, true)) {
            return false;
        }

        if (! in_array($endpoint, self::ENDPOINTS, true)) {
            return false;
        }

        if (! in_array($target, self::TARGETS, true)) {
            return false;
        }

        if (in_array($reason, self::ROW_BOUND_REASONS, true) && $row === null) {
            return false;
        }

        // A row whose settlement target is not the budget being charged would
        // spend one landlord's allowance on another's payment.
        if ($row !== null && $row->settlement_target !== $target) {
            return false;
        }

        return true;
    }

    /**
     * Has this one session already cost as many live calls as any session needs?
     *
     * The cooldown caps the RATE and qr_ttl caps the WINDOW; this caps their
     * PRODUCT — the number that matters when something goes wrong in a way
     * neither of the other two anticipated. Counted from the durable ledger
     * rather than the cache, so a cache flush cannot reset a runaway session.
     */
    private function attemptsExhausted(KhqrPayment $row): bool
    {
        $cap = (int) config('bakong.max_verify_attempts', 0);

        if ($cap <= 0) {
            return false;
        }

        try {
            return BakongApiCall::query()
                ->where('khqr_payment_id', $row->id)
                ->where('allowed', true)
                ->count() >= $cap;
        } catch (\Throwable $e) {
            // Unreadable ledger cannot prove the cap is reached, and the budget
            // gate below still fails closed. Let it through to that.
            return false;
        }
    }

    private function usableToken(string $target, ?int $accountId = null): ?string
    {
        // The landlord's own credential. Null covers "never set", "switched
        // off" and "expired" alike — every one of them means there is nothing
        // to spend, and an expired token is ABSENT rather than broken because
        // Bakong charges a 401 exactly like a success.
        if ($target === 'merchant') {
            if ($accountId === null) {
                return null;
            }

            try {
                return app(MerchantBakongCredentials::class)->tokenFor($accountId);
            } catch (\Throwable $e) {
                return null;
            }
        }

        try {
            $row = BakongToken::current();
        } catch (\Throwable $e) {
            return null;
        }

        return $row !== null && $row->isUsable() ? (string) $row->token : null;
    }

    // --------------------------------------------------------------- refusal

    private function refuse(string $blockedReason, string $reason, string $endpoint, string $target, ?KhqrPayment $row, ?int $accountId = null): BakongResult
    {
        $this->ledger->recordBlocked(
            in_array($target, self::TARGETS, true) ? $target : 'platform',
            in_array($reason, self::REASONS, true) ? $reason : 'unknown',
            in_array($endpoint, self::ENDPOINTS, true) ? $endpoint : 'unknown',
            $row?->id,
            $blockedReason,
            $accountId,
        );

        Log::info('Bakong provider request blocked', [
            'blocked' => $blockedReason,
            'reason' => $reason,
            'endpoint' => $endpoint,
            'target' => $target,
            'transaction' => $row?->transaction_id,
        ]);

        return BakongResult::blocked($blockedReason);
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * Strip anything credential-shaped out of provider text before it is logged
     * or shown to a human.
     *
     * Transport exceptions quote the request URL, and a response body can echo
     * back a header. Neither is supposed to contain the token, and neither is
     * trusted not to.
     */
    public static function redact(string $text, int $limit = 300): string
    {
        $text = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $text) ?? $text;
        // A bare JWT (three dot-separated base64url segments) anywhere in the text.
        $text = preg_replace('/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/', '[jwt]', $text) ?? $text;

        return Str::limit(trim($text), $limit);
    }
}
