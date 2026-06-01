<?php

namespace App\Http\Controllers\Admin;

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
        $validated = $request->validate(['plan' => ['required', 'exists:plans,slug']]);
        $plan = Plan::where('slug', $validated['plan'])->firstOrFail();
        $accountId = current_account_id();

        // One subscription row per account — reuse it for renewals/upgrades.
        $subscription = Subscription::updateOrCreate(
            ['account_id' => $accountId],
            ['plan_id' => $plan->id]
        );

        $row = $khqr->createSubscriptionQr($subscription, (float) $plan->price_usd, route('admin.billing.index'));

        return redirect()->route('admin.billing.checkout', $row->transaction_id);
    }

    public function checkout(string $transaction): View|RedirectResponse
    {
        $payment = KhqrPayment::where('transaction_id', $transaction)
            ->whereNotNull('subscription_id')
            ->firstOrFail();

        if ($payment->isPaid()) {
            return redirect()->route('admin.billing.index')->with('success', __('messages.flash_subscription_renewed'));
        }

        $payment->load('subscription.plan');

        return view('admin.billing.checkout', [
            'payment' => $payment,
            'statusUrl' => route('admin.billing.status', $payment->transaction_id),
            'redirectUrl' => route('admin.billing.index'),
        ]);
    }

    public function status(string $transaction, KhqrPaymentService $khqr): JsonResponse
    {
        $payment = KhqrPayment::where('transaction_id', $transaction)
            ->whereNotNull('subscription_id')
            ->firstOrFail();

        if (! $payment->isPaid() && $khqr->verify($payment)) {
            $khqr->finalize($payment);
            $payment->refresh();
        }

        return response()->json([
            'status' => $payment->status,
            'paid' => $payment->isPaid(),
            'redirect' => $payment->isPaid() ? route('admin.billing.index') : null,
        ]);
    }
}
