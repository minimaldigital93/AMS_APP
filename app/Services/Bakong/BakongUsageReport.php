<?php

namespace App\Services\Bakong;

use App\Models\BakongApiCall;
use Carbon\Carbon;

/**
 * What the Bakong token has been spent on, assembled for a human to look at.
 *
 * ENTIRELY OFFLINE, and that is a hard requirement rather than an optimisation:
 * the moment anyone opens a usage page is the moment the allowance is already
 * under pressure, and a report that spent the thing it was reporting on would
 * be worse than no report. Every figure here comes from the local ledger
 * (bakong_api_calls), the local cache (the backoff and exhaustion latches) and
 * the token's own JWT. Nothing in this class can make an HTTP request.
 *
 * It is the shared source behind `php artisan bakong:usage` and the superadmin
 * Payment Settings meter, so an SSH session and a browser cannot read different
 * numbers off the same day.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * THE ONE THING THIS PAGE MUST NOT IMPLY: that `remaining` is how many requests
 * are actually left. NBC meters the TOKEN, not this app. Anything else holding
 * the same credential — a second deployment, a hosted-checkout provider it was
 * pasted into, a colleague's script — spends from the same daily allowance and
 * is invisible here. That is not hypothetical: this installation spent 6 of 80
 * and was refused with errorCode 17 ("Daily request limit of 100 exceeded")
 * because the token was shared with khqr.cc.
 *
 * So `upstreamExhausted` is reported as a FINDING OF ITS OWN, above the bar
 * rather than inside it. Our own ceiling and NBC's are different facts, and on
 * a shared token it is always NBC's that bites first.
 * ───────────────────────────────────────────────────────────────────────────
 */
class BakongUsageReport
{
    /** Below this fraction of the ceiling the day is healthy. */
    private const WARN_AT = 0.60;

    private const CRITICAL_AT = 0.85;

    public function __construct(
        private BakongQuotaLedger $ledger,
        private BakongTokenService $tokens,
    ) {}

    /**
     * @param  string  $target  which settlement target's allowance ('platform' for subscriptions)
     * @param  int  $days  how much history to include, today first
     */
    public function build(string $target = 'platform', int $days = 7): array
    {
        $limit = $this->ledger->limit();
        $spent = $this->ledger->spentToday($target);
        $upstream = $this->ledger->upstreamExhausted($target);
        $backoff = $this->ledger->activeBackoff($target);
        $resetsAt = Carbon::now()->endOfDay();

        return [
            'enabled' => BakongProviderClient::featureEnabled(),
            'calls_permitted' => BakongProviderClient::providerCallsPermitted(),
            'target' => $target,

            'limit' => $limit,
            'upstream_limit' => (int) config('bakong.upstream_daily_limit', 0),
            'spent' => $spent,
            // Null rather than a number when no ceiling is configured: with
            // nothing to count down from, "remaining" has no meaning and a
            // fabricated one would be read as a promise.
            'remaining' => $limit > 0 ? max(0, $limit - $spent) : null,
            'percent' => $limit > 0 ? min(100, (int) round($spent / $limit * 100)) : 0,
            'state' => $this->state($spent, $limit, $upstream !== null),

            // The allowance is a calendar-day thing at NBC's end, so the reset
            // is local midnight — the same instant markUpstreamExhausted()
            // latches until, and the only honest answer to "when can I try
            // again?" while it is latched.
            'resets_at' => $resetsAt->toIso8601String(),
            'resets_in' => $resetsAt->diffForHumans(['parts' => 2, 'short' => true, 'syntax' => Carbon::DIFF_ABSOLUTE]),

            // How many more customers can complete a checkout today, which is
            // the question behind the question. Derived from the same two
            // settings that bound one session — see callsPerCheckout().
            'calls_per_checkout' => $this->callsPerCheckout(),
            'checkouts_left' => $limit > 0
                ? intdiv(max(0, $limit - $spent), max(1, $this->callsPerCheckout()))
                : null,

            'upstream_exhausted' => $upstream === null ? null : [
                'until' => $upstream['until']->toIso8601String(),
                'until_human' => $upstream['until']->diffForHumans(['parts' => 2, 'short' => true, 'syntax' => Carbon::DIFF_ABSOLUTE]),
                'why' => $upstream['why'],
            ],
            'backoff' => $backoff === null ? null : [
                'until' => $backoff['until']->toIso8601String(),
                'until_human' => $backoff['until']->diffForHumans(['parts' => 2, 'short' => true, 'syntax' => Carbon::DIFF_ABSOLUTE]),
                'why' => $backoff['why'],
            ],

            // Why the day went, and what the guards refused. Read together:
            // a large verify_cooldown block count is the throttle working; a
            // large daily_budget_exhausted count is the day already lost and
            // every later refusal merely noise.
            'by_reason' => $this->sorted(BakongApiCall::spentByReasonOn($target)),
            'blocked_by_gate' => $this->sorted(BakongApiCall::blockedByReasonOn($target)),

            'history' => $this->history($target, $days, $limit),
            'token' => $this->token(),
        ];
    }

