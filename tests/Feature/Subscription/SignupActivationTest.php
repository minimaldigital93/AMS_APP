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

it('creates a pending account + subscription and shows the checkout QR on signup', function () {
    Http::fake();

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
    expect($payment)->not->toBeNull()
        ->and($payment->provider)->toBe('bakong')
        ->and($payment->qr_payload)->not->toBeEmpty();

    // THE CUSTOMER NEVER LEAVES. khqr.cc was a hosted checkout, so signup used
    // to redirect()->away() to someone else's domain — a one-way door this app
    // could say nothing through, which is what the two metered preflight probes
    // existed to guard. The direct Bakong QR is built here and shown here.
    $response->assertRedirect(route('subscribe.checkout', $payment->public_token));

    // And minting it cost nothing: the payload is EMV built locally.
    Http::assertNothingSent();
});

it('does not 500 when the payout account is not configured — rolls back and says so', function () {
    // Cleared / never-configured state: no Payment Settings row, blank .env.
    config(['bakong.account_id' => '', 'bakong.demo' => false]);
    Http::fake();

    $response = $this->post(route('subscribe.store'), [
        'name' => 'No Payout Owner',
        'phone' => '0999000444',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    // Its own message, not the generic "try again in a moment": nobody can
    // retry their way out of an unset payout account.
    $response->assertRedirect();
    $response->assertSessionHas('error', __('messages.bakong_account_missing'));

    Http::assertNothingSent();
    // The whole signup transaction rolled back — no orphaned account.
    expect(User::where('phone', '0999000444')->exists())->toBeFalse();
    expect(Subscription::count())->toBe(0);
});

it('does not 500 when Bakong is switched off entirely — rolls back and shows an error', function () {
    config(['bakong.enabled' => false]);
    Http::fake();

    $response = $this->post(route('subscribe.store'), [
        'name' => 'Unlucky Owner',
        'phone' => '0999000999',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $response->assertRedirect();           // back to the form, NOT a 500
    $response->assertSessionHas('error');

    Http::assertNothingSent();
    expect(User::where('phone', '0999000999')->exists())->toBeFalse();
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
