<?php

namespace App\Http\Controllers;

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

        return view('subscribe.register', compact('plans', 'selected'));
    }

    /**
     * Create the pending account + subscription, then either start the plan's
     * free trial (account usable immediately, no payment) or KHQR checkout.
     */
    public function store(Request $request, KhqrPaymentService $khqr, SubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'plan' => ['required', 'exists:plans,slug'],
            'start_trial' => ['nullable', 'boolean'],
        ]);

        $plan = Plan::where('slug', $validated['plan'])->firstOrFail();
        $wantsTrial = $request->boolean('start_trial') && $plan->hasTrial();

        if ($wantsTrial) {
            DB::transaction(function () use ($validated, $plan, $subscriptions) {
                $user = User::create([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'password' => Hash::make($validated['password']),
                    'status' => 'active',
                ]);
                // An account owner points at itself.
                $user->forceFill(['account_id' => $user->id])->save();
                $user->assignRole('admin');

                $subscriptions->startTrial($user->id, $plan);
            });

            return redirect()->route('login')
                ->with('status', __('messages.flash_trial_started', ['days' => $plan->trial_days]));
        }

        $row = DB::transaction(function () use ($validated, $plan, $khqr) {
            $user = User::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'status' => 'inactive',
            ]);
            // An account owner points at itself.
            $user->forceFill(['account_id' => $user->id])->save();

            $subscription = Subscription::create([
                'account_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
            ]);

            return $khqr->createSubscriptionQr($subscription, (float) $plan->price_usd);
        });

        return redirect()->route('subscribe.checkout', $row->transaction_id);
    }

    /** Scan/poll page for the subscription QR. */
    public function checkout(string $transaction): View|RedirectResponse
    {
        $payment = KhqrPayment::where('transaction_id', $transaction)
            ->whereNotNull('subscription_id')
            ->firstOrFail();

        if ($payment->isPaid()) {
            return redirect()->route('login')->with('status', __('messages.flash_subscription_activated_signin'));
        }

        $payment->load('subscription.plan');

        return view('subscribe.checkout', compact('payment'));
    }

    /** Polled by the checkout page; verifies + activates on confirmation. */
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
            'redirect' => $payment->isPaid() ? route('login') : null,
        ]);
    }
}
