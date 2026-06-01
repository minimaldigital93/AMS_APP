<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\KhqrPayment;
use App\Models\Plan;
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
        $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();

        // ── Headline counts ──────────────────────────────────────────────
        $activeSubscriptions = Subscription::query()->where('status', 'active')->count();
        $pendingSubscriptions = Subscription::query()->where('status', 'pending')->count();
        $expiredSubscriptions = Subscription::query()->whereIn('status', ['expired', 'cancelled'])->count();
        $accountsCount = User::role('admin')->count();

        // Monthly recurring revenue = sum of price of every active subscription's plan.
        $mrr = (float) Subscription::query()
            ->where('status', 'active')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_usd');

        // Annual run rate — the headline SaaS projection.
        $arr = $mrr * 12;

        // All-time collected revenue from confirmed subscription payments.
        $platformRevenue = (float) KhqrPayment::query()
            ->whereNotNull('subscription_id')
            ->where('status', 'paid')
            ->sum('amount');

        // Average revenue per account (active subscriptions only).
        $arpa = $activeSubscriptions > 0 ? $mrr / $activeSubscriptions : 0.0;

        // ── Month-over-month deltas (this month vs last) ─────────────────
        $newAccountsThisMonth = User::role('admin')->where('created_at', '>=', $monthStart)->count();
        $newAccountsLastMonth = User::role('admin')
            ->whereBetween('created_at', [$lastMonthStart, $monthStart])
            ->count();

        $revenueThisMonth = (float) KhqrPayment::query()
            ->whereNotNull('subscription_id')->where('status', 'paid')
            ->where('paid_at', '>=', $monthStart)->sum('amount');
        $revenueLastMonth = (float) KhqrPayment::query()
            ->whereNotNull('subscription_id')->where('status', 'paid')
            ->whereBetween('paid_at', [$lastMonthStart, $monthStart])->sum('amount');

        $newSubsThisMonth = Subscription::query()->where('started_at', '>=', $monthStart)->count();
        $newSubsLastMonth = Subscription::query()
            ->whereBetween('started_at', [$lastMonthStart, $monthStart])->count();

        $delta = fn ($current, $previous) => $previous > 0
            ? round(($current - $previous) / $previous * 100, 1)
            : ($current > 0 ? 100.0 : 0.0);

        $revenueDelta = $delta($revenueThisMonth, $revenueLastMonth);
        $accountsDelta = $delta($newAccountsThisMonth, $newAccountsLastMonth);
        $subsDelta = $delta($newSubsThisMonth, $newSubsLastMonth);

        // ── MRR movement: new vs churned this month ──────────────────────
        $newMrr = (float) Subscription::query()
            ->where('subscriptions.status', 'active')
            ->where('subscriptions.started_at', '>=', $monthStart)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_usd');

        $churnedThisMonth = Subscription::query()
            ->whereIn('status', ['expired', 'cancelled'])
            ->where('updated_at', '>=', $monthStart)
            ->count();
        $churnedMrr = (float) Subscription::query()
            ->whereIn('subscriptions.status', ['expired', 'cancelled'])
            ->where('subscriptions.updated_at', '>=', $monthStart)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_usd');

        $netMrrMovement = $newMrr - $churnedMrr;
        // Churn rate: lost this month relative to the base that was active.
        $churnRate = ($activeSubscriptions + $churnedThisMonth) > 0
            ? round($churnedThisMonth / ($activeSubscriptions + $churnedThisMonth) * 100, 1)
            : 0.0;

        // ── 12-month time series (grouped in PHP for DB portability) ──────
        $windowStart = $now->copy()->subMonths(11)->startOfMonth();

        $months = [];
        $monthLabels = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $windowStart->copy()->addMonths($i);
            $months[$m->format('Y-m')] = $m->format('M Y');
            $monthLabels[] = $m->format('M Y');
        }

        $plans = Plan::query()->orderBy('price_usd')->get();

        // Revenue per month, broken down per plan (for the stacked chart).
        $revenueSeries = array_fill_keys(array_keys($months), 0.0);
        $planMonthly = [];
        foreach ($plans as $plan) {
            $planMonthly[$plan->id] = array_fill_keys(array_keys($months), 0.0);
        }

        KhqrPayment::query()
            ->whereNotNull('khqr_payments.subscription_id')
            ->where('khqr_payments.status', 'paid')
            ->whereNotNull('khqr_payments.paid_at')
            ->where('khqr_payments.paid_at', '>=', $windowStart)
            ->join('subscriptions', 'subscriptions.id', '=', 'khqr_payments.subscription_id')
            ->get(['khqr_payments.amount as amount', 'khqr_payments.paid_at as paid_at', 'subscriptions.plan_id as plan_id'])
            ->each(function ($p) use (&$revenueSeries, &$planMonthly) {
                $key = Carbon::parse($p->paid_at)->format('Y-m');
                if (isset($revenueSeries[$key])) {
                    $revenueSeries[$key] += (float) $p->amount;
                    if (isset($planMonthly[$p->plan_id][$key])) {
                        $planMonthly[$p->plan_id][$key] += (float) $p->amount;
                    }
                }
            });

        $revenueByPlanSeries = $plans->map(fn ($plan) => [
            'name' => $plan->name,
            'data' => array_values($planMonthly[$plan->id]),
        ])->values();

        // New accounts per month.
        $accountsSeries = array_fill_keys(array_keys($months), 0);
        User::role('admin')
            ->where('created_at', '>=', $windowStart)
            ->get(['id', 'created_at'])
            ->each(function ($u) use (&$accountsSeries) {
                $key = $u->created_at->format('Y-m');
                if (isset($accountsSeries[$key])) {
                    $accountsSeries[$key]++;
                }
            });

        // ── Plan distribution (active subscriptions per plan) ────────────
        $planCounts = Subscription::query()
            ->where('status', 'active')
            ->selectRaw('plan_id, COUNT(*) as c')
            ->groupBy('plan_id')
            ->pluck('c', 'plan_id');

        $planDistribution = $plans->map(fn ($plan) => [
            'name' => $plan->name,
            'count' => (int) ($planCounts[$plan->id] ?? 0),
            'price' => (float) $plan->price_usd,
        ])->values();

        // ── Account engagement (login activity) ──────────────────────────
        $activeAccounts = User::role('admin')
            ->where('last_login_at', '>=', $now->copy()->subDays(30))
            ->count();
        $suspendedAccounts = User::role('admin')->where('status', 'suspended')->count();
        $dormantAccounts = max($accountsCount - $activeAccounts, 0);

        // ── Platform capacity (across every account) ─────────────────────
        $totalFloors = Floors::withoutAccountScope()->count();
        $totalApartments = Apartments::withoutAccountScope()->count();
        $occupiedApartments = Apartments::withoutAccountScope()->where('status', 'occupied')->count();
        $occupancyRate = $totalApartments > 0
            ? round($occupiedApartments / $totalApartments * 100, 1)
            : 0.0;

        // ── Top accounts by lifetime revenue ─────────────────────────────
        $topAccounts = KhqrPayment::query()
            ->whereNotNull('khqr_payments.subscription_id')
            ->where('khqr_payments.status', 'paid')
            ->join('subscriptions', 'subscriptions.id', '=', 'khqr_payments.subscription_id')
            ->join('users', 'users.id', '=', 'subscriptions.account_id')
            ->selectRaw('users.name as name, users.phone as phone, SUM(khqr_payments.amount) as total, COUNT(*) as payments')
            ->groupBy('users.id', 'users.name', 'users.phone')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        // ── Actionable lists ─────────────────────────────────────────────
        $expiringSoon = Subscription::query()
            ->with(['plan', 'account'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $now->copy()->addDays(7)])
            ->orderBy('expires_at')
            ->get();

        $recentSubscriptions = Subscription::query()
            ->with(['plan', 'account'])
            ->latest()
            ->take(10)
            ->get();

        return view('superadmin.dashboard', [
            'activeSubscriptions' => $activeSubscriptions,
            'pendingSubscriptions' => $pendingSubscriptions,
            'expiredSubscriptions' => $expiredSubscriptions,
            'mrr' => $mrr,
            'arr' => $arr,
            'arpa' => $arpa,
            'platformRevenue' => $platformRevenue,
            'revenueThisMonth' => $revenueThisMonth,
            'revenueDelta' => $revenueDelta,
            'accountsCount' => $accountsCount,
            'newAccountsThisMonth' => $newAccountsThisMonth,
            'accountsDelta' => $accountsDelta,
            'subsDelta' => $subsDelta,
            'newMrr' => $newMrr,
            'churnedMrr' => $churnedMrr,
            'netMrrMovement' => $netMrrMovement,
            'churnRate' => $churnRate,
            'churnedThisMonth' => $churnedThisMonth,
            'plans' => $plans,
            'planDistribution' => $planDistribution,
            'revenueByPlanSeries' => $revenueByPlanSeries,
            'recentSubscriptions' => $recentSubscriptions,
            'expiringSoon' => $expiringSoon,
            'topAccounts' => $topAccounts,
            'activeAccounts' => $activeAccounts,
            'dormantAccounts' => $dormantAccounts,
            'suspendedAccounts' => $suspendedAccounts,
            'totalFloors' => $totalFloors,
            'totalApartments' => $totalApartments,
            'occupiedApartments' => $occupiedApartments,
            'occupancyRate' => $occupancyRate,
            'monthLabels' => $monthLabels,
            'revenueSeries' => array_values($revenueSeries),
            'accountsSeries' => array_values($accountsSeries),
        ]);
    }
}
