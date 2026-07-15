<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Apartments;
use App\Models\Attachment;
use App\Models\Floors;
use App\Models\MerchantPaymentSetting;
use App\Models\Plan;
use App\Models\Settings;
use App\Models\Subscription;
use App\Models\Tenants;
use App\Models\User;
use App\Services\Platform\AccountPurgeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        // Bytes of uploaded files each account owns (tenant photos/documents,
        // expense attachments, company logo, KHQR images). Formatted for
        // display with format_bytes() in the view.
        $diskUsage = $this->diskUsageBytes($accountIds);

        $usage = [];
        foreach ($accountIds as $id) {
            $usage[$id] = [
                'floors' => (int) ($floorCounts[$id] ?? 0),
                'apartments' => (int) ($apartmentCounts[$id] ?? 0),
                'disk_bytes' => (int) ($diskUsage[$id] ?? 0),
            ];
        }

        return view('superadmin.accounts.index', [
            'accounts' => $accounts,
            'usage' => $usage,
            'plans' => Plan::orderBy('price_usd')->get(),
        ]);
    }

    /**
     * Total on-disk size (bytes) of every uploaded file each account owns,
     * keyed by account id. Every upload in the app lands on the `public` disk.
     *
     * The high-volume sources — tenant photos (TracksFileSizes-tracked scalar
     * column) and the polymorphic `attachments` table (tenant ID documents +
     * business-expense receipts, size captured once at upload time) — total in
     * one grouped SUM() query each. That keeps this page's cost proportional to
     * the number of accounts shown rather than the number of files they hold.
     *
     * The company logo and merchant KHQR image are at most one file per account
     * and never scale with usage, so they are simply stat-ed directly (≤2 files
     * per account). Missing files count as zero. All queries drop the account
     * scope since this runs in the superadmin panel.
     *
     * @param  array<int>  $accountIds
     * @return array<int,int>
     */
    private function diskUsageBytes(array $accountIds): array
    {
        if (empty($accountIds)) {
            return [];
        }

        $usage = array_fill_keys($accountIds, 0);

        // Tenant photos. withTrashed: soft-deleted tenants still leave their
        // uploaded files on disk, so they count toward usage.
        $tenantBytes = Tenants::withoutAccountScope()->withTrashed()
            ->whereIn('account_id', $accountIds)
            ->selectRaw('account_id, COALESCE(SUM(photo_size), 0) as bytes')
            ->groupBy('account_id')
            ->pluck('bytes', 'account_id');

        // Multi-file attachments: tenant ID documents + business-expense
        // receipts live in the same table, so one query covers both kinds.
        $attachmentBytes = Attachment::withoutAccountScope()
            ->whereIn('account_id', $accountIds)
            ->selectRaw('account_id, COALESCE(SUM(size), 0) as bytes')
            ->groupBy('account_id')
            ->pluck('bytes', 'account_id');

        foreach ($accountIds as $id) {
            $usage[$id] += (int) ($tenantBytes[$id] ?? 0) + (int) ($attachmentBytes[$id] ?? 0);
        }

        // At-most-one-per-account files stat-ed directly (they never scale with
        // file count): the company logo (a Settings key/value path) and the
        // merchant KHQR image.
        $disk = Storage::disk('public');
        $sizeOf = function (?string $path) use ($disk): int {
            if (! filled($path)) {
                return 0;
            }

            $full = $disk->path($path);

            return is_file($full) ? (int) filesize($full) : 0;
        };

        Settings::withoutAccountScope()
            ->whereIn('account_id', $accountIds)
            ->where('key', 'company_logo')
            ->get(['account_id', 'value'])
            ->each(function ($s) use (&$usage, $sizeOf) {
                if (array_key_exists($s->account_id, $usage)) {
                    $usage[$s->account_id] += $sizeOf($s->value);
                }
            });

        MerchantPaymentSetting::withoutAccountScope()
            ->whereIn('account_id', $accountIds)
            ->whereNotNull('khqr_image_path')
            ->get(['account_id', 'khqr_image_path'])
            ->each(function ($m) use (&$usage, $sizeOf) {
                if (array_key_exists($m->account_id, $usage)) {
                    $usage[$m->account_id] += $sizeOf($m->khqr_image_path);
                }
            });

        return $usage;
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

        $account = User::forceCreate([
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
            $days = $sub->billing_cycle === 'yearly' ? 365 : ($sub->plan?->billing_period_days ?? 30);
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
        $account->forceFill(['status' => $suspending ? 'suspended' : 'active'])->save();

        if ($sub = $account->subscription) {
            $sub->update(['status' => $suspending ? 'cancelled' : 'active']);
        }

        return back()->with('success', $suspending ? __('messages.flash_account_suspended') : __('messages.flash_account_reactivated'));
    }

    /**
     * Permanently delete a customer account and everything it owns — rows and
     * uploaded files. The heavy lifting lives in AccountPurgeService, which
     * deletes explicitly (children first) because the soft-delete models never
     * trigger DB cascades and the financial-history FKs are RESTRICT.
     */
    public function destroy(User $account, AccountPurgeService $purge): RedirectResponse
    {
        // Only customer account owners are deletable here. Never let a superadmin
        // delete themselves or another platform admin through this screen.
        if ($account->id === auth()->id() || $account->hasRole('superadmin')) {
            return back()->with('error', __('messages.flash_account_delete_forbidden'));
        }

        $purge->purge($account);

        return redirect()->route('superadmin.accounts.index')
            ->with('success', __('messages.flash_account_deleted'));
    }

    /**
     * Customer account detail screen: read-only profile + admin actions.
     * Cross-account read (superadmin panel), so owned models are counted with
     * withoutAccountScope().
     */
    public function show(User $account): View
    {
        // Only real customer account owners are viewable here — never a member
        // user (supervisor/tenant login) or another platform superadmin.
        abort_if($account->account_id !== $account->id || $account->hasRole('superadmin'), 404);

        $account->load(['subscription.plan']);
        $id = $account->id;

        $stats = [
            'floors' => Floors::withoutAccountScope()->where('account_id', $id)->count(),
            'apartments' => Apartments::withoutAccountScope()->where('account_id', $id)->count(),
            'tenants' => Tenants::withoutAccountScope()->where('account_id', $id)->count(),
            'members' => User::where('account_id', $id)->where('id', '!=', $id)->count(),
        ];

        return view('superadmin.accounts.show', [
            'account' => $account,
            'stats' => $stats,
        ]);
    }

    /**
     * Reset a customer account's login password to a fixed default. The phone
     * number — their login identifier — is left untouched on purpose.
     */
    public function resetPassword(User $account): RedirectResponse
    {
        abort_if($account->hasRole('superadmin'), 404);

        // Random per reset — shown once in the flash so the operator can hand
        // it to the customer. A fixed default was guessable platform-wide.
        $password = Str::random(10);
        $account->forceFill(['password' => Hash::make($password)])->save();

        // Sticky: the message contains the new password — it must stay on
        // screen until it has been copied (plain 'success' auto-dismisses).
        return back()->with('success_sticky', __('messages.flash_account_password_reset', [
            'name' => $account->name,
            'password' => $password,
        ]));
    }

    /** Change the account's plan (no payment — superadmin override). */
    public function changePlan(Request $request, User $account): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
            'billing_cycle' => ['nullable', 'in:monthly,yearly'],
        ]);
        $plan = Plan::where('slug', $validated['plan'])->firstOrFail();
        $cycle = ($validated['billing_cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';

        Subscription::updateOrCreate(
            ['account_id' => $account->id],
            [
                'plan_id' => $plan->id,
                'billing_cycle' => $cycle,
                'status' => 'active',
                'started_at' => now(),
                'expires_at' => now()->addDays($cycle === 'yearly' ? 365 : $plan->billing_period_days),
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
