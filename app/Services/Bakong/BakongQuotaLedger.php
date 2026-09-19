<?php

namespace App\Services\Bakong;

use App\Models\BakongApiCall;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The concurrency-critical half of the Bakong quota protection: the daily
 * ceiling, the per-transaction cooldown slot, and the two backoffs.
 *
 * Split out of BakongProviderClient because these are the parts where being
 * *nearly* right is worthless. A ceiling that yields under load, or a cooldown
 * that two workers can both pass, does nothing at all under exactly the
 * conditions it exists for — and the failure is invisible until the allowance
 * is gone. They are small enough to reason about and test in isolation here,
 * while the client above stays readable as a list of gates.
 *
 * Two different failure policies live here, and mixing them up is the bug:
 *
 *  - THE BUDGET FAILS CLOSED. If the reservation cannot be completed — the lock
 *    times out under contention, the ledger will not write — the request is
 *    REFUSED. The KHQRPay integration originally caught every exception here,
 *    counted the call and allowed it, so the ceiling gave way under precisely
 *    the concurrency it was written for. A payment that cannot be verified this
 *    second stays open and is asked about again; an allowance spent is gone for
 *    the day.
 *
 *  - ACCOUNTING FAILS OPEN AND SILENT. Recording that a call was *refused* must
 *    never itself break a payment flow, the same rule AuditLogger follows.
 */
class BakongQuotaLedger
{
    /**
     * How long a verification slot is held when no cooldown is configured:
     * long enough to cover one request's own timeouts, so two processes still
     * cannot ask about the same transaction at the same moment.
     */
    private const IN_FLIGHT_SECONDS = 30;

    /** Seconds to wait for the budget lock before giving up (and refusing). */
    private const LOCK_WAIT = 5;

    /** Seconds the budget lock is held before it self-releases. */
    private const LOCK_TTL = 10;

    // ---------------------------------------------------------------- budget

    /**
     * Is the day's allowance for this target already spent?
     *
     * A read-only question, for the callers that must decide NOT TO ASK in the
     * first place — a diagnostics report must never spend the reserve it is
     * reporting on. The ceiling itself is enforced by reserve(), which is what
     * makes it unforgettable.
     */
    public function exhausted(string $target): bool
    {
        $limit = $this->limit();

        if ($limit <= 0) {
            return false;
        }

        try {
            return BakongApiCall::spentOn($target) >= $limit;
        } catch (\Throwable $e) {
            // Unreadable ledger is not a positive finding that the day is
            // spent. reserve() is where this has to fail closed; answering
            // "exhausted" here would wrongly tell an operator the allowance is
            // gone when the database merely blinked.
            return false;
        }
    }

