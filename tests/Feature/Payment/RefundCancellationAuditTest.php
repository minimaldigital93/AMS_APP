<?php

use App\Models\AuditLog;
use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\RefundService;
use App\Services\RevenueExpense\KhqrPaymentService;
use App\Services\Subscription\SubscriptionService;

beforeEach(function () {
    seedRoles();
    $this->plan = Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24,
        'max_floors' => 4, 'max_apartments' => 200, 'billing_period_days' => 30, 'is_active' => true,
    ]);
    $this->owner = User::factory()->create(['status' => 'inactive']);
    $this->owner->forceFill(['account_id' => $this->owner->id])->save();
    $this->sub = Subscription::create([
        'account_id' => $this->owner->id, 'plan_id' => $this->plan->id, 'status' => 'pending',
    ]);
});

function paidSubscriptionPayment($sub): KhqrPayment
{
    $payment = KhqrPayment::create([
        'transaction_id' => 'SUB-PAID-'.$sub->id,
        'subscription_id' => $sub->id,
        'amount' => 24,
        'currency' => 'USD',
        'status' => 'qr_generated',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
    ]);
    app(KhqrPaymentService::class)->finalize($payment);

    return $payment->fresh();
}

it('snapshots price_paid and writes an audit log on activation', function () {
    paidSubscriptionPayment($this->sub);

    expect($this->sub->fresh()->price_paid)->toBe(24.0);
    expect(AuditLog::where('action', 'subscription.activated')->count())->toBe(1);
});

it('records a refund: payment becomes refunded, refund row + audit written', function () {
    $payment = paidSubscriptionPayment($this->sub);

    app(RefundService::class)->record($payment, 24.0, 'duplicate charge', 'BANK-REF-9', revokeAccess: false);

    expect($payment->fresh()->status)->toBe('refunded');
    expect(Refund::where('khqr_payment_id', $payment->id)->where('status', 'completed')->count())->toBe(1);
    expect(AuditLog::where('action', 'payment.refunded')->count())->toBe(1);
});

it('revokes subscription access when a refund is recorded with revoke_access', function () {
    $payment = paidSubscriptionPayment($this->sub);
    expect($this->sub->fresh()->isActive())->toBeTrue();

    app(RefundService::class)->record($payment, 24.0, 'fraud', null, revokeAccess: true);

    $sub = $this->sub->fresh();
    expect($sub->status)->toBe('cancelled');
    expect($sub->isActive())->toBeFalse();
});

it('refuses to refund a payment that is not paid', function () {
    $unpaid = KhqrPayment::create([
        'transaction_id' => 'SUB-UNPAID-1', 'subscription_id' => $this->sub->id,
        'amount' => 24, 'currency' => 'USD', 'status' => 'qr_generated',
        'settlement_target' => 'platform', 'channel' => 'api', 'checkout_payload' => [],
    ]);

    app(RefundService::class)->record($unpaid, 24.0, 'nope');
})->throws(RuntimeException::class);

it('cancels at period end via the service: keeps access, marks cancelled_at', function () {
    paidSubscriptionPayment($this->sub);
    $expiry = $this->sub->fresh()->expires_at;

    app(SubscriptionService::class)->cancel($this->owner->id, 'too expensive', immediate: false);

    $sub = $this->sub->fresh();
    expect($sub->cancelled_at)->not->toBeNull();
    expect($sub->expires_at->toDateTimeString())->toBe($expiry->toDateTimeString()); // access retained
    expect(AuditLog::where('action', 'subscription.cancelled')->count())->toBe(1);
});

it('lets a superadmin record a refund through the payments console', function () {
    $payment = paidSubscriptionPayment($this->sub);
    $su = User::factory()->create();
    $su->assignRole('superadmin');

    $this->actingAs($su)
        ->post(route('superadmin.payments.refund', $payment), [
            'amount' => 24, 'reason' => 'customer request',
        ])
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe('refunded');
    expect(AuditLog::where('action', 'payment.refunded')->first()->actor_id)->toBe($su->id);
});

it('renders the superadmin payments console and blocks non-superadmins', function () {
    $su = User::factory()->create();
    $su->assignRole('superadmin');
    $this->actingAs($su)->get(route('superadmin.payments.index'))->assertOk();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin)->get(route('superadmin.payments.index'))->assertForbidden();
});
