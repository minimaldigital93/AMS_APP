<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::with('roles', 'permissions');

        // Filter by role
        if ($request->filled('role')) {
            $role = $request->get('role');
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Search - search in name and email
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        // Validate sort fields to prevent SQL injection
        $allowedSortFields = ['id', 'name', 'email', 'status', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }
        
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);
        
        // Append query parameters to pagination links
        $users->appends($request->query());

        // Return view for web requests, JSON for API requests
        if ($request->wantsJson() || $request->is('api/*')) {
            return UserResource::collection($users);
        }

        $roles = Role::all();
        $permissions = Permission::all();
        
        // Pass filter values to view for sticky filters
        $filters = [
            'search' => $request->get('search', ''),
            'role' => $request->get('role', ''),
            'status' => $request->get('status', ''),
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ];
        
        return view('admin.user', compact('users', 'roles', 'permissions', 'filters'));
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'role' => ['required', 'string', Rule::in(['admin', 'supervisor', 'tenant'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'] ?? 'active',
        ]);

        $user->assignRole($validated['role']);

        // Return JSON for API requests, redirect for web requests
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'User created successfully',
                'data' => new UserResource($user),
            ], 201);
        }

        return redirect()->route('admin.users.index')
                        ->with('success', 'User created successfully!');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['supervisedApartments', 'managedTenants']));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'role' => ['nullable', 'string', Rule::in(['admin', 'supervisor', 'tenant'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $role = $validated['role'] ?? null;
        unset($validated['role']);
        
        $permissions = $validated['permissions'] ?? null;
        unset($validated['permissions']);

        $user->update($validated);

        if ($role) {
            $user->syncRoles([$role]);
        }

        if ($permissions) {
            $user->syncPermissions($permissions);
        }

        // Return JSON for API requests, redirect for web requests
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'User updated successfully',
                'data' => new UserResource($user),
            ]);
        }

        return redirect()->route('admin.users.index')
                        ->with('success', 'User updated successfully!');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->id === Auth::id()) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Cannot delete your own account',
                ], 403);
            }
            return redirect()->route('admin.users.index')
                            ->with('error', 'You cannot delete your own account!');
        }

        $user->delete();

        // Return JSON for API requests, redirect for web requests
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'User deleted successfully',
            ]);
        }

        return redirect()->route('admin.users.index')
                        ->with('success', 'User deleted successfully!');
    }

    /**
     * Get users by role.
     */
    public function byRole(string $role): AnonymousResourceCollection
    {
        $users = User::role($role)->paginate(15);
        return UserResource::collection($users);
    }

    /**
     * Get all supervisors.
     */
    public function supervisors(): AnonymousResourceCollection
    {
        $users = User::role('supervisor')->where('status', 'active')->get();
        return UserResource::collection($users);
    }

    /**
     * Assign permissions to a user.
     */
    public function assignPermissions(Request $request, User $user)
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $user->syncPermissions($validated['permissions']);

        // Return JSON for API requests, redirect for web requests
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Permissions updated successfully',
                'data' => new UserResource($user->load('permissions')),
            ]);
        }

        return redirect()->back()
                        ->with('success', 'Permissions updated successfully!');
    }
}
