<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
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
    public function index(): View
    {
        // Customer account owners that have actually completed signup. Owners point
        // account_id at themselves and are the only users with a subscription. A
        // signup that registered but never successfully paid stays status 'inactive'
        // (it only flips off 'inactive' once payment is finalized / a trial starts —
        // see SubscriptionController + KhqrPaymentService::finalizeSubscription), so
        // excluding 'inactive' hides never-paid signups while keeping active and
        // suspended (paid) accounts visible.
        $accounts = User::whereColumn('account_id', 'id')
            ->where('status', '!=', 'inactive')
            ->whereHas('subscription')
            ->with(['subscription.plan'])
            ->orderBy('name')
            ->paginate(20);

        // Per-account usage snapshot for the table. Counts are fetched in two
        // grouped queries (across the whole platform) rather than per row, so the
        // page stays at a fixed query count regardless of how many accounts show.
        $accountIds = $accounts->getCollection()->modelKeys();

        $floorCounts = Floors::withoutAccountScope()
            ->whereIn('account_id', $accountIds)
            ->selectRaw('account_id, COUNT(*) as aggregate')
            ->groupBy('account_id')
            ->pluck('aggregate', 'account_id');

        $apartmentCounts = Apartments::withoutAccountScope()
            ->whereIn('account_id', $accountIds)
            ->selectRaw('account_id, COUNT(*) as aggregate')
            ->groupBy('account_id')
            ->pluck('aggregate', 'account_id');

        $usage = [];
        foreach ($accountIds as $id) {
            $usage[$id] = [
                'floors' => (int) ($floorCounts[$id] ?? 0),
                'apartments' => (int) ($apartmentCounts[$id] ?? 0),
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
