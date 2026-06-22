<?php

use App\Models\Plan;
use App\Models\Subscription;

it('records the chosen billing cycle on renew even if the gateway is unavailable', function () {
    $admin = makeAdmin();
    $plan = Plan::create([
        'slug' => 'yearly-plan', 'name' => 'Yearly', 'price_usd' => 5.99, 'price_yearly_usd' => 59,
        'billing_period_days' => 30, 'is_active' => true,
    ]);
    $this->actingAs($admin);

    // KHQR isn't configured in tests, so the QR step fails and we redirect back —
    // but the subscription row (with its cycle) is written before that step.
    $this->post(route('admin.billing.renew'), ['plan' => $plan->slug, 'billing_cycle' => 'yearly']);

    $sub = Subscription::where('account_id', $admin->id)->latest('id')->first();
    expect($sub->billing_cycle)->toBe('yearly');
    expect($sub->plan_id)->toBe($plan->id);
});

it('charges the yearly price for a yearly cycle and falls back when none is set', function () {
    $plan = new Plan(['price_usd' => 5.99, 'price_yearly_usd' => 59]);
    expect($plan->priceFor('yearly'))->toBe(59.0);
    expect($plan->priceFor('monthly'))->toBe(5.99);

    $noYearly = new Plan(['price_usd' => 5.99, 'price_yearly_usd' => null]);
    expect($noYearly->priceFor('yearly'))->toBe(5.99);
});
