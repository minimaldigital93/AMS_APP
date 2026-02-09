<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    /**
     * Display a listing of tenants.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Tenants::query();

        // Filter by apartment
        if ($request->has('apartment_id')) {
            $query->where('apartment_id', $request->get('apartment_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by manager
        if ($request->has('managed_by')) {
            $query->where('managed_by', $request->get('managed_by'));
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $tenants = $query->with(['apartment', 'manager', 'rentals'])->paginate($perPage);

        return TenantResource::collection($tenants);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'apartment_id' => ['required', 'exists:apartments,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'managed_by' => ['nullable', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'move_in_date' => ['required', 'date'],
            'move_out_date' => ['nullable', 'date', 'after:move_in_date'],
            'status' => ['required', Rule::in(['active', 'inactive', 'pending'])],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $tenant = Tenants::create($validated);

        return response()->json([
            'message' => 'Tenant created successfully',
            'data' => new TenantResource($tenant->load(['apartment', 'manager'])),
        ], 201);
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenants $tenant): TenantResource
    {
        return new TenantResource($tenant->load(['apartment', 'manager', 'rentals', 'utilities']));
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, Tenants $tenant): JsonResponse
    {
        $validated = $request->validate([
            'apartment_id' => ['sometimes', 'required', 'exists:apartments,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'managed_by' => ['nullable', 'exists:users,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'move_in_date' => ['sometimes', 'required', 'date'],
            'move_out_date' => ['nullable', 'date', 'after:move_in_date'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'pending'])],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $tenant->update($validated);

        return response()->json([
            'message' => 'Tenant updated successfully',
            'data' => new TenantResource($tenant->load(['apartment', 'manager'])),
        ]);
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(Tenants $tenant): JsonResponse
    {
        $tenant->delete();

        return response()->json([
            'message' => 'Tenant deleted successfully',
        ]);
    }

    /**
     * Archive a tenant.
     */
    public function archive(Tenants $tenant): JsonResponse
    {
        $tenant->update([
            'archived_at' => now(),
            'status' => 'inactive',
        ]);

        return response()->json([
            'message' => 'Tenant archived successfully',
            'data' => new TenantResource($tenant),
        ]);
    }

    /**
     * Get active tenants.
     */
    public function active(Request $request): AnonymousResourceCollection
    {
        $query = Tenants::where('status', 'active');

        if ($request->has('apartment_id')) {
            $query->where('apartment_id', $request->get('apartment_id'));
        }

        $perPage = $request->get('per_page', 15);
        $tenants = $query->with(['apartment', 'manager'])->paginate($perPage);

        return TenantResource::collection($tenants);
    }
}
