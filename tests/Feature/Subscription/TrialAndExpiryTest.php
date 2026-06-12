<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;

function makeTrialPlan(int $trialDays = 14): Plan
{
    return Plan::create([
        'slug' => 'trial-plan-'.uniqid(),
        'name' => 'Trial Plan',
        'price_usd' => 10,
        'max_floors' => null,
        'max_apartments' => null,
        'billing_period_days' => 30,
        'trial_days' => $trialDays,
        'is_active' => true,
    ]);
}

it('signup with start_trial activates the account immediately without payment', function () {
    seedRoles();
    $plan = makeTrialPlan(14);

    $response = $this->post(route('subscribe.store'), [
        'name' => 'Trial Landlord',
        'phone' => '012-999-888',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => $plan->slug,
        'start_trial' => '1',
    ]);

    $response->assertRedirect(route('login'));

    $user = User::where('phone', '012-999-888')->sole();
    expect($user->status)->toBe('active');
    expect($user->hasRole('admin'))->toBeTrue();

    $subscription = Subscription::where('account_id', $user->id)->sole();
    expect($subscription->status)->toBe('trialing');
    expect($subscription->isActive())->toBeTrue();
    expect($subscription->trial_started_at)->not->toBeNull();
    expect($subscription->expires_at->isFuture())->toBeTrue();
});

it('allows only one trial per account, ever', function () {
    seedRoles();
    $plan = makeTrialPlan();
    $service = app(SubscriptionService::class);

    $user = User::factory()->create();
    $user->forceFill(['account_id' => $user->id])->save();

    $service->startTrial($user->id, $plan);

    // Simulate the trial lapsing, then try again.
    Subscription::where('account_id', $user->id)->update(['status' => 'expired', 'expires_at' => now()->subDay()]);

    $service->startTrial($user->id, $plan);
})->throws(RuntimeException::class);

it('a trialing subscription satisfies the active-subscription gate', function () {
    seedRoles();
    $plan = makeTrialPlan();
    $service = app(SubscriptionService::class);

    $user = User::factory()->create();
    $user->forceFill(['account_id' => $user->id])->save();
    $service->startTrial($user->id, $plan);

    expect($service->activeSubscription($user->id))->not->toBeNull();
});

it('subscriptions:expire flips lapsed active and trialing subscriptions', function () {
    seedRoles();
    $plan = makeTrialPlan();

    $lapsed = User::factory()->create();
    Subscription::create([
        'account_id' => $lapsed->id, 'plan_id' => $plan->id,
        'status' => 'active', 'started_at' => now()->subMonths(2), 'expires_at' => now()->subDay(),
    ]);

    $current = User::factory()->create();
    Subscription::create([
        'account_id' => $current->id, 'plan_id' => $plan->id,
        'status' => 'active', 'started_at' => now(), 'expires_at' => now()->addMonth(),
    ]);

    $this->artisan('subscriptions:expire')->assertSuccessful();

    expect(Subscription::where('account_id', $lapsed->id)->sole()->status)->toBe('expired');
    expect(Subscription::where('account_id', $current->id)->sole()->status)->toBe('active');
});

it('renewing early extends from the current expiry instead of resetting it', function () {
    seedRoles();
    $plan = makeTrialPlan(0);

    $user = User::factory()->create();
    $user->forceFill(['account_id' => $user->id])->save();

    $subscription = Subscription::create([
        'account_id' => $user->id, 'plan_id' => $plan->id,
        'status' => 'active', 'started_at' => now()->subDays(20), 'expires_at' => now()->addDays(10),
    ]);

    $row = \App\Models\KhqrPayment::create([
        'transaction_id' => 'SUB-TEST-1',
        'subscription_id' => $subscription->id,
        'amount' => 10,
        'currency' => 'USD',
        'status' => 'pending',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription', 'subscription_id' => $subscription->id],
    ]);

    (new \App\Services\RevenueExpense\KhqrPaymentService)->finalizeSubscription($row);

    $subscription->refresh();
    // 10 remaining days + 30-day period ≈ 40 days out (not 30).
    expect((int) round(now()->diffInDays($subscription->expires_at)))->toBeGreaterThanOrEqual(39);
});
