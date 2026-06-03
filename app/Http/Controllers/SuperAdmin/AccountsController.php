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
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Superadmin management of customer (admin) accounts across the whole platform.
 */
class AccountsController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(): View
    {
        // Every customer account owner (admins + pending/unpaid signups). Owners
        // point account_id at themselves and are the only users with a subscription,
        // so this also surfaces signups that haven't paid yet (status inactive,
        // no admin role) — previously only visible on the Subscriptions page.
        $accounts = User::whereColumn('account_id', 'id')
            ->whereHas('subscription')
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

    /** Show the form to provision a new customer (admin) account. */
    public function create(): View
    {
        return view('superadmin.accounts.create', [
            'plans' => Plan::orderBy('price_usd')->get(),
        ]);
    }

    /** Provision a new customer account: admin user + active subscription. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255', 'unique:users,phone'],
            'password' => ['required', Password::defaults()],
            'plan' => ['required', 'exists:plans,slug'],
        ]);

        $plan = Plan::where('slug', $validated['plan'])->firstOrFail();

        $account = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        // Admins own their own account — point account_id back at themselves.
        $account->forceFill(['account_id' => $account->id])->save();
        $account->assignRole('admin');

        Subscription::create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addDays($plan->billing_period_days),
        ]);

        return redirect()->route('superadmin.accounts.index')
            ->with('success', __('messages.flash_plan_updated_to', ['plan' => $plan->name]));
    }

    /**
     * Activate a pending/unpaid signup without payment (superadmin override).
     * Mirrors the payment-confirmed path: subscription active + expiry, admin
     * role, account status active.
     */
    public function activate(User $account): RedirectResponse
    {
        if ($sub = $account->subscription) {
            $days = $sub->plan?->billing_period_days ?? 30;
            $sub->update([
                'status' => 'active',
                'started_at' => $sub->started_at ?? now(),
                'expires_at' => now()->addDays($days),
            ]);
        }

        if (! $account->hasRole('admin')) {
            $account->assignRole('admin');
        }
        if ($account->status !== 'active') {
            $account->forceFill(['status' => 'active'])->save();
        }

        return back()->with('success', __('messages.flash_account_reactivated'));
    }

    /** Suspend or reactivate an account. */
    public function toggleSuspend(User $account): RedirectResponse
    {
        $suspending = $account->status !== 'suspended';
        $account->update(['status' => $suspending ? 'suspended' : 'active']);

        if ($sub = $account->subscription) {
            $sub->update(['status' => $suspending ? 'cancelled' : 'active']);
        }

        return back()->with('success', $suspending ? __('messages.flash_account_suspended') : __('messages.flash_account_reactivated'));
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

        return back()->with('success', __('messages.flash_plan_updated_to', ['plan' => $plan->name]));
    }
}
