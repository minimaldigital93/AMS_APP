<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * Platform (SaaS) overview for the superadmin — reads ACROSS every account, so
 * all queries here deliberately bypass the per-account global scope.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $activeSubscriptions = Subscription::query()->where('status', 'active')->count();
        $pendingSubscriptions = Subscription::query()->where('status', 'pending')->count();

        // Monthly recurring revenue = sum of price of every active subscription's plan.
        $mrr = Subscription::query()
            ->where('status', 'active')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_usd');

        $platformRevenue = KhqrPayment::query()
            ->whereNotNull('subscription_id')
            ->where('status', 'paid')
            ->sum('amount');

        $accountsCount = User::role('admin')->count();

        $recentSubscriptions = Subscription::query()
            ->with(['plan', 'account'])
            ->latest()
            ->take(10)
            ->get();

        return view('superadmin.dashboard', [
            'activeSubscriptions' => $activeSubscriptions,
            'pendingSubscriptions' => $pendingSubscriptions,
            'mrr' => $mrr,
            'platformRevenue' => $platformRevenue,
            'accountsCount' => $accountsCount,
            'plans' => Plan::query()->orderBy('price_usd')->get(),
            'recentSubscriptions' => $recentSubscriptions,
        ]);
    }
}
