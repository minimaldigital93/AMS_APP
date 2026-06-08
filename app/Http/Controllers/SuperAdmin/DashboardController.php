<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\KhqrPayment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

/**
 * Platform (SaaS) overview for the superadmin — reads ACROSS every account, so
 * all queries here deliberately bypass the per-account global scope.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();

        // ── Headline numbers shown on the overview cards ─────────────────
        $activeSubscriptions = Subscription::query()->where('status', 'active')->count();
        $accountsCount = User::role('admin')->count();
        $newAccountsThisMonth = User::role('admin')->where('created_at', '>=', $monthStart)->count();

        // Monthly recurring revenue = sum of price of every active subscription's plan.
        $mrr = (float) Subscription::query()
            ->where('status', 'active')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_usd');

        // ── MRR movement this month: new vs churned ──────────────────────
        $newMrr = (float) Subscription::query()
            ->where('subscriptions.status', 'active')
            ->where('subscriptions.started_at', '>=', $monthStart)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_usd');
        $churnedMrr = (float) Subscription::query()
            ->whereIn('subscriptions.status', ['expired', 'cancelled'])
            ->where('subscriptions.updated_at', '>=', $monthStart)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_usd');
        $netMrrMovement = $newMrr - $churnedMrr;

        // Churn rate: subscriptions lost this month relative to the active base.
        $churnedThisMonth = Subscription::query()
            ->whereIn('status', ['expired', 'cancelled'])
            ->where('updated_at', '>=', $monthStart)
            ->count();
        $churnRate = ($activeSubscriptions + $churnedThisMonth) > 0
            ? round($churnedThisMonth / ($activeSubscriptions + $churnedThisMonth) * 100, 1)
            : 0.0;

        // ── 12-month revenue series for the trend chart ──────────────────
        // Bucketed in PHP (keyed by "Y-m") for DB portability.
        $windowStart = $now->copy()->subMonths(11)->startOfMonth();
        $monthLabels = [];
        $revenueSeries = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $windowStart->copy()->addMonths($i);
            $monthLabels[] = $m->format('M Y');
            $revenueSeries[$m->format('Y-m')] = 0.0;
        }

        KhqrPayment::query()
            ->whereNotNull('subscription_id')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $windowStart)
            ->get(['amount', 'paid_at'])
            ->each(function ($p) use (&$revenueSeries) {
                $key = Carbon::parse($p->paid_at)->format('Y-m');
                if (isset($revenueSeries[$key])) {
                    $revenueSeries[$key] += (float) $p->amount;
                }
            });

        return view('superadmin.dashboard', [
            'mrr' => $mrr,
            'activeSubscriptions' => $activeSubscriptions,
            'accountsCount' => $accountsCount,
            'newAccountsThisMonth' => $newAccountsThisMonth,
            'netMrrMovement' => $netMrrMovement,
            'churnRate' => $churnRate,
            'monthLabels' => $monthLabels,
            'revenueSeries' => array_values($revenueSeries),
        ]);
    }
}
