<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;


class UserController extends Controller
{

    private const ASSIGNABLE_ROLES = ['supervisor', 'tenant'];

    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        // Isolate to the current account (admins only see their own team).
        $query = User::where('account_id', current_account_id())
            ->with('roles', 'permissions', 'tenants.apartment.floor');

        $propertyId = current_property_id();
        if ($propertyId !== null) {
            $query->where(function ($q) use ($propertyId) {
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'superadmin']))
                    ->orWhereHas('supervisedProperties', fn ($p) => $p->where('id', $propertyId))
                    ->orWhereHas('tenants.apartment.floor', fn ($f) => $f->where('property_id', $propertyId))
                    ->orWhere(function ($unattached) {
                        $unattached->whereDoesntHave('supervisedProperties')
                            ->whereDoesntHave('tenants.apartment');
                    });
            });
        }

        if ($request->filled('role')) {
            $role = $request->get('role');
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $all = $query->get();

        [$suspended, $active] = $all->partition(fn (User $u) => ($u->status ?? null) === 'suspended');

        $users = $active->sortBy(fn (User $u) => $this->userSortKey($u), SORT_NATURAL | SORT_FLAG_CASE)->values();
        $suspended = $suspended->sortBy(fn (User $u) => $this->userSortKey($u), SORT_NATURAL | SORT_FLAG_CASE)->values();

        $roles = Role::whereIn('name', self::ASSIGNABLE_ROLES)->get();

        // Summary card counts, taken off the already-loaded collections so no
        // extra queries are fired.
        $adminCount = $active->filter(fn (User $u) => $u->hasAnyRole(['admin', 'superadmin']))->count();
        $supervisorCount = $active->filter(fn (User $u) => $u->hasRole('supervisor'))->count();
        $tenantCount = $active->filter(fn (User $u) => $u->hasRole('tenant'))->count();
        $suspendedCount = $suspended->count();

        return view('admin.users.index', compact(
            'users', 'suspended', 'roles',
            'adminCount', 'supervisorCount', 'tenantCount', 'suspendedCount',
        ));
    }

    /**
     * Show the form for adding a team member.
     */
    public function create(): View
    {
        $roles = Role::whereIn('name', self::ASSIGNABLE_ROLES)->get();

        return view('admin.users.create', compact('roles'));
    }

    /**
     * Create a team member, subject to the account's plan staff cap.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255|unique:users',
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
        ]);

        // Only supervisors count against the plan's staff cap; tenant logins don't.
        if ($validated['role'] === 'supervisor') {
            $accountId = current_account_id();
            if (! $this->subscriptions->canAddStaff($accountId)) {
                $plan = $this->subscriptions->activePlan($accountId);

                return back()->withInput()->with('error', __('messages.flash_plan_limit_staff', ['plan' => $plan?->name, 'max' => $plan?->max_staff]));
            }
        }

        $user = User::forceCreate([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            // New team members belong to the creating admin's account.
            'account_id' => current_account_id(),
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')->with('success', __('messages.flash_user_created'));
    }

    /**
     * Show the form for editing a team member.
     */
    public function edit(User $user): View
    {
        $this->authorizeTeamMember($user);

        $roles = Role::whereIn('name', self::ASSIGNABLE_ROLES)->get();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update a team member's details, role and login password.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeTeamMember($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            'status' => 'nullable|in:active,inactive,suspended',
            // Optional: set a new login password. Left blank keeps the current one.
            'password' => ['nullable', Password::defaults()],
        ]);

        // Block promoting a non-supervisor into a supervisor past the staff cap.
        if ($validated['role'] === 'supervisor' && ! $user->hasRole('supervisor')
            && ! $this->subscriptions->canAddStaff(current_account_id())) {
            $plan = $this->subscriptions->activePlan(current_account_id());

            return back()->withInput()->with('error', __('messages.flash_plan_limit_staff', ['plan' => $plan?->name, 'max' => $plan?->max_staff]));
        }

        $updateData = [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ];

        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->forceFill($updateData)->save();

        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users.index')->with('success', __('messages.flash_user_updated'));
    }

    /**
     * Remove a team member.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeTeamMember($user);

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', __('messages.flash_user_deleted'));
    }


    public function resetPassword(User $user): RedirectResponse
    {
        $this->authorizePasswordManagement($user);

        $password = Str::random(10);
        $user->forceFill(['password' => Hash::make($password)])->save();
        return back()->with('success_sticky', __('messages.flash_account_password_reset', [
            'name' => $user->name,
            'password' => $password,
        ]));
    }

    /**
     * Switch a team member's role from the roster's inline role picker.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        // Cross-account targets 404 (the friendlier admin-role flash below only
        // applies to this account's own rows).
        abort_unless($user->account_id === current_account_id(), 404);

        // Never allow switching/demoting an admin or superadmin via the role picker.
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return back()->with('error', __('messages.flash_cannot_change_admin_role'));
        }

        $validated = $request->validate([
            'role' => [
                'required',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->whereIn('name', self::ASSIGNABLE_ROLES)),
            ],
        ]);

        $role = Role::findById($validated['role']);

        // Block promoting a non-supervisor into a supervisor past the staff cap.
        if ($role->name === 'supervisor' && ! $user->hasRole('supervisor')
            && ! $this->subscriptions->canAddStaff(current_account_id())) {
            $plan = $this->subscriptions->activePlan(current_account_id());

            return back()->with('error', __('messages.flash_plan_limit_staff', ['plan' => $plan?->name, 'max' => $plan?->max_staff]));
        }

        $user->syncRoles([$role]);

        return redirect()->route('admin.users.index')->with('success', __('messages.flash_user_role_updated'));
    }

    /**
     * Replace a team member's direct permissions (on top of their role).
     */
    public function assignPermissions(Request $request, User $user): RedirectResponse
    {
        $this->authorizeTeamMember($user);

        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $user->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('admin.users.index')->with('success', __('messages.flash_permissions_updated'));
    }

    private function authorizeTeamMember(User $user): void
    {
        abort_unless($user->account_id === current_account_id(), 404);
        abort_if($user->hasAnyRole(['admin', 'superadmin']), 403);
    }

    private function authorizePasswordManagement(User $user): void
    {
        // Always allowed to manage your own password.
        if ($user->id === Auth::id()) {
            return;
        }

        abort_unless($user->account_id === current_account_id(), 404);
        abort_if($user->hasAnyRole(['admin', 'superadmin']), 403);
    }

    private function userSortKey(User $user): string
    {
        $role = $user->roles->first()?->name;

        $rank = match ($role) {
            'superadmin', 'admin' => 0,
            'supervisor' => 1,
            'tenant' => 2,
            default => 3,
        };

        $apartment = $role === 'tenant'
            ? $user->tenants->whereIn('status', ['active', 'pending'])->first()?->apartment
            : null;

        return sprintf(
            '%d|%020d|%s|%s',
            $rank,
            $apartment?->floor?->id ?? PHP_INT_MAX,
            $apartment?->apartment_number ?? '~',
            $user->name,
        );
    }
}
