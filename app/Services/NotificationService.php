<?php

namespace App\Services;

use App\Models\BusinessExpense;
use App\Models\Payments;
use App\Models\TenantLeave;
use App\Models\Tenants;
use App\Models\User;
use App\Models\Utilities;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NotificationService
{
    const WINDOW_DAYS = 7;
    const MAX_ITEMS = 12;

    public function for(?User $user): Collection
    {
        if (!$user) {
            return collect();
        }

        if ($user->hasRole('admin')) {
            return $this->forAdmin();
        }

        if ($user->hasRole('supervisor')) {
            return $this->forSupervisor($user);
        }

        if ($user->hasRole('tenant')) {
            return $this->forTenant($user);
        }

        return collect();
    }

    protected function forAdmin(): Collection
    {
        return $this->forStaff('admin');
    }

    protected function forSupervisor(/** @phpstan-ignore-line */ User $user): Collection
    {
        unset($user);
        return $this->forStaff('supervisor');
    }

    /**
     * Admin and supervisor share the same data scope (see CLAUDE.md).
     * Only the link targets differ.
     */
    protected function forStaff(string $role): Collection
    {
        $items = collect();
        $now = Carbon::now();
        $since = $now->copy()->subDays(self::WINDOW_DAYS);

        $tenantUrl = fn($id) => $role === 'admin'
            ? route('admin.tenants.show', $id)
            : route('supervisor.tenants.show', $id);

        $expenseUrl = $role === 'admin'
            ? route('admin.revenue_expense.index')
            : route('supervisor.revenue_expense.index');

        // Rent: upcoming due
        $upcoming = Payments::with('rental.tenant')
            ->whereIn('payment_status', ['pending', 'unpaid', 'partial'])
            ->whereBetween('due_date', [$now->copy()->startOfDay(), $now->copy()->addDays(self::WINDOW_DAYS)->endOfDay()])
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        foreach ($upcoming as $p) {
            $tenant = optional($p->rental)->tenant;
            $items->push([
                'type' => 'due_soon',
                'icon' => 'schedule',
                'color' => 'amber',
                'title' => __('messages.notif_upcoming_due'),
                'message' => __('messages.notif_upcoming_due_msg', [
                    'name' => $tenant->name ?? __('messages.tenant'),
                    'date' => $p->due_date?->format('M d'),
                    'amount' => number_format((float) $p->amount, 2),
                ]),
                'time' => $p->due_date,
                'url' => $tenant ? $tenantUrl($tenant->id) : null,
            ]);
        }

        // Rent: overdue
        $overdue = Payments::with('rental.tenant')
            ->whereIn('payment_status', ['pending', 'unpaid', 'partial', 'overdue'])
            ->whereDate('due_date', '<', $now->copy()->startOfDay())
            ->orderBy('due_date', 'desc')
            ->limit(5)
            ->get();

        foreach ($overdue as $p) {
            $tenant = optional($p->rental)->tenant;
            $items->push([
                'type' => 'overdue',
                'icon' => 'error_outline',
                'color' => 'red',
                'title' => __('messages.notif_overdue'),
                'message' => __('messages.notif_overdue_msg', [
                    'name' => $tenant->name ?? __('messages.tenant'),
                    'date' => $p->due_date?->format('M d'),
                    'amount' => number_format((float) $p->amount, 2),
                ]),
                'time' => $p->due_date,
                'url' => $tenant ? $tenantUrl($tenant->id) : null,
            ]);
        }

        // Rent: recently paid
        $recentPaid = Payments::with('rental.tenant')
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $since)
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentPaid as $p) {
            $tenant = optional($p->rental)->tenant;
            $items->push([
                'type' => 'paid',
                'icon' => 'check_circle',
                'color' => 'emerald',
                'title' => __('messages.notif_recent_paid'),
                'message' => __('messages.notif_recent_paid_msg', [
                    'name' => $tenant->name ?? __('messages.tenant'),
                    'amount' => number_format((float) $p->amount, 2),
                ]),
                'time' => $p->paid_at,
                'url' => $tenant ? $tenantUrl($tenant->id) : null,
            ]);
        }

        // Cash-out: all recent business expenses (admin or supervisor logged)
        $expenses = BusinessExpense::with('user')
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($expenses as $e) {
            $loggedBy = optional($e->user)->name;
            $items->push([
                'type' => 'expense',
                'icon' => 'request_quote',
                'color' => 'indigo',
                'title' => __('messages.notif_expense'),
                'message' => $loggedBy
                    ? __('messages.notif_sup_expense_msg', [
                        'name' => $loggedBy,
                        'expense' => $e->expense_name,
                        'amount' => number_format((float) $e->amount, 2),
                    ])
                    : __('messages.notif_expense_msg', [
                        'expense' => $e->expense_name,
                        'amount' => number_format((float) $e->amount, 2),
                    ]),
                'time' => $e->created_at,
                'url' => $expenseUrl,
            ]);
        }

        // Tenant moves in: new tenants
        $newTenants = Tenants::where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($newTenants as $t) {
            $items->push([
                'type' => 'new_tenant',
                'icon' => 'person_add',
                'color' => 'blue',
                'title' => __('messages.notif_new_tenant'),
                'message' => __('messages.notif_new_tenant_msg', ['name' => $t->name]),
                'time' => $t->created_at,
                'url' => $tenantUrl($t->id),
            ]);
        }

        // Tenant moves out: recent TenantLeave records
        $leaves = TenantLeave::with('tenant')
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($leaves as $l) {
            $tenant = $l->tenant;
            $balance = (float) $l->balance_due;
            $refund = (float) $l->refund_amount;
            $date = $l->leave_date?->format('M d');
            $name = $tenant->name ?? __('messages.tenant');

            if ($refund > 0.001) {
                $msg = __('messages.notif_tenant_moved_out_refund_msg', [
                    'name' => $name, 'date' => $date, 'amount' => number_format($refund, 2),
                ]);
                $color = 'emerald';
            } elseif ($balance > 0.001) {
                $msg = __('messages.notif_tenant_moved_out_due_msg', [
                    'name' => $name, 'date' => $date, 'amount' => number_format($balance, 2),
                ]);
                $color = 'red';
            } else {
                $msg = __('messages.notif_tenant_moved_out_clear_msg', [
                    'name' => $name, 'date' => $date,
                ]);
                $color = 'slate';
            }

            $items->push([
                'type' => 'tenant_moved_out',
                'icon' => 'logout',
                'color' => $color,
                'title' => __('messages.notif_tenant_moved_out'),
                'message' => $msg,
                'time' => $l->created_at,
                'url' => $tenant ? $tenantUrl($tenant->id) : null,
            ]);
        }

        // Cash flow: utility charges and payments
        $utilities = Utilities::with('tenant')
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($utilities as $u) {
            $name = optional($u->tenant)->name ?? __('messages.tenant');
            $items->push([
                'type' => 'utility_charge',
                'icon' => 'bolt',
                'color' => 'amber',
                'title' => __('messages.notif_utility_charge'),
                'message' => __('messages.notif_utility_charge_msg', [
                    'name' => $name,
                    'utility' => $u->utility_type,
                    'amount' => number_format((float) $u->charge_amount, 2),
                ]),
                'time' => $u->created_at,
                'url' => $u->tenant ? $tenantUrl($u->tenant->id) : null,
            ]);
        }

        $utilitiesPaid = Utilities::with('tenant')
            ->where('paid_status', true)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $since)
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($utilitiesPaid as $u) {
            $name = optional($u->tenant)->name ?? __('messages.tenant');
            $items->push([
                'type' => 'utility_paid',
                'icon' => 'check_circle',
                'color' => 'emerald',
                'title' => __('messages.notif_utility_paid'),
                'message' => __('messages.notif_utility_paid_msg', [
                    'name' => $name,
                    'utility' => $u->utility_type,
                    'amount' => number_format((float) $u->charge_amount, 2),
                ]),
                'time' => $u->paid_at,
                'url' => $u->tenant ? $tenantUrl($u->tenant->id) : null,
            ]);
        }

        return $this->sortAndLimit($items);
    }

    protected function forTenant(User $user): Collection
    {
        $items = collect();
        $now = Carbon::now();
        $since = $now->copy()->subDays(self::WINDOW_DAYS);

        $tenant = Tenants::where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->first();

        if (!$tenant) {
            return $items;
        }

        $rentalIds = $tenant->rentals()->pluck('id');
        if ($rentalIds->isEmpty()) {
            return $items;
        }

        $upcoming = Payments::whereIn('rental_id', $rentalIds)
            ->whereIn('payment_status', ['pending', 'unpaid', 'partial'])
            ->whereBetween('due_date', [$now->copy()->startOfDay(), $now->copy()->addDays(self::WINDOW_DAYS)->endOfDay()])
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        foreach ($upcoming as $p) {
            $items->push([
                'type' => 'due_soon',
                'icon' => 'schedule',
                'color' => 'amber',
                'title' => __('messages.notif_your_due_soon'),
                'message' => __('messages.notif_your_due_soon_msg', [
                    'date' => $p->due_date?->format('M d'),
                    'amount' => number_format((float) $p->amount, 2),
                ]),
                'time' => $p->due_date,
                'url' => route('tenant.dashboard'),
            ]);
        }

        $overdue = Payments::whereIn('rental_id', $rentalIds)
            ->whereIn('payment_status', ['pending', 'unpaid', 'partial', 'overdue'])
            ->whereDate('due_date', '<', $now->copy()->startOfDay())
            ->orderBy('due_date', 'desc')
            ->limit(5)
            ->get();

        foreach ($overdue as $p) {
            $items->push([
                'type' => 'overdue',
                'icon' => 'error_outline',
                'color' => 'red',
                'title' => __('messages.notif_your_overdue'),
                'message' => __('messages.notif_your_overdue_msg', [
                    'date' => $p->due_date?->format('M d'),
                    'amount' => number_format((float) $p->amount, 2),
                ]),
                'time' => $p->due_date,
                'url' => route('tenant.dashboard'),
            ]);
        }

        $recentPaid = Payments::whereIn('rental_id', $rentalIds)
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $since)
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentPaid as $p) {
            $items->push([
                'type' => 'paid',
                'icon' => 'check_circle',
                'color' => 'emerald',
                'title' => __('messages.notif_your_recent_paid'),
                'message' => __('messages.notif_your_recent_paid_msg', [
                    'amount' => number_format((float) $p->amount, 2),
                    'date' => $p->paid_at?->format('M d'),
                ]),
                'time' => $p->paid_at,
                'url' => route('tenant.dashboard'),
            ]);
        }

        // Tenant's own utility charges
        $utilities = Utilities::whereIn('rental_id', $rentalIds)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($utilities as $u) {
            $items->push([
                'type' => 'utility_charge',
                'icon' => 'bolt',
                'color' => 'amber',
                'title' => __('messages.notif_your_utility_charge'),
                'message' => __('messages.notif_your_utility_charge_msg', [
                    'utility' => $u->utility_type,
                    'amount' => number_format((float) $u->charge_amount, 2),
                ]),
                'time' => $u->created_at,
                'url' => route('tenant.dashboard'),
            ]);
        }

        // Tenant's own move-out settlement
        $leaves = TenantLeave::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($leaves as $l) {
            $balance = (float) $l->balance_due;
            $refund = (float) $l->refund_amount;
            $date = $l->leave_date?->format('M d');

            if ($refund > 0.001) {
                $msg = __('messages.notif_your_moved_out_refund_msg', [
                    'date' => $date, 'amount' => number_format($refund, 2),
                ]);
                $color = 'emerald';
            } elseif ($balance > 0.001) {
                $msg = __('messages.notif_your_moved_out_due_msg', [
                    'date' => $date, 'amount' => number_format($balance, 2),
                ]);
                $color = 'red';
            } else {
                continue;
            }

            $items->push([
                'type' => 'tenant_moved_out',
                'icon' => 'logout',
                'color' => $color,
                'title' => __('messages.notif_your_moved_out'),
                'message' => $msg,
                'time' => $l->created_at,
                'url' => route('tenant.dashboard'),
            ]);
        }

        return $this->sortAndLimit($items);
    }

    protected function sortAndLimit(Collection $items): Collection
    {
        return $items
            ->sortByDesc(fn($i) => $i['time'] ? Carbon::parse($i['time'])->timestamp : 0)
            ->values()
            ->take(self::MAX_ITEMS);
    }
}
