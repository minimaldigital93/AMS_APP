<?php

use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\RevenueExpense\KhqrPaymentService;

/** A bigger plan than the unlimited one makeAdmin() hands out. */
function biggerPlan(): Plan
{
    return Plan::create([
        'slug' => 'yearly-plan', 'name' => 'Yearly', 'price_usd' => 5.99, 'price_yearly_usd' => 59,
        'billing_period_days' => 30, 'is_active' => true,
    ]);
}

it('does NOT move a live subscription onto the new plan before the customer pays', function () {
    config(['services.khqrpay.demo' => true]); // mint a QR without live credentials
    $admin = makeAdmin();                      // already active on the test plan
    $paidFor = Subscription::where('account_id', $admin->id)->firstOrFail();
    $plan = biggerPlan();
    $this->actingAs($admin);

    $this->post(route('admin.billing.renew'), ['plan' => $plan->slug, 'billing_cycle' => 'yearly']);

    // The customer picked the bigger plan but has not paid. Caps come straight
    // off subscriptions.plan_id, so applying it here would hand them the bigger
    // plan for free the moment they close the khqr.cc tab.
    $sub = $paidFor->fresh();
    expect($sub->plan_id)->toBe($paidFor->plan_id);
    expect($sub->billing_cycle)->not->toBe('yearly');

    // The choice isn't lost — it rides on the payment until the money lands.
    $payload = KhqrPayment::where('subscription_id', $sub->id)->latest('id')->firstOrFail()->checkout_payload;
    expect($payload['plan_id'])->toBe($plan->id);
    expect($payload['billing_cycle'])->toBe('yearly');
});

it('applies the purchased plan and cycle once the payment finalizes', function () {
    config(['services.khqrpay.demo' => true]);
    $admin = makeAdmin();
    $plan = biggerPlan();
    $this->actingAs($admin);

    $this->post(route('admin.billing.renew'), ['plan' => $plan->slug, 'billing_cycle' => 'yearly']);

    $sub = Subscription::where('account_id', $admin->id)->firstOrFail();
    $row = KhqrPayment::where('subscription_id', $sub->id)->latest('id')->firstOrFail();
    app(KhqrPaymentService::class)->finalizeSubscription($row);

    $sub->refresh();
    expect($sub->plan_id)->toBe($plan->id);         // the upgrade lands HERE
    expect($sub->billing_cycle)->toBe('yearly');
    expect($sub->status)->toBe('active');
    // A yearly term, added on top of the ~30 days still left on the old one —
    // an early renewal extends rather than resets (see finalizeSubscription).
    expect(now()->diffInDays($sub->expires_at))->toBeGreaterThan(360);
});

it('carries the days left on the old plan onto the upgrade, at the new plan', function () {
    // The upgrade money rule, chosen deliberately: leftover days are added on
    // top of the new term AND ride the new plan — a customer with 20 days of
    // Basic left who upgrades to Pro gets 50 days of Pro, not 30, and the 20
    // are upgraded free. The alternatives (prorate the unused VALUE into fewer
    // new-plan days, or forfeit them) were both rejected. Don't "correct" this
    // into proration without asking — it changes what every upgrade is worth.
    config(['services.khqrpay.demo' => true]);
    $admin = makeAdmin();
    $sub = Subscription::where('account_id', $admin->id)->firstOrFail();
    $sub->forceFill(['expires_at' => now()->addDays(20)])->save(); // 20 days left
    $pro = biggerPlan();                                          // 30-day term
    $this->actingAs($admin);

    $this->post(route('admin.billing.renew'), ['plan' => $pro->slug, 'billing_cycle' => 'monthly']);

    $row = KhqrPayment::where('subscription_id', $sub->id)->latest('id')->firstOrFail();
    app(KhqrPaymentService::class)->finalizeSubscription($row);

    $sub->refresh();
    expect($sub->plan_id)->toBe($pro->id);                     // the leftover days are PRO days
    expect(round(now()->diffInDays($sub->expires_at)))->toBe(50.0); // 20 carried + 30 bought
});

it('creates a pending subscription when the account has none yet', function () {
    config(['services.khqrpay.demo' => true]);
    $admin = makeAdmin();
    Subscription::where('account_id', $admin->id)->delete();
    $plan = biggerPlan();
    $this->actingAs($admin);

    $this->post(route('admin.billing.renew'), ['plan' => $plan->slug, 'billing_cycle' => 'yearly']);

    // Safe to stamp on a fresh row: `pending` grants no access either way.
    $sub = Subscription::where('account_id', $admin->id)->firstOrFail();
    expect($sub->plan_id)->toBe($plan->id);
    expect($sub->status)->toBe('pending');
});

it('charges the yearly price for a yearly cycle and falls back when none is set', function () {
    $plan = new Plan(['price_usd' => 5.99, 'price_yearly_usd' => 59]);
    expect($plan->priceFor('yearly'))->toBe(59.0);
    expect($plan->priceFor('monthly'))->toBe(5.99);

    $noYearly = new Plan(['price_usd' => 5.99, 'price_yearly_usd' => null]);
    expect($noYearly->priceFor('yearly'))->toBe(5.99);
});
