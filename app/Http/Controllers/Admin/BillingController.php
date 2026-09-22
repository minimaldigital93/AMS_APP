<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PlatformPayoutNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Payment\SubscriptionCheckout;
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
        $usage = $this->subscriptions->usage($accountId);
        $plans = Plan::where('is_active', true)->orderBy('price_usd')->get();

        // A retired plan is off the menu — but an account SITTING on one must
        // still be able to renew it, so it joins the grid rather than leaving
        // the page with no button for the plan they are actually paying for.
        if ($usage['plan'] && ! $plans->contains('id', $usage['plan']->id)) {
            $plans = $plans->push($usage['plan'])->sortBy('price_usd')->values();
        }

        return view('admin.billing.index', [
            'usage' => $usage,
            'subscription' => $this->subscriptions->activeSubscription($accountId)
                ?? Subscription::where('account_id', $accountId)->latest('id')->with('plan')->first(),
            'plans' => $plans,
            // Why each plan may NOT be switched to — the refusal in words, or
            // null when the plan fits. Keyed by plan id, computed off one set
            // of counts and worded by the same service renew() enforces with,
            // so the page cannot offer a switch the POST then refuses, or
            // refuse one in different words.
            'planFaults' => $plans->mapWithKeys(fn (Plan $p) => [
                $p->id => $this->planFault($p, $usage),
            ])->all(),
        ]);
    }

    /**
     * Why this account may not switch onto $plan — null when it may.
     *
     * The account's CURRENT plan always passes: renewing what you are already
     * on can never put you further over a cap, and refusing it would leave a
     * lapsed account with no button on this page that works.
     */
    private function planFault(Plan $plan, array $usage): ?string
    {
        if ($usage['plan']?->id === $plan->id) {
            return null;
        }

        $shortfalls = $this->subscriptions->planShortfalls($plan, $usage);

        return $shortfalls === [] ? null : $this->subscriptions->shortfallMessage($plan, $shortfalls);
    }

    /**
     * Start a renewal or upgrade: mint a subscription QR and show it here.
     */
    public function renew(Request $request, SubscriptionCheckout $checkout): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
            'billing_cycle' => ['nullable', 'in:monthly,yearly'],
        ]);
        $plan = Plan::where('slug', $validated['plan'])->firstOrFail();
        $cycle = ($validated['billing_cycle'] ?? 'monthly') === 'yearly' && $plan->hasYearly() ? 'yearly' : 'monthly';
        $accountId = current_account_id();

        // ------------------------------------------------- is this switch allowed?
        //
        // Renewing the CURRENT plan is always allowed and is checked first, on
        // purpose. Both rules below could otherwise trap an account with no way
        // to pay at all: a plan the superadmin has since retired, or one whose
        // caps were tightened under an account that had already outgrown them,
        // would leave the only button on this page refusing itself — and
        // EnsureSubscriptionActive sends them straight back here.
        $usage = $this->subscriptions->usage($accountId);
        $isRenewal = $usage['plan']?->id === $plan->id;

        if (! $isRenewal && ! $plan->is_active) {
            // A stale tab, or a slug typed by hand. The grid only ever offers
            // active plans plus the one being renewed.
            return back()->with('error', __('messages.plan_unavailable'));
        }

        if (($fault = $this->planFault($plan, $usage)) !== null) {
            // Refused BEFORE the QR is minted. finalizeSubscription() applies
            // the purchased plan the moment the money lands and the caps are
            // read straight off subscriptions.plan_id, so letting this through
            // takes the payment and then puts the account over every cap.
            return back()->with('error', $fault);
        }

        // There is deliberately NO gateway preflight here any more. It existed
        // because redirect()->away() to khqr.cc was a one-way door: a profile
        // that could not transact answered with a raw JSON body and this page
        // never got to say what went wrong. The direct Bakong checkout has no
        // door — the QR is built locally and shown below — so the two metered
        // probes that guarded it are pure cost and are gone with the provider.

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
            $row = $checkout->create($subscription, $plan->priceFor($cycle), $plan, $cycle);
        } catch (PlatformPayoutNotConfiguredException $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // Don't 500 the billing page when the payout identity or the Bakong
            // token is misconfigured.
            report($e);

            return back()->with('error', __('messages.subscription_payment_unavailable'));
        }

        $returnUrl = route('admin.billing.checkout', $row->public_token);

        // The direct Bakong flow keeps the admin here and shows them the QR;
        // handoffUrl() is the seam a hosted provider would plug back into.
        return ($handoff = $checkout->handoffUrl($row, $returnUrl))
            ? redirect()->away($handoff)
            : redirect()->to($returnUrl);
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

    /** The checkout page for a pending subscription payment. */
    public function checkout(string $token, SubscriptionCheckout $checkout): View|RedirectResponse
    {
        $payment = $this->resolveSubscriptionPayment($token);

        if ($payment->isPaid()) {
            return redirect()->route('admin.billing.index')->with('success', __('messages.flash_subscription_renewed'));
        }

        $payment->load('subscription.plan');

        return view('admin.billing.checkout', [
            'payment' => $payment,
            // What this QR is BUYING, off the payment's own payload — the
            // subscription still holds the old plan until the money lands, so
            // reading it here would tell a switching customer they are paying
            // for the plan they are leaving.
            'purchasedPlan' => $payment->purchasedPlan(),
            'purchasedCycle' => $payment->purchasedCycle(),
            'statusUrl' => route('admin.billing.status', $payment->public_token),
            'redirectUrl' => route('admin.billing.index'),
            // A data URI rendered from the row's own stored payload; null for
            // a legacy khqr.cc row, which was paid on someone else's page.
            'qrImage' => $checkout->qrImage($payment),
        ]);
    }

    /** Poll endpoint the checkout page calls until the payment lands. */
    public function status(string $token, SubscriptionCheckout $checkout): JsonResponse
    {
        $payment = $this->resolveSubscriptionPayment($token);
        $gatewayError = false;
        // Assume the gateway answered until a poll says otherwise: a thrown
        // exception below is a genuine miss, and every non-Bakong row reports
        // true, preserving the previous behaviour exactly.
        $gatewayAnswered = true;
        $quotaExhausted = null;

        try {
            // Routed on the ROW, not on configuration, so a legacy khqr.cc
            // payment is never asked about at a gateway where it does not
            // exist — which would read as unpaid.
            [
                'payment' => $payment,
                'gateway_error' => $gatewayError,
                // Did we actually ASK? A poll the cooldown absorbed is not evidence
                // the gateway is healthy, and treating it as such is what kept the
                // stall warning from ever appearing.
                'gateway_answered' => $gatewayAnswered,
                // When today's allowance resets, if it is already spent.
                'quota_exhausted' => $quotaExhausted,
            ] = $checkout->poll($payment);
        } catch (\Throwable $e) {
            // Never let a gateway failure 500 the poll — the page swallows a
            // non-OK response and would spin forever. Say so instead.
            report($e);
            $gatewayError = true;
            $gatewayAnswered = false;
        }

        return response()->json([
            'status' => $payment->status,
            'paid' => $payment->isPaid(),
            'gateway_error' => $gatewayError,
            // Did we actually ASK? A poll the cooldown absorbed is not evidence
            // the gateway is healthy, and treating it as such is what kept the
            // stall warning from ever appearing.
            'gateway_answered' => $gatewayAnswered,
            // When today's allowance resets, if it is already spent.
            'quota_exhausted' => $quotaExhausted,
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
