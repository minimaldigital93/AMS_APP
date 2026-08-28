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

class BillingController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /**
     * The billing page: current plan, usage against its caps, and the upgrade grid.
     */
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

    /**
     * Start a renewal or upgrade: mint a subscription QR and hand off to KHQRPay.
     */
    public function renew(Request $request, KhqrPaymentService $khqr): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
            'billing_cycle' => ['nullable', 'in:monthly,yearly'],
        ]);
        $plan = Plan::where('slug', $validated['plan'])->firstOrFail();
        $cycle = ($validated['billing_cycle'] ?? 'monthly') === 'yearly' && $plan->hasYearly() ? 'yearly' : 'monthly';
        $accountId = current_account_id();

        // Ask the gateway whether it can take a payment BEFORE minting anything.
        // redirect()->away() below is a one-way door: once the browser is on
        // khqr.cc, a profile that can't transact answers with a raw JSON body
        // and this page never gets to say what went wrong. Refuse here so the
        // billing page shows the warning instead — and leaves no orphan QR.
        // khqr_fault is the popup's trigger: the billing page flashes `error`
        // for ordinary failures too, and a diagnostics dialog is only the right
        // answer when the GATEWAY is what refused.
        if ($fault = $khqr->platformCheckoutFault()) {
            return back()->with('error', $fault)->with('khqr_fault', true);
        }

        // One subscription row per account — reuse it for renewals/upgrades.
        //
        // The chosen plan is deliberately NOT written here: caps are read from
        // subscriptions.plan_id, so stamping an upgrade before payment hands the
        // customer the bigger plan's caps for free the moment they abandon
        // checkout. It rides on the payment instead and is applied by
        // KhqrPaymentService::finalizeSubscription() once the money lands.
        // A brand-new row is safe to write — `pending` grants no access.
        $subscription = Subscription::firstOrNew(['account_id' => $accountId]);
        if (! $subscription->exists) {
            $subscription->fill([
                'plan_id' => $plan->id,
                'billing_cycle' => $cycle,
                'status' => 'pending',
            ])->save();
        }

        try {
            $row = $khqr->createSubscriptionQr($subscription, $plan->priceFor($cycle), $plan, $cycle);
        } catch (KhqrPlatformCredentialsMissingException $e) {
            report($e);

            return back()->with('error', $e->getMessage())->with('khqr_fault', true);
        } catch (\Throwable $e) {
            // Don't 500 the billing page when KHQRPay is down / misconfigured.
            report($e);

            return back()->with('error', __('messages.subscription_payment_unavailable'))->with('khqr_fault', true);
        }

        return redirect()->away(
            $khqr->subscriptionCheckoutUrl($row, route('admin.billing.checkout', $row->public_token))
        );
    }

    /**
     * Live gateway diagnostics behind the "payment problem" popup.
     *
     * Answers the question a failed checkout leaves behind — WHICH part of the
     * KHQR setup is refusing — instead of the one sentence the customer-facing
     * flash can say. Admin-only, because `detail` quotes the gateway verbatim
     * and names the profile id: it is the fix-it view, not the apology.
     *
     * Never 500s. This is the page someone opens precisely because something is
     * already broken, so a failure here has to report itself rather than
     * replace the popup with an error.
     */
    public function diagnostics(KhqrPaymentService $khqr): JsonResponse
    {
        try {
            $report = $khqr->platformDiagnostics();
            $report['last_fault'] = $khqr->lastPlatformCheckoutFault();

            return response()->json($report);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'healthy' => false,
                'checks' => [[
                    'key' => 'diagnostics',
                    'label' => __('messages.khqr_diag_failed'),
                    'state' => 'fail',
                    'detail' => $e->getMessage(),
                ]],
                'checked_at' => now()->toIso8601String(),
                'last_fault' => null,
            ]);
        }
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

    /** The hosted checkout page for a pending subscription payment. */
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

    /** Poll endpoint the checkout page calls until the payment lands. */
    public function status(string $token, KhqrPaymentService $khqr): JsonResponse
    {
        $payment = $this->resolveSubscriptionPayment($token);
        $gatewayError = false;

        try {
            $payment = $khqr->pollAndAdvance($payment);
            // A refusal reaches the page as gateway_error too — see
            // KhqrPaymentService::lastPollRefused().
            $gatewayError = $khqr->lastPollRefused();
        } catch (\Throwable $e) {
            // Never let a gateway failure 500 the poll — the page swallows a
            // non-OK response and would spin forever. Say so instead.
            report($e);
            $gatewayError = true;
        }

        return response()->json([
            'status' => $payment->status,
            'paid' => $payment->isPaid(),
            'gateway_error' => $gatewayError,
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
