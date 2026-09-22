<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Subscription;
use App\Services\RevenueExpense\KhqrPaymentService;

/**
 * Switching plan is validated BEFORE the QR is minted.
 *
 * finalizeSubscription() applies the purchased plan the moment the money lands,
 * and every cap is read straight off subscriptions.plan_id — so a switch onto a
 * plan smaller than what the account already uses takes the payment and then
 * leaves the customer over every limit, with no automatic way back.
 */
function planNamed(string $slug, array $attrs = []): Plan
{
    return Plan::create(array_merge([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'price_usd' => 12,
        'price_yearly_usd' => 120,
        'billing_period_days' => 30,
        'is_active' => true,
    ], $attrs));
}

/** Give the current account $count rooms under one property/floor. */
function stockRooms(int $count): void
{
    $property = Property::create(['name' => 'P1']);
    $floor = Floors::create(['property_id' => $property->id, 'floor_name' => 'F1']);

    for ($i = 1; $i <= $count; $i++) {
        Apartments::create([
            'floor_id' => $floor->id,
            'apartment_number' => 'R'.$i,
            'monthly_rent' => 100,
            'status' => 'available',
        ]);
    }
}

it('refuses a switch onto a plan the account has already outgrown, and mints nothing', function () {
    config(['services.khqrpay.demo' => true]);
    $admin = makeAdmin();               // unlimited plan
    $this->actingAs($admin);
    stockRooms(4);

    $small = planNamed('small', ['max_rooms' => 2]);

    $this->post(route('admin.billing.renew'), ['plan' => $small->slug])
        ->assertRedirect()
        ->assertSessionHas('error');

    // Refused before the QR exists — not after the customer has paid for it.
    expect(KhqrPayment::count())->toBe(0);
    expect(Subscription::where('account_id', $admin->id)->first()->plan_id)->not->toBe($small->id);
});

it('names every cap the account is over, with the numbers', function () {
    config(['services.khqrpay.demo' => true]);
    $admin = makeAdmin();
    $this->actingAs($admin);
    stockRooms(4);                       // 4 rooms, and 1 property from stockRooms()

    $small = planNamed('tiny', ['max_rooms' => 2, 'max_properties' => 0]);

    $error = $this->post(route('admin.billing.renew'), ['plan' => $small->slug])
        ->assertSessionHas('error')
        ->getSession()->get('error');

    expect($error)->toContain('4/2')     // rooms
        ->and($error)->toContain('1/0'); // properties
});

it('allows a switch onto a plan that fits, and routes to the QR checkout', function () {
    config(['services.khqrpay.demo' => true, 'bakong.demo' => true]);
    $admin = makeAdmin();
    $this->actingAs($admin);
    stockRooms(4);

    $roomy = planNamed('roomy', ['max_rooms' => 10]);

    $this->post(route('admin.billing.renew'), ['plan' => $roomy->slug])
        ->assertRedirect();

    $row = KhqrPayment::latest('id')->firstOrFail();
    $this->get(route('admin.billing.checkout', $row->public_token))
        ->assertOk()
        ->assertSee($roomy->name);       // the page states what is being bought

    // The plan still rides on the payment, never on the live subscription.
    expect($row->checkout_payload['plan_id'])->toBe($roomy->id);
    expect(Subscription::where('account_id', $admin->id)->first()->plan_id)->not->toBe($roomy->id);
});

it('never blocks renewing the plan the account is already on', function () {
    config(['services.khqrpay.demo' => true, 'bakong.demo' => true]);
    $admin = makeAdmin();
    $tight = planNamed('tight', ['max_rooms' => 2]);
    giveActiveSubscription($admin, $tight);   // already on it, and already over it
    $this->actingAs($admin);
    stockRooms(4);

    // Refusing this would leave a lapsed account with no working button on the
    // billing page — and EnsureSubscriptionActive sends them straight back here.
    $this->post(route('admin.billing.renew'), ['plan' => $tight->slug])
        ->assertRedirect()
        ->assertSessionMissing('error');

    expect(KhqrPayment::count())->toBe(1);
});

it('refuses a plan that is no longer on sale', function () {
    config(['services.khqrpay.demo' => true]);
    $admin = makeAdmin();
    $this->actingAs($admin);

    $retired = planNamed('retired', ['is_active' => false]);

    $this->post(route('admin.billing.renew'), ['plan' => $retired->slug])
        ->assertSessionHas('error', __('messages.plan_unavailable'));

    expect(KhqrPayment::count())->toBe(0);
});

