<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Pdf\KhmerPdf;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Roles an account owner may hand out from this page.
     *
     * 'admin' is a CO-ADMIN of the same account, not a second account: the row
     * keeps account_id = the owner's id, so current_account_id() (and with it
     * every BelongsToAccount query, the subscription gate, and the fiscal
     * period / ledger lookups) resolves to the owner's books. The owner row
     * itself is never editable from here — see authorizeTeamMember().
     */
    private const ASSIGNABLE_ROLES = ['admin', 'supervisor', 'tenant'];

    /** Roles that occupy a seat under the plan's staff cap. */
    private const STAFF_ROLES = ['admin', 'supervisor'];

    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        [$users, $suspended] = $this->roster($request);

        $roles = Role::whereIn('name', self::ASSIGNABLE_ROLES)->get();

        // Summary card counts, taken off the already-loaded collections so no
        // extra queries are fired.
        $adminCount = $users->filter(fn (User $u) => $u->hasAnyRole(['admin', 'superadmin']))->count();
        $supervisorCount = $users->filter(fn (User $u) => $u->hasRole('supervisor'))->count();
        $tenantCount = $users->filter(fn (User $u) => $u->hasRole('tenant'))->count();
        $suspendedCount = $suspended->count();

        return view('admin.users.index', compact(
            'users', 'suspended', 'roles',
            'adminCount', 'supervisorCount', 'tenantCount', 'suspendedCount',
        ));
    }

    /**
     * Download the roster as a Khmer PDF under the company letterhead.
     *
     * It is the SAME list the page shows — roster() with the page's `role`
     * dropdown and `search` box, same property scope, same order, suspended
     * rows last — so the download always matches what is on screen. The page
     * keeps the icon's href in step with both filters as they change.
     *
     * Tenant rows carry their tenancy (ID card, address, lease dates); staff
     * rows have no ID/address on record and start on the day they were added.
     * Rendered with mPDF (KhmerPdf), not Dompdf — Dompdf cannot shape Khmer.
     */
    public function rosterPdf(Request $request, KhmerPdf $pdf): Response
    {
        $request->validate([
            'role' => ['nullable', Rule::in(self::ASSIGNABLE_ROLES)],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active'])],
        ]);

        [$users, $suspended] = $this->roster($request, withTenancy: true);
        $activeOnly = $request->get('status') === 'active';

        $rows = ($activeOnly ? $users : $users->concat($suspended))->values()->map(function (User $u) {
            $role = $u->roles->first()?->name;
            $tenant = $role === 'tenant'
                ? ($u->tenants->whereIn('status', ['active', 'pending'])->first() ?? $u->tenants->sortByDesc('move_in_date')->first())
                : null;
            $rental = $tenant?->rentals->sortByDesc('start_date')->first();

            return [
                'name' => $u->name,
                'gender' => $tenant?->gender,
                'suspended' => ($u->status ?? null) === 'suspended',
                'role' => $role,
                'phone' => $u->phone ?: $tenant?->phone,
                'id_card_number' => $tenant?->id_card_number,
                'address' => $tenant?->address,
                'start_date' => $tenant
                    ? ($rental?->start_date ?? $tenant->move_in_date)
                    : $u->created_at,
                'end_date' => $tenant
                    ? ($rental?->end_date
                        ?? $tenant->move_out_date
                        ?? $tenant->deleted_at)
                    : null,
            ];
        });

        $role = $request->get('role');

        $bytes = $pdf->render('pdf.user_list', [
            'rows' => $rows,
            'role' => $role,
            'search' => $request->get('search'),
            'activeOnly' => $activeOnly,
            'company' => [
                'name' => settings('company_name') ?: config('app.name'),
                'address' => settings('company_address'),
                'phone' => settings('company_phone'),
                'email' => settings('company_email'),
            ],
            'generatedAt' => now(),
        ]);

        $file = ($role ? $role.'-list' : 'user-list').'-'.now()->format('Y-m-d').'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$file.'"',
        ]);
    }

    /**
     * The account's roster as the page lists it: [active, suspended], each in
     * userSortKey() order. Shared by index() and rosterPdf() so the screen and
     * the download can never disagree about who is on the list.
     *
     * @return array{0: \Illuminate\Support\Collection<int, User>, 1: \Illuminate\Support\Collection<int, User>}
     */
    private function roster(Request $request, bool $withTenancy = false): array
    {
        // Isolate to the current account (admins only see their own team).
        $query = User::where('account_id', current_account_id())
            ->with(array_merge(
                ['roles', 'permissions', 'tenants.apartment.floor'],
                // A moved-out tenant is archived (soft-deleted) and their login
                // suspended, so the PDF reads archived tenancies too — that is
                // where a suspended tenant's ID, address and end date live.
                $withTenancy ? [
                    'tenants' => fn ($q) => $q->withTrashed(),
                    'tenants.rentals',
                ] : [],
            ));

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

        [$suspended, $active] = $query->get()->partition(fn (User $u) => ($u->status ?? null) === 'suspended');

        return [
            $active->sortBy(fn (User $u) => $this->userSortKey($u), SORT_NATURAL | SORT_FLAG_CASE)->values(),
            $suspended->sortBy(fn (User $u) => $this->userSortKey($u), SORT_NATURAL | SORT_FLAG_CASE)->values(),
        ];
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

        // Staff logins (co-admins and supervisors) count against the plan's
        // staff cap; tenant logins don't.
        if (in_array($validated['role'], self::STAFF_ROLES, true)) {
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

        // Block promoting a tenant login into a staff seat past the staff cap
        // (admin ⇄ supervisor swaps occupy the same seat, so they're free).
        if (in_array($validated['role'], self::STAFF_ROLES, true)
            && ! $user->hasAnyRole(self::STAFF_ROLES)
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

        return back()->with('password_reveal', [
            'name' => $user->name,
            'password' => $password,
        ]);
    }

    /**
     * Switch a team member's role from the roster's inline role picker.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        // Cross-account targets 404 (the friendlier admin-role flash below only
        // applies to this account's own rows).
        abort_unless($user->account_id === current_account_id(), 404);

        // The account owner's own role is fixed (their user id IS the account
        // id every row hangs off), and a superadmin is never demoted from here.
        // Co-admins are ordinary team members and may be switched.
        if ($this->isAccountOwner($user) || $user->hasRole('superadmin')) {
            return back()->with('error', __('messages.flash_cannot_change_admin_role'));
        }

        // No self-demotion — a co-admin would lock themselves out mid-request.
        if ($user->getKey() === Auth::id()) {
            return back()->with('error', __('messages.flash_cannot_change_own_role'));
        }

        $validated = $request->validate([
            'role' => [
                'required',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->whereIn('name', self::ASSIGNABLE_ROLES)),
            ],
        ]);

        $role = Role::findById($validated['role']);

        // Block promoting a tenant login into a staff seat past the staff cap.
        if (in_array($role->name, self::STAFF_ROLES, true)
            && ! $user->hasAnyRole(self::STAFF_ROLES)
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

    /**
     * Is this row the account owner — the user whose id every account_id points
     * at? Their row is the account itself, so it is never managed from here
     * (they edit themselves under Profile).
     */
    private function isAccountOwner(User $user): bool
    {
        return $user->getKey() === current_account_id();
    }

    private function authorizeTeamMember(User $user): void
    {
        abort_unless($user->account_id === current_account_id(), 404);
        abort_if($user->hasRole('superadmin'), 403);
        abort_if($this->isAccountOwner($user), 403);
        // A co-admin editing their own row here could demote or delete
        // themselves; that belongs to Profile, not team management.
        abort_if($user->getKey() === Auth::id(), 403);
    }

    private function authorizePasswordManagement(User $user): void
    {
        // Always allowed to manage your own password.
        if ($user->id === Auth::id()) {
            return;
        }

        abort_unless($user->account_id === current_account_id(), 404);
        abort_if($user->hasRole('superadmin'), 403);
        abort_if($this->isAccountOwner($user), 403);
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

        // The account owner's row is pinned to the top of the roster. It is the
        // locked row (_row/_card's $rowLocked, authorizeTeamMember()'s 403) and
        // the one every other row's account_id points at, so it heads the list
        // whatever the owner happens to be called — sorting it by name alongside
        // the co-admins buried it under any team member earlier in the alphabet.
        return sprintf(
            '%d|%d|%020d|%s|%s',
            $this->isAccountOwner($user) ? 0 : 1,
            $rank,
            $apartment?->floor?->id ?? PHP_INT_MAX,
            $apartment?->apartment_number ?? '~',
            $user->name,
        );
    }
}
