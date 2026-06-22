<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\KhqrPlatformCredentialsMissingException;
use App\Http\Controllers\Controller;
use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\RevenueExpense\KhqrPaymentService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Self-service billing for an admin account: view plan + usage, and renew or
 * upgrade by paying the plan price via KHQR. Reuses the same KhqrPaymentService
 * subscription path as the public signup funnel.
 */
class BillingController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(): View
    {
        $accountId = current_account_id();

        return view('admin.billing.index', [
            'usage' => $this->subscriptions->usage($accountId),
            'subscription' => $this->subscriptions->activeSubscription($accountId)
                ?? Subscription::where('account_id', $accountId)->latest('id')->with('plan')->first(),
            'plans' => Plan::where('is_active', true)->orderBy('price_usd')->get(),
        ]);
    }

    public function renew(Request $request, KhqrPaymentService $khqr): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
            'billing_cycle' => ['nullable', 'in:monthly,yearly'],
        ]);
        $plan = Plan::where('slug', $validated['plan'])->firstOrFail();
        $cycle = ($validated['billing_cycle'] ?? 'monthly') === 'yearly' && $plan->hasYearly() ? 'yearly' : 'monthly';
        $accountId = current_account_id();

        // One subscription row per account — reuse it for renewals/upgrades.
        $subscription = Subscription::updateOrCreate(
            ['account_id' => $accountId],
            ['plan_id' => $plan->id, 'billing_cycle' => $cycle]
        );

        try {
            $row = $khqr->createSubscriptionQr($subscription, $plan->priceFor($cycle));
        } catch (KhqrPlatformCredentialsMissingException $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // Don't 500 the billing page when KHQRPay is down / misconfigured.
            report($e);

            return back()->with('error', __('messages.subscription_payment_unavailable'));
        }

        return redirect()->away(
            $khqr->subscriptionCheckoutUrl($row, route('admin.billing.checkout', $row->public_token))
        );
    }

    /** Self-service cancel: keep access until the period ends, just stop renewing. */
    public function cancel(Request $request): RedirectResponse
    {
        $this->subscriptions->cancel(
            accountId: current_account_id(),
            reason: (string) $request->input('reason', ''),
            immediate: false,
            actor: $request->user(),
        );

        return back()->with('success', __('messages.subscription_cancelled'));
    }

    public function checkout(string $token): View|RedirectResponse
    {
        $payment = $this->resolveSubscriptionPayment($token);

        if ($payment->isPaid()) {
            return redirect()->route('admin.billing.index')->with('success', __('messages.flash_subscription_renewed'));
        }

        $payment->load('subscription.plan');

        return view('admin.billing.checkout', [
            'payment' => $payment,
            'statusUrl' => route('admin.billing.status', $payment->public_token),
            'redirectUrl' => route('admin.billing.index'),
        ]);
    }

    public function status(string $token, KhqrPaymentService $khqr): JsonResponse
    {
        $payment = $khqr->pollAndAdvance($this->resolveSubscriptionPayment($token));

        return response()->json([
            'status' => $payment->status,
            'paid' => $payment->isPaid(),
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'redirect' => $payment->isPaid() ? route('admin.billing.index') : null,
        ]);
    }

    /** Resolve this account's subscription payment by its public token, or 404. */
    private function resolveSubscriptionPayment(string $token): KhqrPayment
    {
        return KhqrPayment::where('public_token', $token)
            ->whereNotNull('subscription_id')
            ->firstOrFail();
    }
}
