<?php

namespace App\Services\Payment;

use App\Models\KhqrPayment;
use App\Services\RevenueExpense\KhqrCredentials;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * THE ONLY PLACE IN THIS APPLICATION THAT MAY TALK TO khqr.cc / BAKONG.
 *
 * Every outbound KHQR request — QR minting, transaction verification, both
 * checkout preflight probes, diagnostics — is handed to call() as a closure.
 * Nothing else in app/ calls Http:: against the gateway, and nothing new should:
 * the point of this class is that the protection cannot be forgotten by the next
 * person to add a provider call, because the request does not leave the process
 * unless this method lets it.
 *
 * Why it exists at all: Bakong meters the upstream token per CALENDAR DAY (this
 * account's allowance is ~100 requests) and charges a REFUSED request exactly
 * like a successful one. So the expensive failure mode is not a bug in any one
 * call site — it is the sum of a scheduler, three browser pollers, two preflight
 * probes and a diagnostics page each individually looking reasonable. Guards
 * scattered across those call sites were the old design, and what it produced
 * was a token drained overnight with nobody touching the app.
 *
 * Every gate is a refusal BEFORE the request, checked in this order — cheapest
 * and most absolute first, the one that spends the day's allowance last:
 *
 *  1. khqr_disabled          — services.khqrpay.enabled is false. Absolute: no
 *                              reason, row, budget or override gets past it.
 *  2. demo_mode              — the local simulation must never transmit.
 *  3. invalid_request        — an unknown reason, an unknown settlement target,
 *                              a verification with no row to verify, or a row
 *                              whose target is not the budget being charged.
 *  4. invalid_credentials    — nothing to sign with; the gateway could only 404.
 *  5. no_active_payment      — the row is not a payment session this request can
 *                              be about. A database row is not a payment; see
 *                              KhqrPayment::isActiveKhqrSession() and
 *                              KhqrPayment::isMintableKhqrSession().
 *  6. rate_limited           — this credential answered 429 recently.
 *  7. provider_backoff       — this credential refused recently in a way that
 *                              will still be true on the next request (401/403/
 *                              422/5xx, "Bakong Token Required", a spent quota).
 *  8. verify_cooldown        — the same transaction was asked about inside
 *                              KHQRPAY_VERIFY_COOLDOWN, or is being asked about
 *                              RIGHT NOW by another process. Claimed atomically.
 *  9. max_attempts_reached   — this one session has already cost as many calls
 *                              as any session needs.
 * 10. daily_budget_exhausted — the per-target ceiling is reached. Reserved
 *                              atomically, and FAILS CLOSED when the ledger
 *                              cannot be read (budget_unavailable).
 *
 * Every allowed request is logged with the REASON it happened, and every block
 * with the reason it did not. That log — and the per-reason counter behind
 * `php artisan khqr:usage` — is how a future accidental call gets found, so a
 * request with no reason is not allowed.
 */
class KhqrProviderClient
{
    /** Why a request is being made. Required, and logged verbatim. */
    public const REASON_PAYMENT_CREATION = 'payment_creation';

    public const REASON_PAYMENT_VERIFICATION = 'payment_verification';

    public const REASON_WEBHOOK_RECOVERY = 'webhook_recovery';

    public const REASON_CHECKOUT_PREFLIGHT = 'checkout_preflight';

    public const REASON_MANUAL_DIAGNOSTIC = 'manual_diagnostic';

    public const REASONS = [
        self::REASON_PAYMENT_CREATION,
        self::REASON_PAYMENT_VERIFICATION,
        self::REASON_WEBHOOK_RECOVERY,
        self::REASON_CHECKOUT_PREFLIGHT,
        self::REASON_MANUAL_DIAGNOSTIC,
    ];

    /** Reasons that are ABOUT one payment session and cannot be made without it. */
    private const ROW_BOUND_REASONS = [
        self::REASON_PAYMENT_CREATION,
        self::REASON_PAYMENT_VERIFICATION,
        self::REASON_WEBHOOK_RECOVERY,
    ];

    /** The budgets a call can be charged to — one Bakong token each. */
    public const TARGETS = ['platform', 'merchant'];

    /** Why a request was refused. */
    public const BLOCK_DISABLED = 'khqr_disabled';

    public const BLOCK_DEMO = 'demo_mode';

    public const BLOCK_INVALID_REQUEST = 'invalid_request';

    public const BLOCK_INVALID_CREDENTIALS = 'invalid_credentials';

    public const BLOCK_NO_ACTIVE_PAYMENT = 'no_active_payment';

    public const BLOCK_RATE_LIMITED = 'rate_limited';

    public const BLOCK_PROVIDER_BACKOFF = 'provider_backoff';

    public const BLOCK_COOLDOWN = 'verify_cooldown';

    public const BLOCK_ATTEMPTS = 'max_attempts_reached';

    public const BLOCK_BUDGET = 'daily_budget_exhausted';

    public const BLOCK_BUDGET_UNAVAILABLE = 'budget_unavailable';

    /**
     * How long a verification slot is held when no cooldown is configured: long
     * enough to cover one request's own timeouts, so two processes can still
     * never ask about the same transaction at the same moment.
     */
    private const IN_FLIGHT_SECONDS = 30;

    /**
     * Is the KHQR feature switched on for this installation?
     *
     * Static because the answer is needed in places that have no business
     * resolving a service — the scheduler's skip() closure, a command's early
     * return, a Blade condition.
     *
     * Demo mode counts as enabled: it is a purely local simulation that builds
     * an example QR in PHP and auto-confirms it on a timer, so it cannot reach a
     * provider however hard it tries, and treating it as disabled would only
     * break local demonstrations without protecting anything. Demo is itself
     * hard-disabled in production (config/services.php).
     */
    public static function featureEnabled(): bool
    {
        return (bool) config('services.khqrpay.enabled')
            || (bool) config('services.khqrpay.demo');
    }

    /**
     * May a request actually leave this server?
     *
     * Deliberately NARROWER than featureEnabled(): demo mode is allowed to run
     * the KHQR flows but must never transmit. Nothing legitimately reaches call()
     * in demo (every branch short-circuits into fillDemo / a local verdict
     * first), so this is defence against a future call site that forgets — the
     * same reason the gate exists at all.
     */
    public static function providerCallsPermitted(): bool
    {
        return (bool) config('services.khqrpay.enabled')
            && ! (bool) config('services.khqrpay.demo');
    }

    /**
     * Perform one provider request, or refuse it.
     *
     * $perform is the actual Http:: call, handed in as a closure so that this
     * method — and not the call site — decides whether it ever runs. It is only
     * invoked once every gate has passed and the call has been counted.
     *
     * @param  string  $reason  one of the REASON_* constants: why this request exists
     * @param  string|null  $target  settlement target ('platform'|'merchant') the quota is charged to
     * @param  KhqrPayment|null  $row  the payment being asked about; required for creation/verification/recovery
     * @param  callable(): Response  $perform
     * @param  int  $sessionGrace  minutes past a QR's expiry that still count as live (khqr:reconcile's rescue window)
     */
    public function call(
        string $reason,
        ?string $target,
        ?KhqrPayment $row,
        callable $perform,
        ?KhqrCredentials $creds = null,
        int $sessionGrace = 0,
    ): KhqrProviderResult {
        $target = (string) $target;

        // ---- Gate 1: the master switch. Nothing gets past this. ----
        if (! (bool) config('services.khqrpay.enabled')) {
            return $this->refuse(self::BLOCK_DISABLED, $reason, $target, $row, $creds);
        }

        // Demo mode simulates the whole flow locally and must never transmit.
        // Reaching here in demo is a bug in the caller, not a configuration —
        // hence a distinct reason rather than folding it into 'khqr_disabled'.
        if ((bool) config('services.khqrpay.demo')) {
            return $this->refuse(self::BLOCK_DEMO, $reason, $target, $row, $creds);
        }

        // ---- Gate 3: a request that cannot be accounted for is not made. ----
        // An unknown target used to become an 'unknown' budget bucket of its
        // own, while the row was still signed with the PLATFORM credentials —
        // a second allowance nobody had set, spent on the real token.
        if (! $this->wellFormed($reason, $target, $row)) {
            return $this->refuse(self::BLOCK_INVALID_REQUEST, $reason, $target, $row, $creds);
        }

        // ---- Gate 4: nothing to sign with. ----
        if ($creds === null || ! $creds->isConfigured()) {
            return $this->refuse(self::BLOCK_INVALID_CREDENTIALS, $reason, $target, $row, $creds);
        }

        // ---- Gate 5: the row must be a payment session THIS request is about. ----
        // The rule the whole audit turns on: AMS must never contact Bakong
        // merely because a KHQR record exists.
        if ($row !== null && ! $this->sessionPermits($reason, $row, $sessionGrace)) {
            return $this->refuse(self::BLOCK_NO_ACTIVE_PAYMENT, $reason, $target, $row, $creds);
        }

        // ---- Gate 6: a 429 the provider already sent us. ----
        if ($this->rateLimited($creds)) {
            return $this->refuse(self::BLOCK_RATE_LIMITED, $reason, $target, $row, $creds);
        }

        // ---- Gate 7: a refusal that will still be true on the next request. ----
        // An operator explicitly running the live diagnostics is the one caller
        // allowed past it — they are asking precisely because they may have just
        // fixed the profile, and the rate limit and the budget below still apply.
        if ($reason !== self::REASON_MANUAL_DIAGNOSTIC && $this->providerBackoff($creds) !== null) {
            return $this->refuse(self::BLOCK_PROVIDER_BACKOFF, $reason, $target, $row, $creds);
        }

        // ---- Gate 8: one request per transaction per cooldown, atomically. ----
        $slot = $row !== null ? $this->claimSessionSlot($reason, $row) : null;
        if ($row !== null && $slot === null) {
            return $this->refuse(self::BLOCK_COOLDOWN, $reason, $target, $row, $creds);
        }

        try {
            // ---- Gate 9: this session has been asked about enough times. ----
            if ($row !== null && $reason !== self::REASON_PAYMENT_CREATION && $this->attemptsExhausted($row)) {
                return $this->refuse(self::BLOCK_ATTEMPTS, $reason, $target, $row, $creds);
            }

            // ---- Gate 10: atomically reserve one call from the day's ceiling. ----
            // LAST, so that no refusal above ever spends a slot of the allowance,
            // and BEFORE the request, so a timeout is still charged to it.
            $budget = $this->reserveBudgetCall($target, $reason);
            if ($budget !== true) {
                return $this->refuse($budget, $reason, $target, $row, $creds);
            }

            if ($row !== null && $reason !== self::REASON_PAYMENT_CREATION) {
                $this->recordAttempt($row);
            }

            Log::info('KHQR provider request', [
                'reason' => $reason,
                'provider' => 'khqrpay',
                'target' => $target,
                'transaction_id' => $row?->transaction_id,
                'profile' => $creds->profileId,
                'spent_today' => self::callsOn($target),
                'daily_budget' => (int) config('services.khqrpay.daily_budget', 0),
            ]);

            $startedAt = microtime(true);

            try {
                $response = $perform();
            } catch (\Throwable $e) {
                // A timeout or a dropped connection is not a verdict about the
                // payment, and it is not retried here either: the next attempt
                // is whatever the cooldown next allows.
                Log::warning('KHQR provider request failed in transport', [
                    'reason' => $reason,
                    'target' => $target,
                    'transaction_id' => $row?->transaction_id,
                    'profile' => $creds->profileId,
                    'msg' => self::redact($e->getMessage()),
                ]);

                return KhqrProviderResult::failed($e);
            }

            $this->observeResponse($response, $reason, $target, $row, $creds, $startedAt);

            return KhqrProviderResult::answered($response);
        } finally {
            // With a cooldown the slot deliberately outlives the request — that
            // IS the cooldown. Without one it is only an in-flight guard, and is
            // handed back the moment the request is over.
            if ($slot !== null && $this->verifyCooldownSeconds() === 0 && $reason !== self::REASON_PAYMENT_CREATION) {
                $this->forget($slot);
            }
        }
    }

    /** Log a refusal and hand it back. No request is made, no quota spent. */
    private function refuse(string $block, string $reason, string $target, ?KhqrPayment $row, ?KhqrCredentials $creds): KhqrProviderResult
    {
        // debug for the blocks that are the NORMAL steady state — a KHQR-disabled
        // install, a stale row, a poll landing inside the cooldown. At info they
        // would write one line per browser poll for a decision working as
        // configured. The rest are warnings: they mean a live payment is not
        // being confirmed right now.
        $level = in_array($block, [self::BLOCK_DISABLED, self::BLOCK_NO_ACTIVE_PAYMENT, self::BLOCK_COOLDOWN], true)
            ? 'debug'
            : 'warning';

        Log::{$level}('KHQR provider request blocked', [
            'reason' => $block,
            'requested_for' => $reason,
            'target' => $target,
            'transaction_id' => $row?->transaction_id,
            'profile' => $creds?->profileId,
        ]);

        return KhqrProviderResult::blocked($block);
    }

    /** Is this a request that can be charged to a budget and reasoned about? */
    private function wellFormed(string $reason, string $target, ?KhqrPayment $row): bool
    {
        if (! in_array($reason, self::REASONS, true) || ! in_array($target, self::TARGETS, true)) {
            return false;
        }

        if (in_array($reason, self::ROW_BOUND_REASONS, true)) {
            // A verification with nothing to verify, or charged to a budget other
            // than the token the row is actually signed with.
            return $row !== null && $row->settlement_target === $target;
        }

        // Preflight and diagnostics ask about the PROFILE, never a payment.
        return $row === null;
    }

    /** Does the row's state justify THIS kind of request? */
    private function sessionPermits(string $reason, KhqrPayment $row, int $sessionGrace): bool
    {
        return $reason === self::REASON_PAYMENT_CREATION
            ? $row->isMintableKhqrSession()
            : $row->isActiveKhqrSession($sessionGrace);
    }

    // ───────────────────────────────── response observation

    /**
     * React to an answer the gateway gave, for every caller at once.
     *
     * The statuses here describe OUR access, never the payer's money, and none of
     * them improves by being asked again a minute later: a 429 is a spent
     * allowance, 401/403 a bad signature, 422 the "Bakong Token Required" refusal
     * this profile gives, 5xx a profile khqr.cc will not serve. Backing the whole
     * credential off is what stops every open checkout on the same token from
     * rediscovering it once per cooldown each.
     */
    private function observeResponse(Response $response, string $reason, string $target, ?KhqrPayment $row, KhqrCredentials $creds, float $startedAt): void
    {
        $status = $response->status();

        Log::info('KHQR provider response', [
            'reason' => $reason,
            'target' => $target,
            'transaction_id' => $row?->transaction_id,
            'profile' => $creds->profileId,
            'http_status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        if ($status === 429) {
            $this->backOffRateLimit($creds, $row);

            return;
        }

        if (in_array($status, [401, 403, 422], true) || $status >= 500) {
            $this->backOffProviderFailure($creds, $status, self::responseMessage($response), $row, $reason);
        }
    }

    /** The gateway's own words, from its JSON envelope or its body — redacted and bounded. */
    public static function responseMessage(Response $response): string
    {
        $body = $response->json();
        $message = is_array($body) ? (string) ($body['responseMessage'] ?? '') : '';

        return self::redact($message !== '' ? $message : strip_tags($response->body()), 200);
    }

    // ───────────────────────────────── rate-limit backoff

    /**
     * Has this credential (profile) been rate-limited by the provider recently?
     *
     * Independent of the daily budget: it reacts to a 429 the gateway actually
     * sent, so it protects an installation that never set a ceiling at all.
     */
    public function rateLimited(KhqrCredentials $creds): bool
    {
        try {
            return (bool) Cache::get('khqr:ratelimited:'.$creds->profileId);
        } catch (\Throwable $e) {
            return false; // fail open — gates 8 and 10 still fail closed on a broken cache
        }
    }

    /**
     * Back a credential off for ALL of its open transactions after a 429, not
     * just the row that hit it: one abandoned QR polling every few seconds was
     * enough to exhaust a day's quota and make every other open checkout on the
     * same token look stuck too.
     */
    public function backOffRateLimit(KhqrCredentials $creds, ?KhqrPayment $row = null): void
    {
        $minutes = max(1, (int) config('services.khqrpay.rate_limit_backoff', 5));

        Log::warning('KHQRPay rate-limited (daily quota likely exhausted) — backing off this credential', [
            'transaction_id' => $row?->transaction_id,
            'profile' => $creds->profileId,
            'backoff_minutes' => $minutes,
        ]);

        try {
            Cache::put('khqr:ratelimited:'.$creds->profileId, true, now()->addMinutes($minutes));
        } catch (\Throwable $e) {
            // Instrumentation only — never fail a verify over the cache.
        }
    }

    // ───────────────────────────────── provider-failure backoff

    /**
     * The active failure backoff for this credential, if any.
     *
     * @return array{status: int, message: string, at: string, until: string}|null
     */
    public function providerBackoff(KhqrCredentials $creds): ?array
    {
        try {
            $state = Cache::get(self::backoffKey($creds));
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($state) ? $state : null;
    }

    /**
     * Stop asking this credential anything for KHQRPAY_FAILURE_BACKOFF minutes.
     *
     * For refusals that describe the PROFILE and will be just as true on the next
     * request: 401/403 (signature), 422 and "Bakong Token Required" (no usable
     * upstream token), a quota-worded refusal, 5xx (a profile khqr.cc will not
     * serve). Deliberately NOT for a timeout or a dropped connection: those say
     * nothing about the profile, and the per-transaction cooldown already stops
     * them being retried immediately.
     */
    public function backOffProviderFailure(KhqrCredentials $creds, int $status, string $message, ?KhqrPayment $row = null, ?string $reason = null): void
    {
        $minutes = max(1, (int) config('services.khqrpay.failure_backoff', 15));
        $until = now()->addMinutes($minutes);
        $message = self::redact($message, 200);

        Log::warning('KHQR provider backoff engaged', [
            'reason' => $reason,
            'transaction_id' => $row?->transaction_id,
            'profile' => $creds->profileId,
            'http_status' => $status,
            'message' => $message,
            'backoff_minutes' => $minutes,
        ]);

        try {
            Cache::put(self::backoffKey($creds), [
                'status' => $status,
                'message' => $message,
                'at' => Carbon::now()->toIso8601String(),
                'until' => $until->toIso8601String(),
            ], $until);
        } catch (\Throwable $e) {
            // Instrumentation only.
        }
    }

    /** A live diagnostic has just shown the profile working again. */
    public function clearProviderBackoff(KhqrCredentials $creds): void
    {
        $this->forget(self::backoffKey($creds));
    }

    private static function backoffKey(KhqrCredentials $creds): string
    {
        return 'khqr:provider:backoff:'.$creds->profileId;
    }

    // ───────────────────────────────── per-transaction cooldown

    /**
     * Atomically claim the right to make ONE request about this transaction.
     *
     * The old cooldown lived in the caller and was check-then-act: read the cache,
     * make the request, write the cache when the answer came back. Everything that
     * arrived in between — a second tab, a second user, the page's own next tick
     * while a slow gateway was still answering, a reconcile run — saw an empty
     * cache and made its own request. Cache::add() is an atomic insert-if-absent
     * on every store this app runs on (database insertOrIgnore, file flock, redis
     * SET NX), so exactly one claimant wins, and it wins BEFORE the request.
     *
     * Creation gets a one-shot slot: a transaction is minted once, ever.
     * Fails CLOSED — a cache that cannot say whether someone else is already
     * asking is not permission to ask.
     */
    private function claimSessionSlot(string $reason, KhqrPayment $row): ?string
    {
        if ($reason === self::REASON_PAYMENT_CREATION) {
            $key = 'khqr:provider:mint:'.$row->transaction_id;
            $ttl = max(60, (int) config('services.khqrpay.qr_ttl', 30) * 60);
        } else {
            // Verification and webhook recovery share one slot: both ask the same
            // question about the same transaction.
            $key = 'khqr:provider:verify-slot:'.$row->transaction_id;
            $ttl = $this->verifyCooldownSeconds() ?: self::IN_FLIGHT_SECONDS;
        }

        try {
            return Cache::add($key, Carbon::now()->getTimestamp(), $ttl) ? $key : null;
        } catch (\Throwable $e) {
            Log::error('KHQR cooldown ledger unavailable — refusing the provider request', [
                'transaction_id' => $row->transaction_id,
                'msg' => self::redact($e->getMessage()),
            ]);

            return null;
        }
    }

    /** KHQRPAY_VERIFY_COOLDOWN, in seconds (0 = in-flight guard only). */
    public function verifyCooldownSeconds(): int
    {
        return max(0, (int) config('services.khqrpay.verify_cooldown', 60));
    }

    // ───────────────────────────────── daily budget

    /**
     * Has this settlement target spent its allowance of live calls for the day?
     *
     * Per target, because platform rows spend the SaaS operator's Bakong token
     * and merchant rows spend the individual landlord's — a shared ceiling would
     * let one busy landlord lock out everyone else. A read for callers deciding
     * not to ask at all (preflight, diagnostics); the enforcing check is the
     * reservation inside call().
     */
    public function budgetExhausted(?string $target): bool
    {
        $budget = (int) config('services.khqrpay.daily_budget', 0);
        if ($budget <= 0) {
            return false;
        }

        return self::callsOn($target ?? 'unknown') >= $budget;
    }

    /**
     * Live provider calls spent on the given day (default today), optionally for
     * one settlement target. Surfaced by `php artisan khqr:usage`.
     */
    public static function callsOn(?string $target = null, ?Carbon $day = null): int
    {
        try {
            return (int) Cache::get(self::usageKey($target, $day), 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Live calls spent on the given day for one target, broken down by the
     * reason they were made — the first question of any quota investigation.
     *
     * @return array<string, int>
     */
    public static function callsByReasonOn(string $target, ?Carbon $day = null): array
    {
        $counts = [];
        foreach (self::REASONS as $reason) {
            try {
                $counts[$reason] = (int) Cache::get(self::usageKey($target, $day).':'.$reason, 0);
            } catch (\Throwable $e) {
                $counts[$reason] = 0;
            }
        }

        return $counts;
    }

    /**
     * Atomically reserve one live provider call from the target's daily budget.
     *
     * The check and the increment happen under one lock, so concurrent requests
     * cannot both see the last remaining slot. The lock covers the counter only,
     * never the provider request.
     *
     * FAILS CLOSED. This used to catch any exception — including the lock timing
     * out because many requests were waiting for it — record the call and ALLOW
     * it, which meant the ceiling gave way under exactly the concurrency it was
     * written for. A payment that cannot be verified right now stays open for the
     * webhook and the next poll; a ceiling that yields under load protects
     * nothing.
     *
     * @return true|string true when reserved, otherwise the BLOCK_* reason
     */
    private function reserveBudgetCall(string $target, string $reason): true|string
    {
        $budget = (int) config('services.khqrpay.daily_budget', 0);

        if ($budget <= 0) {
            // No ceiling configured: nothing to protect, so the counters stay
            // best-effort instrumentation.
            $this->recordCall($target, $reason);

            return true;
        }

        $lockKey = 'khqr:budget-lock:'.Carbon::now()->format('Y-m-d').':'.$target;

        try {
            $reserved = Cache::lock($lockKey, 10)->block(5, function () use ($target, $budget): bool {
                $targetKey = self::usageKey($target);

                Cache::add($targetKey, 0, now()->addDays(3));

                if ((int) Cache::get($targetKey, 0) >= $budget) {
                    return false;
                }

                if (Cache::increment($targetKey) === false) {
                    throw new \RuntimeException('budget counter could not be incremented');
                }

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('KHQR budget ledger unavailable — refusing the provider request', [
                'target' => $target,
                'requested_for' => $reason,
                'msg' => self::redact($e->getMessage()),
            ]);

            return self::BLOCK_BUDGET_UNAVAILABLE;
        }

        if (! $reserved) {
            return self::BLOCK_BUDGET;
        }

        // The ceiling is the target counter above; these are reporting only.
        $this->recordCall(null, $reason, $target);

        return true;
    }

    /**
     * Count one live call — in total, for its target, and by reason.
     *
     * Best-effort by design: this is instrumentation wrapped around a payment
     * check, and a cache hiccup must never turn a working verify into a failed
     * one. The enforcing counter is incremented under the budget lock instead.
     */
    private function recordCall(?string $target, string $reason, ?string $reasonTarget = null): void
    {
        $reasonTarget ??= $target;

        $keys = [self::usageKey()];
        if ($target !== null) {
            $keys[] = self::usageKey($target);
        }
        if ($reasonTarget !== null) {
            $keys[] = self::usageKey($reasonTarget).':'.$reason;
        }

        foreach ($keys as $key) {
            try {
                // add() only writes when the key is absent, so it seeds the
                // counter without clobbering a concurrent increment. The
                // database cache store's increment() is a no-op on a missing
                // key, which is why seeding cannot be skipped.
                Cache::add($key, 0, now()->addDays(3));
                Cache::increment($key);
            } catch (\Throwable $e) {
                // Deliberately swallowed — see the docblock.
            }
        }
    }

    private static function usageKey(?string $target = null, ?Carbon $day = null): string
    {
        $date = ($day ?? Carbon::now())->format('Y-m-d');

        return $target === null
            ? "khqr:calls:{$date}"
            : "khqr:calls:{$date}:{$target}";
    }

    // ───────────────────────────────── per-session attempt cap

    /**
     * Has this one payment session already cost as many live calls as any
     * session ever needs?
     *
     * The cooldown caps the RATE and the QR's TTL caps the WINDOW, but both
     * pollers and the reconcile net draw on the same session, so the product was
     * still the only bound on a single payment's total cost. A session asked
     * about twenty times has said everything it is going to say; the webhook is
     * the path that rescues it after that.
     */
    public function attemptsExhausted(KhqrPayment $row): bool
    {
        $max = (int) config('services.khqrpay.max_verify_attempts', 0);
        if ($max <= 0) {
            return false;
        }

        return self::attemptsFor($row) >= $max;
    }

    public static function attemptsFor(KhqrPayment $row): int
    {
        try {
            return (int) Cache::get(self::attemptKey($row), 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function recordAttempt(KhqrPayment $row): void
    {
        try {
            // Held well past the QR's own life so the count still bounds the
            // reconcile net's rescue window, not just the browser poll.
            Cache::add(self::attemptKey($row), 0, now()->addDay());
            Cache::increment(self::attemptKey($row));
        } catch (\Throwable $e) {
            // Instrumentation only.
        }
    }

    private static function attemptKey(KhqrPayment $row): string
    {
        return 'khqr:verify:attempts:'.$row->transaction_id;
    }

    // ───────────────────────────────── helpers

    /**
     * Make provider-derived text safe to log or display.
     *
     * A transport exception's message quotes the full request URL — and the
     * hosted-checkout URL is SIGNED, carrying `hash=` in its query string. Query
     * strings, credential-looking assignments, bearer tokens and JWT-shaped values
     * are removed; nothing here is needed to diagnose a refusal.
     */
    public static function redact(?string $text, int $limit = 300): string
    {
        $text = (string) $text;

        $text = (string) preg_replace('~(https?://[^\s?#"\'<>]+)\?[^\s"\'<>]*~i', '$1?[redacted]', $text);
        $text = (string) preg_replace(
            '~\b(hash|token|secret|signature|authorization|password|api[_-]?key)\b("?\s*[=:]\s*)("?)[^\s&"\',}]+~i',
            '$1$2$3[redacted]',
            $text,
        );
        $text = (string) preg_replace('~\bBearer\s+[A-Za-z0-9._\~+/=-]+~i', 'Bearer [redacted]', $text);
        $text = (string) preg_replace('~\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]*~', '[redacted-jwt]', $text);

        return Str::limit(trim($text), $limit);
    }

    private function forget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable $e) {
            // The key carries its own TTL; it will lapse on its own.
        }
    }
}