    public function spentToday(string $target): int
    {
        try {
            return BakongApiCall::spentOn($target);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function limit(): int
    {
        return (int) config('bakong.daily_request_limit', 0);
    }

    /**
     * Atomically reserve one live call against the day's ceiling.
     *
     * The count and the insert happen under ONE lock, so two workers cannot
     * both see the last remaining slot. The lock covers the ledger only — never
     * the HTTP request, which would serialise every checkout in the system
     * behind one mutex.
     *
     * Returns the ledger row (to be stamped with the outcome afterwards) or a
     * BLOCK_* reason string. FAILS CLOSED on every error path.
     */
    public function reserve(string $target, string $reason, string $endpoint, ?int $paymentId): BakongApiCall|string
    {
        $limit = $this->limit();

        try {
            // With no ceiling configured there is nothing to serialise on, so
            // the row is written directly. This is the backward-compatibility
            // seam — and not a setting any deployment taking real payments
            // should be using.
            if ($limit <= 0) {
                return $this->writeAllowed($target, $reason, $endpoint, $paymentId);
            }

            $lockKey = 'bakong:budget-lock:'.Carbon::now()->toDateString().':'.$target;

            $reserved = Cache::lock($lockKey, self::LOCK_TTL)->block(
                self::LOCK_WAIT,
                function () use ($target, $reason, $endpoint, $paymentId, $limit): BakongApiCall|false {
                    if (BakongApiCall::spentOn($target) >= $limit) {
                        return false;
                    }

                    return $this->writeAllowed($target, $reason, $endpoint, $paymentId);
                }
            );

            if ($reserved === false) {
                return BakongProviderClient::BLOCK_BUDGET;
            }

            return $reserved;
        } catch (\Throwable $e) {
            // A lock that timed out because many requests are queued for it is
            // exactly the moment the ceiling matters most. Refuse.
            Log::warning('Bakong budget reservation unavailable', [
                'target' => $target,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);

            return BakongProviderClient::BLOCK_BUDGET_UNAVAILABLE;
        }
    }

    private function writeAllowed(string $target, string $reason, string $endpoint, ?int $paymentId): BakongApiCall
    {
        return BakongApiCall::create([
            'called_on' => Carbon::now()->toDateString(),
            'endpoint' => $endpoint,
            'reason' => $reason,
            'target' => $target,
            'khqr_payment_id' => $paymentId,
            'allowed' => true,
        ]);
    }

    /**
     * Record an attempt that never left this server.
     *
     * Best-effort and silent: the finding is useful, but failing to write it
     * must not break the flow that was already being refused for other reasons.
     * These rows never count toward the ceiling — a refused call cost Bakong
     * nothing, and counting it would let a burst of correctly-blocked polls
     * lock out the payment that matters.
     */
    public function recordBlocked(string $target, string $reason, string $endpoint, ?int $paymentId, string $blockedReason): void
    {
        try {
            BakongApiCall::create([
                'called_on' => Carbon::now()->toDateString(),
                'endpoint' => $endpoint,
                'reason' => $reason,
                'target' => $target,
                'khqr_payment_id' => $paymentId,
                'allowed' => false,
                'blocked_reason' => $blockedReason,
            ]);
        } catch (\Throwable $e) {
            // Deliberately swallowed. See the class docblock.
        }
    }

    /** Stamp a reserved row with what came back. Best-effort, never throws. */
    public function stamp(BakongApiCall $call, array $attributes): void
    {
        try {
            $call->forceFill($attributes)->save();
        } catch (\Throwable $e) {
            // Deliberately swallowed.
        }
    }

    // -------------------------------------------------------------- cooldown

    /**
     * Claim the right to ask about one transaction, atomically, BEFORE the
     * request is made.
     *
     * Cache::add is an insert-if-absent on the database, file and redis stores,
     * so this is a genuine compare-and-set rather than a check followed by a
     * write. That distinction is the whole point: the KHQRPay cooldown was
     * check-then-act and was only written once the ANSWER came back, so every
     * poll, tab, worker and reconcile run that arrived while a request was in
     * flight made its own — eight processes, eight metered requests, one
     * question.
     *
     * With a cooldown of 0 the slot is purely an in-flight guard and is
     * released as soon as the request finishes.
     */
    public function claimVerifySlot(string $transactionId): bool
    {
        $seconds = max(0, (int) config('bakong.verify_cooldown', 0));
        $hold = $seconds > 0 ? $seconds : self::IN_FLIGHT_SECONDS;

        try {
            return Cache::add($this->slotKey($transactionId), Carbon::now()->toIso8601String(), $hold);
        } catch (\Throwable $e) {
            // A cooldown ledger that cannot be written cannot promise the call
            // is unique, and an un-throttled verify loop is the failure this
            // whole class exists to prevent. Refuse.
            Log::warning('Bakong cooldown slot unavailable', [
                'transaction' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Release an in-flight-only slot once the request is done.
     *
     * Only when no cooldown is configured: with one, the slot IS the cooldown
     * and releasing it early would let the next poll straight through.
     */
    public function releaseVerifySlot(string $transactionId): void
    {
        if ((int) config('bakong.verify_cooldown', 0) > 0) {
            return;
        }

        try {
            Cache::forget($this->slotKey($transactionId));
        } catch (\Throwable $e) {
            // The slot expires on its own; nothing to recover.
        }
    }

    private function slotKey(string $transactionId): string
    {
        return 'bakong:verify-slot:'.sha1($transactionId);
    }

    // --------------------------------------------------------------- backoff

    /**
     * Stop calling for a while after a refusal that will be just as true on the
     * next request — a rejected token, a spent allowance, Bakong unwell.
     *
     * Without this, every open checkout re-discovers the same refusal once per
     * cooldown, each discovery a metered call, and a broken credential costs
     * more the more customers are trying to pay.
     *
     * Deliberately NOT tripped by a timeout: a timeout says nothing about the
     * token, and the cooldown already prevents an immediate retry.
     */
    public function backOff(string $target, int $minutes, string $why): void
    {
        if ($minutes <= 0) {
            return;
        }

        try {
            Cache::put($this->backoffKey($target), [
                'until' => Carbon::now()->addMinutes($minutes)->toIso8601String(),
                'why' => mb_substr($why, 0, 200),
            ], $minutes * 60);
        } catch (\Throwable $e) {
            // Best-effort: the gates above still refuse on the next real answer.
        }
    }

    /** @return array{until: Carbon, why: string}|null */
    public function activeBackoff(string $target): ?array
    {
        foreach ([$this->backoffKey($target), $this->rateLimitKey($target)] as $key) {
            try {
                $entry = Cache::get($key);
            } catch (\Throwable $e) {
                continue;
            }

            if (! is_array($entry) || ! isset($entry['until'])) {
                continue;
            }

            $until = Carbon::parse($entry['until']);

            if ($until->isFuture()) {
                return ['until' => $until, 'why' => (string) ($entry['why'] ?? '')];
            }
        }

        return null;
    }

    /** Bakong itself answered 429 — a fact about the provider, not our config. */
    public function rateLimited(string $target, string $why): void
    {
        $minutes = max(1, (int) config('bakong.rate_limit_backoff', 5));

        try {
            Cache::put($this->rateLimitKey($target), [
                'until' => Carbon::now()->addMinutes($minutes)->toIso8601String(),
                'why' => mb_substr($why, 0, 200),
            ], $minutes * 60);
        } catch (\Throwable $e) {
            // Best-effort.
        }
    }

    public function isRateLimited(string $target): bool
    {
        try {
            $entry = Cache::get($this->rateLimitKey($target));
        } catch (\Throwable $e) {
            return false;
        }

        return is_array($entry)
            && isset($entry['until'])
            && Carbon::parse($entry['until'])->isFuture();
    }

    // ------------------------------------------- upstream quota exhaustion

    /**
     * NBC itself has said the day is over. Stop asking until it isn't.
     *
     * This is DIFFERENT from our own daily ceiling, and the difference is the
     * whole reason it exists. Our ceiling counts what WE spent; this records
     * what the TOKEN has spent — including every request made by anything else
     * sharing it, which we cannot see and cannot count. On this account the
     * token is shared with a hosted-checkout provider, so the allowance can be
     * gone while our own ledger reads 6 of 80.
     *
     * Held until local midnight because that is precisely what Bakong says
     * ("Please try again tomorrow"), and capped at 24h so a clock oddity can
     * never latch it shut for longer than a day.
     */
    public function markUpstreamExhausted(string $target, string $why): void
    {
        $until = Carbon::now()->endOfDay();
        $seconds = max(60, min(86400, (int) Carbon::now()->diffInSeconds($until, false)));

        try {
            Cache::put($this->exhaustedKey($target), [
                'until' => $until->toIso8601String(),
                'why' => mb_substr($why, 0, 200),
            ], $seconds);

            Log::warning('Bakong upstream allowance exhausted — no further requests today', [
                'target' => $target,
                'until' => $until->toIso8601String(),
                'our_own_spend_today' => $this->spentToday($target),
                'why' => mb_substr($why, 0, 200),
            ]);
        } catch (\Throwable $e) {
            // Best-effort: the gate below simply keeps asking, which is how it
            // behaved before this existed.
        }
    }

    /** @return array{until: Carbon, why: string}|null */
    public function upstreamExhausted(string $target): ?array
    {
        try {
            $entry = Cache::get($this->exhaustedKey($target));
        } catch (\Throwable $e) {
            return null;
        }

        if (! is_array($entry) || ! isset($entry['until'])) {
            return null;
        }

        $until = Carbon::parse($entry['until']);

        return $until->isFuture()
            ? ['until' => $until, 'why' => (string) ($entry['why'] ?? '')]
            : null;
    }

    private function exhaustedKey(string $target): string
    {
        return 'bakong:upstream-exhausted:'.$target;
    }

    /**
     * Clear both backoffs — for an operator standing on the diagnostics page
     * having just fixed the credential, so a working token is usable
     * immediately rather than after a wait nobody can explain.
     */
    public function clearBackoffs(string $target): void
    {
        try {
            Cache::forget($this->backoffKey($target));
            Cache::forget($this->rateLimitKey($target));
            Cache::forget($this->exhaustedKey($target));
        } catch (\Throwable $e) {
            // Best-effort.
        }
    }

    private function backoffKey(string $target): string
    {
        return 'bakong:backoff:'.$target;
    }

    private function rateLimitKey(string $target): string
    {
        return 'bakong:ratelimit:'.$target;
    }
}