it('keeps a retired current plan on the grid so the account can still renew it', function () {
    $admin = makeAdmin();
    $retired = planNamed('legacy', ['is_active' => false]);
    giveActiveSubscription($admin, $retired);
    $this->actingAs($admin);

    $this->get(route('admin.billing.index'))
        ->assertOk()
        ->assertSee($retired->name)
        ->assertSee(__('messages.renew_via_khqr'));
});

it('disables the button on the billing page for a plan the account has outgrown', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);
    stockRooms(4);
    planNamed('small', ['max_rooms' => 2]);

    planNamed('roomy', ['max_rooms' => 10]);

    $html = $this->get(route('admin.billing.index'))->assertOk()->getContent();

    // The page refuses in the same words the POST would, so the two cannot
    // be reported as two different bugs.
    expect($html)->toContain('4/2');
    expect($html)->toContain('disabled');
    // …and the plan that DOES fit is offered, behind a confirm naming its price.
    expect($html)->toContain(__('messages.plan_switch_confirm_ok'));
    expect($html)->toContain('Switch to Roomy for $12.00');
});

it('applies the purchased plan on the checkout page, not the one being left', function () {
    config(['services.khqrpay.demo' => true, 'bakong.demo' => true]);
    $admin = makeAdmin();
    $this->actingAs($admin);
    $target = planNamed('target');

    $this->post(route('admin.billing.renew'), ['plan' => $target->slug, 'billing_cycle' => 'yearly']);
    $row = KhqrPayment::latest('id')->firstOrFail();

    expect($row->purchasedPlan()->id)->toBe($target->id);
    expect($row->purchasedCycle())->toBe('yearly');

    // …and that is exactly what finalize applies.
    app(KhqrPaymentService::class)->finalizeSubscription($row);
    expect(Subscription::where('account_id', $admin->id)->first()->plan_id)->toBe($target->id);
});

/**
 * A plan with no yearly price is billed MONTHLY whatever the toggle says —
 * renew() coerces the cycle and priceFor() falls back. The money was always
 * right; the LABEL printed the monthly figure under "/yr", so a $12 plan read
 * "$12/year" and charged $12 for thirty days. <x-plan-price> is the one place
 * that decides it now, for all three pricing surfaces.
 */
it('never labels a monthly-only plan as a yearly price, and charges it monthly', function () {
    config(['services.khqrpay.demo' => true, 'bakong.demo' => true]);
    $admin = makeAdmin();
    $this->actingAs($admin);

    $monthlyOnly = planNamed('monthly-only', ['price_usd' => 12, 'price_yearly_usd' => null]);

    $html = $this->get(route('admin.billing.index'))->assertOk()->getContent();

    // The yearly branch of the card prints /mo, and says why the toggle did nothing.
    expect($html)->toContain(__('messages.plan_monthly_only'));
    expect(substr_count($html, '/'.__('messages.year')))->toBe(0);

    // …and buying it on the yearly toggle really is a monthly term at $12.
    $this->post(route('admin.billing.renew'), ['plan' => $monthlyOnly->slug, 'billing_cycle' => 'yearly']);
    $row = KhqrPayment::latest('id')->firstOrFail();

    expect((float) $row->amount)->toBe(12.0);
    expect($row->purchasedCycle())->toBe('monthly');

    app(KhqrPaymentService::class)->finalizeSubscription($row);
    $sub = Subscription::where('account_id', $admin->id)->firstOrFail();
    expect(round(now()->diffInDays($sub->expires_at)))->toBeLessThan(70.0); // a month, not a year
});

it('still shows a real yearly price as a yearly price', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);
    // Every plan on the page must have a yearly price, or the note is on the
    // page for the OTHER plan and the assertion below means nothing.
    Plan::where('slug', 'test-unlimited')->update(['price_yearly_usd' => 99]);
    planNamed('has-yearly', ['price_usd' => 12, 'price_yearly_usd' => 120]);

    $html = $this->get(route('admin.billing.index'))->assertOk()->getContent();

    expect($html)->toContain('$120');
    expect($html)->toContain('/'.__('messages.year'));
    expect($html)->not->toContain(__('messages.plan_monthly_only'));
});
