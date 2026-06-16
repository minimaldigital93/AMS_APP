<?php

namespace App\Services\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolves an account's plan and enforces its floor/apartment caps.
 *
 * The "account" is the owning admin user id (see BelongsToAccount). Counts use
 * withoutAccountScope() so this can be called for ANY account (e.g. the
 * superadmin platform panel), not just the current request's account.
 *
 * Enforcement is lenient when no active plan exists (legacy installs / accounts
 * the superadmin provisioned by hand) — access gating is the renew middleware's
 * job; this class only enforces the numeric caps once a plan is in effect.
 */
class SubscriptionService
{
    public function activeSubscription(int $accountId): ?Subscription
    {
        return Subscription::query()
            ->where('account_id', $accountId)
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->with('plan')
            ->latest('id')
            ->first();
    }

    /**
     * Start a free trial on a plan — one per account, ever (trial_started_at is
     * permanent; switching plans or expiring does not reset it).
     */
    public function startTrial(int $accountId, Plan $plan): Subscription
    {
        if (! $plan->hasTrial()) {
            throw new \InvalidArgumentException("Plan [{$plan->slug}] has no trial period.");
        }

        $existing = Subscription::where('account_id', $accountId)->latest('id')->first();
        if ($existing?->trialUsed()) {
            throw new \RuntimeException(__('messages.trial_already_used'));
        }

        return Subscription::updateOrCreate(
            ['account_id' => $accountId],
            [
                'plan_id' => $plan->id,
                'status' => 'trialing',
                'started_at' => now(),
                'expires_at' => now()->addDays($plan->trial_days),
                'trial_started_at' => now(),
            ]
        );
    }

    public function activePlan(int $accountId): ?Plan
    {
        return $this->activeSubscription($accountId)?->plan;
    }

    /**
     * Cancel an account's subscription.
     *
     * Renewals here are manual KHQR payments (nothing auto-charges), so a normal
     * cancel just marks intent and lets access run to expires_at (the expire cron
     * flips it later). An immediate cancel revokes access now (expires_at = now),
     * which is what a refund-driven revoke uses.
     */
    public function cancel(int $accountId, string $reason = '', bool $immediate = false, ?Authenticatable $actor = null): ?Subscription
    {
        $subscription = Subscription::where('account_id', $accountId)->latest('id')->first();
        if ($subscription === null) {
            return null;
        }

        $subscription->forceFill([
            'cancelled_at' => now(),
            'cancel_reason' => $reason !== '' ? $reason : null,
            // Keep status/expiry for a period-end cancel; revoke now if immediate.
            'status' => $immediate ? SubscriptionStatus::Cancelled->value : $subscription->status,
            'expires_at' => $immediate ? now() : $subscription->expires_at,
        ])->save();

        app(AuditLogger::class)->record('subscription.cancelled', $subscription, [
            'reason' => $reason,
            'immediate' => $immediate,
        ], $actor);

        return $subscription;
    }

    public function floorCount(int $accountId): int
    {
        return Floors::withoutAccountScope()->where('account_id', $accountId)->count();
    }

    public function apartmentCount(int $accountId): int
    {
        return Apartments::withoutAccountScope()->where('account_id', $accountId)->count();
    }

    /**
     * Can this account add $count more floors under its current plan?
     */
    public function canAddFloors(int $accountId, int $count = 1): bool
    {
        $plan = $this->activePlan($accountId);

        if ($plan === null || $plan->hasUnlimitedFloors()) {
            return true;
        }

        return $this->floorCount($accountId) + $count <= $plan->max_floors;
    }

    /**
     * Can this account add $count more apartments under its current plan?
     */
    public function canAddApartments(int $accountId, int $count = 1): bool
    {
        $plan = $this->activePlan($accountId);

        if ($plan === null || $plan->hasUnlimitedApartments()) {
            return true;
        }

        return $this->apartmentCount($accountId) + $count <= $plan->max_apartments;
    }

    /**
     * Usage snapshot for billing/dashboard UI.
     *
     * @return array{plan: ?Plan, floors_used: int, floors_max: ?int, apartments_used: int, apartments_max: ?int}
     */
    public function usage(int $accountId): array
    {
        $plan = $this->activePlan($accountId);

        return [
            'plan' => $plan,
            'floors_used' => $this->floorCount($accountId),
            'floors_max' => $plan?->max_floors,
            'apartments_used' => $this->apartmentCount($accountId),
            'apartments_max' => $plan?->max_apartments,
        ];
    }
}
