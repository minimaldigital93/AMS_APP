<?php

namespace App\Http\Middleware;

use App\Services\Subscription\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate admin (and supervisor) feature routes behind an active subscription.
 *
 * - Superadmin is exempt (platform operator).
 * - The billing pages are exempt so an expired admin can renew (no redirect loop).
 * - An admin with no active subscription is bounced to the billing page.
 * - A supervisor shares their admin's account, so the SAME subscription gates
 *   them — but they can't renew it, so they're bounced to their own dashboard
 *   with a notice to ask the account owner (mirrors the fiscal-period gate).
 */
class EnsureSubscriptionActive
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Superadmin bypasses subscription gating entirely.
        if ($user && $user->hasRole('superadmin')) {
            return $next($request);
        }

        // Never block the billing pages themselves (avoids a redirect loop).
        if ($request->routeIs('admin.billing.*')) {
            return $next($request);
        }

        if ($user && $this->subscriptions->activeSubscription($user->account_id ?? $user->id) === null) {
            // Only the account owner (admin) can renew — send them to billing.
            // A supervisor can't, so bounce them to their dashboard with a notice
            // to ask the owner (their dashboard is outside the gated group).
            if ($user->hasRole('admin')) {
                return redirect()->route('admin.billing.index')
                    ->with('warning', __('messages.subscription_blocked_banner'));
            }

            return redirect()->route('supervisor.dashboard')
                ->with('warning', __('messages.subscription_blocked_supervisor'));
        }

        return $next($request);
    }
}
