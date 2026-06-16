<?php

use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedRoles();
    $this->plan = Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24,
        'max_floors' => 4, 'max_apartments' => 200, 'billing_period_days' => 30, 'is_active' => true,
    ]);
});

it('creates a pending account + subscription and redirects to checkout on signup', function () {
    config(['services.khqrpay.demo' => true]);

    $response = $this->post(route('subscribe.store'), [
        'name' => 'New Owner',
        'phone' => '0999000111',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $user = User::where('phone', '0999000111')->first();
    expect($user)->not->toBeNull();
    expect($user->account_id)->toBe($user->id);
    expect($user->hasRole('admin'))->toBeFalse(); // not until paid

    $sub = Subscription::where('account_id', $user->id)->first();
    expect($sub->status)->toBe('pending');

    $payment = KhqrPayment::where('subscription_id', $sub->id)->first();
    expect($payment)->not->toBeNull();
    $response->assertRedirect(route('subscribe.checkout', $payment->public_token));
});

it('does not 500 when KHQRPay fails during signup — rolls back and shows an error', function () {
    config(['services.khqrpay.demo' => false, 'services.khqrpay.secret' => 'x', 'services.khqrpay.profile_id' => 'p']);

    // KHQRPay is down / misconfigured — minting the QR fails.
    Http::fake(['khqr.cc/*' => Http::response('Not Found', 404)]);

    $response = $this->post(route('subscribe.store'), [
        'name' => 'Unlucky Owner',
        'phone' => '0999000999',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $response->assertRedirect();           // back to the form, NOT a 500
    $response->assertSessionHas('error');

    // The whole signup transaction rolled back — no orphaned account/subscription.
    expect(User::where('phone', '0999000999')->exists())->toBeFalse();
    expect(Subscription::count())->toBe(0);
});

it('does not 500 (or call the gateway) when platform KHQRPay credentials are not configured', function () {
    // Cleared / never-configured state: no DB row, blank env credentials.
    config(['services.khqrpay.demo' => false, 'services.khqrpay.profile_id' => '', 'services.khqrpay.secret' => '']);

    // The fallback guard must short-circuit BEFORE any HTTP call is attempted.
    Http::fake(['khqr.cc/*' => Http::response('Not Found', 404)]);

    $response = $this->post(route('subscribe.store'), [
        'name' => 'No Creds Owner',
        'phone' => '0999000444',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $response->assertRedirect();           // friendly redirect, NOT a 500
    $response->assertSessionHas('error');

    Http::assertNothingSent();             // never reached the gateway
    expect(User::where('phone', '0999000444')->exists())->toBeFalse();
    expect(Subscription::count())->toBe(0);
});

it('activates the subscription and promotes the account to admin on payment', function () {
    $user = User::factory()->create(['phone' => '0999000222', 'status' => 'inactive']);
    $user->forceFill(['account_id' => $user->id])->save();

    $sub = Subscription::create([
        'account_id' => $user->id,
        'plan_id' => $this->plan->id,
        'status' => 'pending',
    ]);

    $payment = KhqrPayment::create([
        'transaction_id' => 'SUB-TEST-1',
        'subscription_id' => $sub->id,
        'amount' => 24,
        'currency' => 'USD',
        'status' => 'pending',
        'checkout_payload' => ['type' => 'subscription', 'subscription_id' => $sub->id],
    ]);

    app(KhqrPaymentService::class)->finalize($payment);

    expect($sub->fresh()->status)->toBe('active');
    expect($sub->fresh()->expires_at)->not->toBeNull();
    expect($payment->fresh()->status)->toBe('paid');
    expect($user->fresh()->hasRole('admin'))->toBeTrue();
    expect($user->fresh()->status)->toBe('active'); // can now log in
});

it('is idempotent — finalizing twice does not double-extend or error', function () {
    $user = User::factory()->create(['phone' => '0999000333']);
    $user->forceFill(['account_id' => $user->id])->save();
    $sub = Subscription::create(['account_id' => $user->id, 'plan_id' => $this->plan->id, 'status' => 'pending']);
    $payment = KhqrPayment::create([
        'transaction_id' => 'SUB-TEST-2', 'subscription_id' => $sub->id, 'amount' => 24,
        'currency' => 'USD', 'status' => 'pending', 'checkout_payload' => ['type' => 'subscription'],
    ]);

    $svc = app(KhqrPaymentService::class);
    $svc->finalize($payment);
    $firstExpiry = $sub->fresh()->expires_at;

    $svc->finalize($payment->fresh());

    expect($sub->fresh()->expires_at->toDateTimeString())->toBe($firstExpiry->toDateTimeString());
});
