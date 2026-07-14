<?php

namespace App\Services\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Subscription;
use App\Models\User;
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
    /**
     * Per-request memo: the middleware gate, the subscription-block composer
     * and the notification due-alert each look this up on every page — one
     * query instead of three. Registered as a singleton, and subscription
     * writes in this service clear it. May hold null (no active subscription).
     */
    private array $activeMemo = [];

    public function activeSubscription(int $accountId): ?Subscription
    {
        if (array_key_exists($accountId, $this->activeMemo)) {
            return $this->activeMemo[$accountId];
        }

        return $this->activeMemo[$accountId] = Subscription::query()
            ->where('account_id', $accountId)
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->with('plan')
            ->latest('id')
            ->first();
    }

    /** Drop the memo for an account after any subscription write. */
    public function forgetActiveMemo(int $accountId): void
    {
        unset($this->activeMemo[$accountId]);
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

        $this->forgetActiveMemo($accountId);

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
        $this->forgetActiveMemo($accountId);

        app(AuditLogger::class)->record('subscription.cancelled', $subscription, [
            'reason' => $reason,
            'immediate' => $immediate,
        ], $actor);

        return $subscription;
    }

    public function propertyCount(int $accountId): int
    {
        return Property::withoutAccountScope()->where('account_id', $accountId)->count();
    }

    public function floorCount(int $accountId): int
    {
        return Floors::withoutAccountScope()->where('account_id', $accountId)->count();
    }

    public function roomCount(int $accountId): int
    {
        return Apartments::withoutAccountScope()->where('account_id', $accountId)->count();
    }

    /** Staff = supervisor users on the account. */
    public function staffCount(int $accountId): int
    {
        return User::where('account_id', $accountId)->role('supervisor')->count();
    }

    /**
     * Can this account add $count more properties under its current plan?
     */
    public function canAddProperties(int $accountId, int $count = 1): bool
    {
        $plan = $this->activePlan($accountId);

        if ($plan === null || $plan->hasUnlimitedProperties()) {
            return true;
        }

        return $this->propertyCount($accountId) + $count <= $plan->max_properties;
    }

    /**
     * Can this account add $count more floors under its current plan?
     * (All current tiers are unlimited floors, so this is effectively always true.)
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
     * Can this account add $count more rooms under its current plan?
     */
    public function canAddRooms(int $accountId, int $count = 1): bool
    {
        $plan = $this->activePlan($accountId);

        if ($plan === null || $plan->hasUnlimitedRooms()) {
            return true;
        }

        return $this->roomCount($accountId) + $count <= $plan->max_rooms;
    }

    /**
     * Can this account add $count more staff (supervisors) under its current plan?
     */
    public function canAddStaff(int $accountId, int $count = 1): bool
    {
        $plan = $this->activePlan($accountId);

        if ($plan === null || $plan->hasUnlimitedStaff()) {
            return true;
        }

        return $this->staffCount($accountId) + $count <= $plan->max_staff;
    }

    /**
     * Usage snapshot for billing/dashboard UI.
     *
     * @return array{plan: ?Plan, properties_used: int, properties_max: ?int, rooms_used: int, rooms_max: ?int, staff_used: int, staff_max: ?int, floors_used: int}
     */
    public function usage(int $accountId): array
    {
        $plan = $this->activePlan($accountId);

        return [
            'plan' => $plan,
            'properties_used' => $this->propertyCount($accountId),
            'properties_max' => $plan?->max_properties,
            'rooms_used' => $this->roomCount($accountId),
            'rooms_max' => $plan?->max_rooms,
            'staff_used' => $this->staffCount($accountId),
            'staff_max' => $plan?->max_staff,
            'floors_used' => $this->floorCount($accountId),
        ];
    }
}
