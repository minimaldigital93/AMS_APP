<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * One recorded Bakong Open API request — made or refused.
 *
 * This is the ledger the daily ceiling is enforced against, which is the whole
 * reason it is a table: the KHQRPay integration counts in the cache, so a
 * `cache:clear` (the very command someone runs when a gateway misbehaves)
 * silently hands the day a fresh allowance.
 *
 * Deliberately NOT account-scoped. The allowance belongs to the installation's
 * integrator token, not to a customer account, and a superadmin investigating
 * a drained quota has to be able to see every call — the same reasoning that
 * keeps Subscription out of BelongsToAccount.
 *
 * Append-only: nothing updates a row except the client stamping the outcome of
 * the request it just reserved. Never holds a token, header or request body.
 */
class BakongApiCall extends Model
{
    protected $fillable = [
        'called_on',
        'endpoint',
        'reason',
        'target',
        'khqr_payment_id',
        'allowed',
        'blocked_reason',
        'http_status',
        'response_code',
        'error_code',
        'outcome',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'called_on' => 'date',
            'allowed' => 'boolean',
        ];
    }

    /**
     * Requests that actually left this server on a given day, for one target.
     *
     * THIS IS THE CEILING'S COUNT. Refused attempts are recorded but excluded:
     * a call that was blocked cost Bakong nothing, and counting it would let a
     * burst of correctly-refused polls lock out the payment that matters.
     */
    public static function spentOn(string $target, ?Carbon $day = null): int
    {
        return static::query()
            ->whereDate('called_on', ($day ?? Carbon::now())->toDateString())
            ->where('target', $target)
            ->where('allowed', true)
            ->count();
    }

    /**
     * Today's spend broken down by the reason each call was made — the first
     * question of any quota investigation, and the one a running total cannot
     * answer.
     *
     * @return array<string, int>
     */
    public static function spentByReasonOn(string $target, ?Carbon $day = null): array
    {
        return static::query()
            ->whereDate('called_on', ($day ?? Carbon::now())->toDateString())
            ->where('target', $target)
            ->where('allowed', true)
            ->selectRaw('reason, COUNT(*) as total')
            ->groupBy('reason')
            ->pluck('total', 'reason')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Attempts that were REFUSED on a given day, by the gate that stopped them.
     *
     * Read alongside spentByReasonOn(): a large 'verify_cooldown' count is the
     * guards working, while a large 'daily_budget_exhausted' count is the day
     * already lost and every later refusal just noise.
     *
     * @return array<string, int>
     */
    public static function blockedByReasonOn(string $target, ?Carbon $day = null): array
    {
        return static::query()
            ->whereDate('called_on', ($day ?? Carbon::now())->toDateString())
            ->where('target', $target)
            ->where('allowed', false)
            ->selectRaw('blocked_reason, COUNT(*) as total')
            ->groupBy('blocked_reason')
            ->pluck('total', 'blocked_reason')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    public function payment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(KhqrPayment::class, 'khqr_payment_id');
    }
}
