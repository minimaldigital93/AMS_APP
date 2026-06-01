<?php

namespace App\Http\Middleware;

use App\Services\Subscription\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate admin feature routes behind an active subscription.
 *
 * - Superadmin is exempt (platform operator).
 * - The billing pages are exempt so an expired admin can renew (no redirect loop).
 * - An admin with no active subscription is bounced to the billing page.
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
            return redirect()->route('admin.billing.index')
                ->with('warning', __('Your subscription is inactive. Please renew to continue.'));
        }

        return $next($request);
    }
}
