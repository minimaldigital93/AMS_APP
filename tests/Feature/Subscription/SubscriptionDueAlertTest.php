<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NotificationService;

beforeEach(function () {
    seedRoles();
    $this->plan = Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24,
        'max_floors' => 4, 'max_apartments' => 200, 'billing_period_days' => 30, 'is_active' => true,
    ]);
});

function makeAdminWithSubscription(Plan $plan, ?\Carbon\Carbon $expiresAt): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->forceFill(['account_id' => $user->id])->save();
    $user->assignRole('admin');

    Subscription::create([
        'account_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'started_at' => now()->subDays(28),
        'expires_at' => $expiresAt,
    ]);

    return $user;
}

it('alerts an admin whose subscription expires within 3 days', function () {
    $user = makeAdminWithSubscription($this->plan, now()->addDays(2));

    $alert = app(NotificationService::class)->subscriptionDueAlert($user);

    expect($alert)->not->toBeNull();
    expect($alert['type'])->toBe('subscription_due');
    expect($alert['url'])->toBe(route('admin.billing.index'));
});

it('does not alert when the subscription is more than 3 days out', function () {
    $user = makeAdminWithSubscription($this->plan, now()->addDays(10));

    expect(app(NotificationService::class)->subscriptionDueAlert($user))->toBeNull();
});

it('does not alert when the subscription has already expired', function () {
    $user = makeAdminWithSubscription($this->plan, now()->subDay());

    expect(app(NotificationService::class)->subscriptionDueAlert($user))->toBeNull();
});

it('does not alert when there is no subscription', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->forceFill(['account_id' => $user->id])->save();
    $user->assignRole('admin');

    expect(app(NotificationService::class)->subscriptionDueAlert($user))->toBeNull();
});

it('surfaces the alert at the top of the admin notification feed', function () {
    $user = makeAdminWithSubscription($this->plan, now()->addDay());

    $feed = app(NotificationService::class)->for($user);

    expect($feed->first()['type'])->toBe('subscription_due');
});
