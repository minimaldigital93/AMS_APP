<?php

namespace App\Services\Payment;

use App\Models\KhqrPayment;
use App\Services\RevenueExpense\KhqrCredentials;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
 * Six gates, checked in this order, and each one is a refusal BEFORE the request
 * rather than an interpretation after it:
 *
 *  1. khqr_disabled        — services.khqrpay.enabled is false. Absolute: no
 *                            reason, row, budget or override gets past it.
 *  2. demo_mode            — the local simulation must never transmit.
 *  3. no_active_payment    — a row was supplied and it is not a live KHQR
 *                            payment session. A database row is not a payment;
 *                            see KhqrPayment::isActiveKhqrSession().
 *  4. rate_limited         — this credential answered 429 recently.
 *  5. daily_budget_exhausted — the per-target ceiling is reached.
 *  6. max_attempts_reached — this one session has already been asked about
 *                            more times than any session needs.
 *
 * Gates 4–6 were already enforced inside KhqrPaymentService; they moved here so
 * a new call site inherits them instead of having to remember them.
 *
 * Every allowed request is logged with the REASON it happened, and every block
 * with the reason it did not. That log is how a future accidental call gets
 * found, so a request with no reason is not allowed — the parameter is required.
 */
class KhqrProviderClient
{
    /** Why a request is being made. Required, and logged verbatim. */
    public const REASON_PAYMENT_CREATION = 'payment_creation';

    public const REASON_PAYMENT_VERIFICATION = 'payment_verification';

    public const REASON_WEBHOOK_RECOVERY = 'webhook_recovery';

    public const REASON_CHECKOUT_PREFLIGHT = 'checkout_preflight';

    public const REASON_MANUAL_DIAGNOSTIC = 'manual_diagnostic';

    /** Why a request was refused. */
    public const BLOCK_DISABLED = 'khqr_disabled';

    public const BLOCK_NO_ACTIVE_PAYMENT = 'no_active_payment';

    public const BLOCK_RATE_LIMITED = 'rate_limited';

    public const BLOCK_BUDGET = 'daily_budget_exhausted';

    public const BLOCK_ATTEMPTS = 'max_attempts_reached';

