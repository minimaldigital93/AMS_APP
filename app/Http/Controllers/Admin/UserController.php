<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /**
     * Roles an admin is allowed to assign when creating/editing team members.
     * Admins cannot create superadmins or other admins.
     */
    private const ASSIGNABLE_ROLES = ['supervisor', 'tenant'];

    /** Default password applied when an admin resets a login. */
    private const RESET_PASSWORD = '12345678';

    public function index(Request $request): View
    {
        // Isolate to the current account (admins only see their own team).
        $query = User::where('account_id', current_account_id())
            ->with('roles', 'permissions', 'tenants.apartment.floor');

        // Filter by role
        if ($request->filled('role')) {
            $role = $request->get('role');
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // Search - search in name and phone
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Admins/superadmins first, then supervisors, then tenants (users without a role last).
        $query->orderByRaw(
            "COALESCE((select min(case roles.name
                    when 'superadmin' then 0
                    when 'admin' then 0
                    when 'supervisor' then 1
                    else 2 end)
                from model_has_roles
                join roles on roles.id = model_has_roles.role_id
                where model_has_roles.model_id = users.id
                  and model_has_roles.model_type = ?), 3) asc",
            [User::class]
        )->orderBy('name');

        $users = $query->paginate(15);
        $roles = Role::whereIn('name', self::ASSIGNABLE_ROLES)->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function create(): View
    {
        $roles = Role::whereIn('name', self::ASSIGNABLE_ROLES)->get();

        return view('admin.users.create', compact('roles'));
    }

    public function edit(User $user): View
    {
        $roles = Role::whereIn('name', self::ASSIGNABLE_ROLES)->get();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255|unique:users',
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
        ]);

        // Enforce the account's subscription plan staff (supervisor) cap.
        if ($validated['role'] === 'supervisor') {
            $accountId = current_account_id();
            if (! $this->subscriptions->canAddStaff($accountId)) {
                $plan = $this->subscriptions->activePlan($accountId);

                return back()->withInput()->with('error', __('messages.flash_plan_limit_staff', ['plan' => $plan?->name, 'max' => $plan?->max_staff]));
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            // New team members belong to the creating admin's account.
            'account_id' => current_account_id(),
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')->with('success', __('messages.flash_user_created'));
    }

    public function update(Request $request, User $user)
    {
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

        $user->update($updateData);

        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users.index')->with('success', __('messages.flash_user_updated'));
    }

    /**
     * Reset a team member's (or the admin's own) login password to the fixed
     * default. The phone number — their login identifier — is left untouched.
     */
    public function resetPassword(User $user)
    {
        $this->authorizePasswordManagement($user);

        $user->forceFill(['password' => Hash::make(self::RESET_PASSWORD)])->save();

        return back()->with('success', __('messages.flash_account_password_reset', [
            'name' => $user->name,
            'password' => self::RESET_PASSWORD,
        ]));
    }

    /**
     * Admins may manage passwords for their own account and for the
     * supervisors/tenants on their team — never another admin or superadmin.
     */
    private function authorizePasswordManagement(User $user): void
    {
        // Always allowed to manage your own password.
        if ($user->id === auth()->id()) {
            return;
        }

        // Otherwise the target must be a supervisor/tenant on the admin's team —
        // never another admin or a superadmin.
        abort_unless($user->account_id === current_account_id(), 404);
        abort_if($user->hasAnyRole(['admin', 'superadmin']), 403);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', __('messages.flash_user_deleted'));
    }

    public function updateRole(Request $request, User $user)
    {
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

    public function assignPermissions(Request $request, User $user)
    {
        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $user->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('admin.users.index')->with('success', __('messages.flash_permissions_updated'));
    }
}
