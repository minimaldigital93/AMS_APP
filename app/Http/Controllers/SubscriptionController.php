<?php

namespace App\Http\Controllers;

use App\Exceptions\KhqrPlatformCredentialsMissingException;
use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RevenueExpense\KhqrPaymentService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
    public function store(Request $request, KhqrPaymentService $khqr, SubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // A phone is only "taken" when it belongs to a *successfully
            // registered* account owner: someone who actually paid and is still
            // a live customer (an active/trialing subscription that hasn't
            // expired), or an owner the platform has deliberately suspended.
            // Every other owner row is a failed/lapsed signup we let re-register:
            //   - abandoned never-paid attempts (subscription still 'pending'),
            //   - legacy rows the `status` column default left non-inactive but
            //     that never reached a live subscription,
            //   - accounts whose subscription has expired or was cancelled.
            // Keying off the subscription (not `users.status`) is what fixes
            // re-registration: a user who didn't pay in time is NOT a completed
            // owner, so their phone stays free. Numbers used only by another
            // account's tenants/supervisors are not owners (account_id != id)
            // and are always free. provisionOwner() then takes over any such
            // reusable owner row rather than minting a duplicate owner.
            'phone' => [
                'required', 'string', 'max:255',
                Rule::unique('users', 'phone')->where(fn ($q) => $q
                    ->whereColumn('account_id', 'id')
                    ->where(fn ($owner) => $owner
                        ->where('status', 'suspended')
                        ->orWhereExists(fn ($sub) => $sub
                            ->from('subscriptions')
                            ->whereColumn('subscriptions.account_id', 'users.id')
                            ->whereIn('subscriptions.status', ['active', 'trialing'])
                            ->where(fn ($exp) => $exp
                                ->whereNull('subscriptions.expires_at')
                                ->orWhere('subscriptions.expires_at', '>', now()))))),
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

        try {
            $row = DB::transaction(function () use ($validated, $plan, $khqr, $cycle) {
                $user = $this->provisionOwner($validated, 'inactive');

                // Reuse the account's pending subscription if it already has one
                // (an earlier abandoned attempt) instead of creating a second row.
                $subscription = Subscription::updateOrCreate(
                    ['account_id' => $user->id],
                    ['plan_id' => $plan->id, 'status' => 'pending', 'billing_cycle' => $cycle],
                );

                return $khqr->createSubscriptionQr($subscription, $plan->priceFor($cycle));
            });
        } catch (KhqrPlatformCredentialsMissingException $e) {
            report($e);

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // A KHQRPay outage / misconfiguration must not 500 the public signup
            // page — roll back (the transaction already did) and show a friendly
            // message instead of an uncaught exception.
            report($e);

            return back()->withInput()->with('error', __('messages.subscription_payment_unavailable'));
        }

        return redirect()->away(
            $khqr->subscriptionCheckoutUrl($row, route('subscribe.checkout', $row->public_token))
        );
    }

    /**
     * Create (or take over) the account-owner user for a signup.
     *
     * Validation has already rejected the phone if a *successfully registered*
     * owner holds it (live subscription or suspended), so any existing owner
     * row here is a failed/lapsed signup safe to reuse. We take it over instead
     * of stacking a duplicate owner on the same phone, which would otherwise
     * make login-by-phone ambiguous. The row is reset to `inactive`, so it
     * stays locked out (LoginRequest blocks non-active logins) until payment
     * finalizes and re-grants the admin role + active status.
     */
    private function provisionOwner(array $validated, string $status): User
    {
        $user = User::whereColumn('account_id', 'id')
            ->where('phone', $validated['phone'])
            ->latest('id')
            ->first() ?? new User;

        $user->fill([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'status' => $status,
        ])->save();

        // An account owner points at itself.
        if ($user->account_id !== $user->id) {
            $user->forceFill(['account_id' => $user->id])->save();
        }

        return $user;
    }

    /** Browser return page after KHQRPay checkout; polls until the webhook confirms. */
    public function checkout(string $token): View|RedirectResponse
    {
        $payment = $this->resolveSubscriptionPayment($token);

        if ($payment->isPaid()) {
            return redirect()->route('login')->with('status', __('messages.flash_subscription_activated_signin'));
        }

        $payment->load('subscription.plan');

        return view('subscribe.checkout', compact('payment'));
    }

    /** Polled by the checkout page; verifies + activates on confirmation. */
    public function status(string $token, KhqrPaymentService $khqr): JsonResponse
    {
        $payment = $khqr->pollAndAdvance($this->resolveSubscriptionPayment($token));

        return response()->json([
            'status' => $payment->status,
            'paid' => $payment->isPaid(),
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