    /**
     * The most requests one subscription checkout can cost.
     *
     * Building the QR costs NOTHING — the payload is constructed locally, which
     * is the single biggest saving of the direct integration over a hosted
     * checkout. So a checkout's whole cost is its verification polls: one per
     * cooldown for as long as the QR lives, capped by max_verify_attempts.
     */
    public function callsPerCheckout(): int
    {
        $ttlSeconds = max(1, (int) config('bakong.qr_ttl', 6)) * 60;
        $cooldown = max(1, (int) config('bakong.verify_cooldown', 60));
        $cap = (int) config('bakong.max_verify_attempts', 0);

        $polls = (int) ceil($ttlSeconds / $cooldown);

        return max(1, $cap > 0 ? min($cap, $polls) : $polls);
    }

    /**
     * ok → warn → critical → exhausted.
     *
     * An upstream refusal outranks every local figure: NBC saying the day is
     * over is the end of the matter, however much of our own ceiling is unused.
     * That case is exactly why the panel cannot be a bar alone.
     */
    private function state(int $spent, int $limit, bool $upstreamExhausted): string
    {
        if ($upstreamExhausted) {
            return 'exhausted';
        }

        if ($limit <= 0) {
            return 'unbounded';
        }

        return match (true) {
            $spent >= $limit => 'exhausted',
            $spent >= $limit * self::CRITICAL_AT => 'critical',
            $spent >= $limit * self::WARN_AT => 'warn',
            default => 'ok',
        };
    }

    /** @return list<array{date: string, label: string, spent: int, percent: int}> */
    private function history(string $target, int $days, int $limit): array
    {
        $rows = [];

        for ($i = max(1, $days) - 1; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i);
            $spent = BakongApiCall::spentOn($target, $day);

            $rows[] = [
                'date' => $day->toDateString(),
                'label' => $day->isToday() ? __('messages.bakong_usage_today') : $day->format('D'),
                'spent' => $spent,
                'percent' => $limit > 0 ? min(100, (int) round($spent / $limit * 100)) : 0,
            ];
        }

        return $rows;
    }

    /**
     * The token's own life, read out of its JWT — never asked for.
     *
     * Asking Bakong when a token expires would spend a metered request on
     * information the credential already states, which is the mistake this
     * whole integration is written to avoid.
     */
    private function token(): array
    {
        $status = $this->tokens->status();

        $expiresAt = $status['expires_at'] ? Carbon::parse($status['expires_at']) : null;

        return $status + [
            // Signed on purpose: a negative number is an expired token, and
            // rendering that as "0 days left" would hide the finding.
            'days_left' => $expiresAt ? (int) floor(Carbon::now()->diffInDays($expiresAt, false)) : null,
            'expires_at_human' => $expiresAt?->toDayDateTimeString(),
        ];
    }

    /** @param array<string, int> $counts */
    private function sorted(array $counts): array
    {
        arsort($counts);

        return $counts;
    }
}