    public const BLOCK_DEMO = 'demo_mode';

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
     * @param  KhqrPayment|null  $row  the payment being asked about; when given, it must be a live session
     * @param  int  $sessionGrace  minutes past a QR's expiry that still count as live (khqr:reconcile's rescue window)
     * @param  callable(): \Illuminate\Http\Client\Response  $perform
     */
    public function call(
        string $reason,
        ?string $target,
        ?KhqrPayment $row,
        callable $perform,
        ?KhqrCredentials $creds = null,
        int $sessionGrace = 0,
    ): KhqrProviderResult {
        $target ??= 'unknown';

        // ---- Gate 1: the master switch. Nothing gets past this. ----
        if (! (bool) config('services.khqrpay.enabled')) {
            return $this->refuse(self::BLOCK_DISABLED, $reason, $target, $row);
        }

        // Demo mode simulates the whole flow locally and must never transmit.
        // Reaching here in demo is a bug in the caller, not a configuration —
        // hence a distinct reason rather than folding it into 'khqr_disabled'.
        if ((bool) config('services.khqrpay.demo')) {
            return $this->refuse(self::BLOCK_DEMO, $reason, $target, $row);
        }

        // ---- Gate 2: a row, if supplied, must be a live payment session. ----
        // The rule the whole audit turns on: AMS must never contact Bakong
        // merely because a KHQR record exists.
        if ($row !== null && ! $row->isActiveKhqrSession($sessionGrace)) {
            return $this->refuse(self::BLOCK_NO_ACTIVE_PAYMENT, $reason, $target, $row);
        }

        // ---- Gate 3: a 429 the provider already sent us. ----
        if ($creds !== null && $this->rateLimited($creds)) {
            return $this->refuse(self::BLOCK_RATE_LIMITED, $reason, $target, $row);
        }

        // ---- Gate 4: atomically reserve one call from the day's ceiling. ----
        // The budget check and reservation happen under one lock so concurrent
        // requests cannot both see the same remaining allowance.
        if (! $this->reserveBudgetCall($target)) {
            return $this->refuse(self::BLOCK_BUDGET, $reason, $target, $row);
        }

        // ---- Gate 5: this session has been asked about enough times. ----
        if ($row !== null && $this->attemptsExhausted($row)) {
            return $this->refuse(self::BLOCK_ATTEMPTS, $reason, $target, $row);
        }

        // The budget reservation above already counted this call BEFORE the
        // network request, so timeouts are still charged to the allowance.
        if ($row !== null) {
            $this->recordAttempt($row);
        }

        Log::info('KHQR provider request', [
            'reason' => $reason,
            'provider' => 'khqrpay',
            'target' => $target,
            'transaction_id' => $row?->transaction_id,
            'spent_today' => self::callsOn($target),
        ]);

        try {
            return KhqrProviderResult::answered($perform());
        } catch (\Throwable $e) {
            Log::warning('KHQR provider request failed in transport', [
                'reason' => $reason,
                'target' => $target,
                'transaction_id' => $row?->transaction_id,
                'msg' => $e->getMessage(),
            ]);

            return KhqrProviderResult::failed($e);
        }
    }

    /** Log a refusal and hand it back. No request is made, no quota spent. */
    private function refuse(string $block, string $reason, string $target, ?KhqrPayment $row): KhqrProviderResult
    {
        // debug for the two blocks that are the NORMAL steady state of a
        // KHQR-disabled install — at info they would fill the log with one line
        // per poll for a decision that is working as configured. The others are
        // warnings: they mean a live payment is not being confirmed.
        $level = in_array($block, [self::BLOCK_DISABLED, self::BLOCK_NO_ACTIVE_PAYMENT], true)
            ? 'debug'
            : 'warning';

        Log::{$level}('KHQR provider request blocked', [
            'reason' => $block,
            'requested_for' => $reason,
            'target' => $target,
            'transaction_id' => $row?->transaction_id,
        ]);

        return KhqrProviderResult::blocked($block);
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
            return false; // fail open — a broken cache must not block a real payment
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

    // ───────────────────────────────── daily budget

    /**
     * Has this settlement target spent its allowance of live calls for the day?
     *
     * Per target, because platform rows spend the SaaS operator's Bakong token
     * and merchant rows spend the individual landlord's — a shared ceiling would
     * let one busy landlord lock out everyone else. Fails OPEN on a cache error:
     * a broken cache must not stop a real payment being confirmed.
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
     * Count one live call, in total and for its target.
     *
     * Best-effort by design: this is instrumentation wrapped around a payment
     * check, and a cache hiccup must never turn a working verify into a failed
     * one. Same rule AuditLogger follows.
     */
    private function recordCall(string $target): void
    {
        foreach ([self::usageKey(), self::usageKey($target)] as $key) {
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

    /**
     * Atomically reserve one live provider call from the target's daily budget.
     *
     * The lock only covers the counter reservation, never the provider request.
     * A cache failure remains fail-open to preserve the existing payment behavior.
     */
    private function reserveBudgetCall(string $target): bool
    {
        $budget = (int) config('services.khqrpay.daily_budget', 0);

        if ($budget <= 0) {
            $this->recordCall($target);
            return true;
        }

        $target = $target !== '' ? $target : 'unknown';
        $lockKey = 'khqr:budget-lock:' . Carbon::now()->format('Y-m-d') . ':' . $target;

        try {
            return Cache::lock($lockKey, 5)->block(2, function () use ($target, $budget): bool {
                $targetKey = self::usageKey($target);

                Cache::add($targetKey, 0, now()->addDays(3));

                $spent = (int) Cache::get($targetKey, 0);

                if ($spent >= $budget) {
                    return false;
                }

                Cache::increment($targetKey);

                $globalKey = self::usageKey();

                Cache::add($globalKey, 0, now()->addDays(3));
                Cache::increment($globalKey);

                return true;
            });
        } catch (\Throwable $e) {
            // Preserve the existing fail-open payment behavior if cache/lock
            // infrastructure is temporarily unavailable.
            $this->recordCall($target);
            return true;
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
}
