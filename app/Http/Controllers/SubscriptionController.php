<?php

namespace App\Http\Controllers;

use App\Exceptions\KhqrPlatformCredentialsMissingException;
use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Theme;
use App\Models\User;
use App\Services\Payment\SubscriptionCheckout;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Public SaaS signup funnel: pick a plan (from the login pricing modal) → create
 * a pending account → pay the plan price via KHQR → the payment activates the
 * subscription and promotes the account to admin (see KhqrPaymentService).
 *
 * Everything here is unauthenticated (guest) — the user has no role until they pay.
 */
class SubscriptionController extends Controller
{
    /** Plan picker / signup form for the chosen plan. */
    public function create(Request $request): View
    {
        $plans = Plan::where('is_active', true)->orderBy('price_usd')->get();
        $selected = $plans->firstWhere('slug', $request->query('plan')) ?? $plans->first();
        $cycle = $request->query('billing_cycle') === 'yearly' ? 'yearly' : 'monthly';

        return view('subscribe.register', compact('plans', 'selected', 'cycle'));
    }

    /**
     * Create the pending account + subscription, then either start the plan's
     * free trial (account usable immediately, no payment) or KHQR checkout.
     */
    public function store(Request $request, SubscriptionCheckout $checkout, SubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required', 'string', 'max:255',
                Rule::unique('users', 'phone')->where(fn ($q) => $q
                    ->where(fn ($row) => $row
                        ->whereNull('account_id')
                        ->orWhereColumn('account_id', '!=', 'id')
                        ->orWhere(fn ($owner) => $owner
                            ->whereColumn('account_id', 'id')
                            ->where(fn ($live) => $live
                                ->where('status', 'suspended')
                                ->orWhereExists(fn ($sub) => $sub
                                    ->from('subscriptions')
                                    ->whereColumn('subscriptions.account_id', 'users.id')
                                    ->where(fn ($ever) => $ever
                                        // Ever activated — paid or trialed.
                                        ->whereNotNull('subscriptions.started_at')
                                        // …or live right now (belt and braces
                                        // for legacy rows with no started_at).
                                        ->orWhere(fn ($now) => $now
                                            ->whereIn('subscriptions.status', ['active', 'trialing'])
                                            ->where(fn ($exp) => $exp
                                                ->whereNull('subscriptions.expires_at')
                                                ->orWhere('subscriptions.expires_at', '>', now()))))))))),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'plan' => ['required', 'exists:plans,slug'],
            'billing_cycle' => ['nullable', 'in:monthly,yearly'],
            'start_trial' => ['nullable', 'boolean'],
        ], [
            'phone.unique' => __('messages.validation_phone_taken'),
        ]);

        $plan = Plan::where('slug', $validated['plan'])->firstOrFail();
        $cycle = ($validated['billing_cycle'] ?? 'monthly') === 'yearly' && $plan->hasYearly() ? 'yearly' : 'monthly';
        $wantsTrial = $request->boolean('start_trial') && $plan->hasTrial();

        if ($wantsTrial) {
            DB::transaction(function () use ($validated, $plan, $subscriptions) {
                $user = $this->provisionOwner($validated, 'active');
                $user->assignRole('admin');

                $subscriptions->startTrial($user->id, $plan);
            });

            return redirect()->route('login')
                ->with('status', __('messages.flash_trial_started', ['days' => $plan->trial_days]));
        }

        // Ask the gateway whether it can take a payment BEFORE creating anything.
        // redirect()->away() below is a one-way door: once the browser is on
        // khqr.cc, a profile that can't transact answers with a raw JSON body
        // and this app never gets to say what went wrong. Refuse here instead,
        // so the customer sees a warning on this form with their input intact —
        // and, like the missing-credentials guard, no half-finished signup is
        // left behind.
        // khqr_fault opens the "payment could not be started" popup on the form
        // — see resources/views/components/khqr-diagnostics.blade.php. The guest
        // copy of it never probes the gateway, so it says what happened and what
        // to do without exposing the profile's internals.
        //
        // Under the DIRECT Bakong integration there is no door: the QR is built
        // locally and shown on our own page, so preflightFault() returns null
        // and the two metered probes are never made.
        if ($fault = $checkout->preflightFault()) {
            return back()->withInput()->with('error', $fault)->with('khqr_fault', true);
        }

        try {
            $row = DB::transaction(function () use ($validated, $plan, $checkout, $cycle) {
                $user = $this->provisionOwner($validated, 'inactive');

                // Reuse the account's pending subscription if it already has one
                // (an earlier abandoned attempt) instead of creating a second row.
                $subscription = Subscription::updateOrCreate(
                    ['account_id' => $user->id],
                    ['plan_id' => $plan->id, 'status' => 'pending', 'billing_cycle' => $cycle],
                );

                return $checkout->create($subscription, $plan->priceFor($cycle), $plan, $cycle);
            });
        } catch (KhqrPlatformCredentialsMissingException $e) {
            report($e);

            return back()->withInput()->with('error', $e->getMessage())->with('khqr_fault', true);
        } catch (\Throwable $e) {
            // A KHQRPay outage / misconfiguration must not 500 the public signup
            // page — roll back (the transaction already did) and show a friendly
            // message instead of an uncaught exception.
            report($e);

            return back()->withInput()->with('error', __('messages.subscription_payment_unavailable'))->with('khqr_fault', true);
        }

        $returnUrl = route('subscribe.checkout', $row->public_token);

        // A hosted-checkout provider takes the browser away; the direct Bakong
        // flow keeps the customer here and shows them the QR, which is also
        // what removes the one-way door this method used to have to guard.
        return ($handoff = $checkout->handoffUrl($row, $returnUrl))
            ? redirect()->away($handoff)
            : redirect()->to($returnUrl);
    }

    /**
     * Create (or take over) the account-owner user for a signup.
     *
     * Validation has already rejected the phone if an *ever-activated* owner
     * holds it (subscription started, live, or suspended), so any existing
     * owner row here is an abandoned never-paid signup safe to reuse. We take
     * it over instead of stacking a duplicate owner on the same phone, which
     * would otherwise make login-by-phone ambiguous. The row is reset to
     * `inactive`, so it stays locked out (LoginRequest blocks non-active
     * logins) until payment finalizes and re-grants the admin role + active
     * status.
     */
    private function provisionOwner(array $validated, string $status): User
    {
        $user = User::whereColumn('account_id', 'id')
            ->where('phone', $validated['phone'])
            ->latest('id')
            ->first() ?? new User;

        // Defence in depth against a stale form or a race slipping past the
        // uniqueness rule: taking over a row that has ever been activated would
        // reset a real customer's password and lock them out of their own data.
        if ($user->exists && Subscription::where('account_id', $user->id)->whereNotNull('started_at')->exists()) {
            throw ValidationException::withMessages([
                'phone' => __('messages.validation_phone_taken'),
            ]);
        }

        $user->forceFill([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'status' => $status,
            // A new row already carries the signup theme (User::$attributes);
            // a taken-over legacy row may predate it, so fill the gap without
            // clobbering a theme someone actually picked.
            'theme' => $user->theme ?: Theme::SIGNUP_SLUG,
        ])->save();

        // An account owner points at itself.
        if ($user->account_id !== $user->id) {
            $user->forceFill(['account_id' => $user->id])->save();
        }

        return $user;
    }

    /** Browser return page after KHQRPay checkout; polls until the webhook confirms. */
    public function checkout(string $token, SubscriptionCheckout $checkout): View|RedirectResponse
    {
        $payment = $this->resolveSubscriptionPayment($token);

        if ($payment->isPaid()) {
            return redirect()->route('login')->with('status', __('messages.flash_subscription_activated_signin'));
        }

        $payment->load('subscription.plan');

        return view('subscribe.checkout', [
            'payment' => $payment,
            // Null for a hosted checkout (the payer paid on khqr.cc); a data
            // URI for a direct Bakong payment, rendered from the row's own
            // stored payload so the page makes no request for it.
            'qrImage' => $checkout->qrImage($payment),
        ]);
    }

    /** Polled by the checkout page; verifies + activates on confirmation. */
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
            // Routed on the ROW, not on configuration: a payment minted at
            // khqr.cc keeps being asked about at khqr.cc for the rest of its
            // life, so a customer mid-checkout when the provider switch is
            // flipped is not suddenly polled at a gateway their QR does not
            // exist on — where it would read as unpaid.
            [
                'payment' => $payment,
                'gateway_error' => $gatewayError,
                // Did we actually ASK? A poll the cooldown absorbed is not evidence
                // the gateway is healthy, and treating it as such is what kept the
                // stall warning from ever appearing.
                'gateway_answered' => $gatewayAnswered,
                // When today's allowance resets, if it is already spent.
                'quota_exhausted' => $quotaExhausted,
                'gateway_answered' => $gatewayAnswered,
                'quota_exhausted' => $quotaExhausted,
            ] = $checkout->poll($payment);
        } catch (\Throwable $e) {
            // A gateway/booking failure must not turn the poll into a 500 the
            // page silently swallows — the customer would watch the spinner
            // forever. Report it, and tell the page so it can say so.
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
            'redirect' => $payment->isPaid() ? route('login') : null,
        ]);
    }

    /** Resolve a subscription payment by its unguessable public token, or 404. */
    private function resolveSubscriptionPayment(string $token): KhqrPayment
    {
        return KhqrPayment::where('public_token', $token)
            ->whereNotNull('subscription_id')
            ->firstOrFail();
    }
}
