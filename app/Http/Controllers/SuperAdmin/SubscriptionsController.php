<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Superadmin view + manual override of every subscription (ops actions).
 */
class SubscriptionsController extends Controller
{
    public function index(Request $request): View
    {
        $query = Subscription::with(['account', 'plan'])->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return view('superadmin.subscriptions.index', [
            'subscriptions' => $query->paginate(25)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function activate(Subscription $subscription): RedirectResponse
    {
        $days = $subscription->plan?->billing_period_days ?? 30;
        $subscription->update([
            'status' => 'active',
            'started_at' => $subscription->started_at ?? now(),
            'expires_at' => now()->addDays($days),
        ]);

        // Make sure the account can log in as admin.
        if ($owner = $subscription->account) {
            if (! $owner->hasRole('admin')) {
                $owner->assignRole('admin');
            }
            if ($owner->status !== 'active') {
                $owner->forceFill(['status' => 'active'])->save();
            }
        }

        return back()->with('success', __('messages.flash_subscription_activated'));
    }

    public function cancel(Subscription $subscription): RedirectResponse
    {
        $subscription->update(['status' => 'cancelled']);

        return back()->with('success', __('messages.flash_subscription_cancelled'));
    }
}
