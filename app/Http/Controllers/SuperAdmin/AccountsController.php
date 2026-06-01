<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Superadmin management of customer (admin) accounts across the whole platform.
 */
class AccountsController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(): View
    {
        $accounts = User::role('admin')
            ->with(['subscription.plan'])
            ->orderBy('name')
            ->paginate(20);

        // Per-account usage snapshot for the table.
        $usage = [];
        foreach ($accounts as $account) {
            $usage[$account->id] = [
                'floors' => $this->subscriptions->floorCount($account->id),
                'apartments' => $this->subscriptions->apartmentCount($account->id),
            ];
        }

        return view('superadmin.accounts.index', [
            'accounts' => $accounts,
            'usage' => $usage,
            'plans' => Plan::orderBy('price_usd')->get(),
        ]);
    }

    /** Suspend or reactivate an account. */
    public function toggleSuspend(User $account): RedirectResponse
    {
        $suspending = $account->status !== 'suspended';
        $account->update(['status' => $suspending ? 'suspended' : 'active']);

        if ($sub = $account->subscription) {
            $sub->update(['status' => $suspending ? 'cancelled' : 'active']);
        }

        return back()->with('success', $suspending ? __('Account suspended.') : __('Account reactivated.'));
    }

    /** Extend the account's subscription by one billing period. */
    public function extend(User $account): RedirectResponse
    {
        $sub = $account->subscription()->with('plan')->first();
        if (! $sub) {
            return back()->with('error', __('Account has no subscription to extend.'));
        }

        $base = $sub->expires_at && $sub->expires_at->isFuture() ? $sub->expires_at : now();
        $sub->update([
            'status' => 'active',
            'started_at' => $sub->started_at ?? now(),
            'expires_at' => $base->copy()->addDays($sub->plan?->billing_period_days ?? 30),
        ]);

        return back()->with('success', __('Subscription extended.'));
    }

    /** Change the account's plan (no payment — superadmin override). */
    public function changePlan(Request $request, User $account): RedirectResponse
    {
        $validated = $request->validate(['plan' => ['required', 'exists:plans,slug']]);
        $plan = Plan::where('slug', $validated['plan'])->firstOrFail();

        Subscription::updateOrCreate(
            ['account_id' => $account->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => now(),
                'expires_at' => now()->addDays($plan->billing_period_days),
            ]
        );

        // Ensure the account is usable: admin role + active status.
        if (! $account->hasRole('admin')) {
            $account->assignRole('admin');
        }
        if ($account->status !== 'active') {
            $account->forceFill(['status' => 'active'])->save();
        }

        return back()->with('success', __('Plan updated to :plan.', ['plan' => $plan->name]));
    }
}
