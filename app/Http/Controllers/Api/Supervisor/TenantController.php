<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Apartments;
use App\Models\Tenants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class TenantController extends Controller
{
    /**
     * Get apartment IDs managed by the current supervisor.
     */
    private function getManagedApartmentIds(): array
    {
        return Apartments::where('supervisor_id', Auth::id())->pluck('id')->toArray();
    }

    /**
     * Display tenants in apartments managed by the supervisor.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $apartmentIds = $this->getManagedApartmentIds();

        $query = Tenants::whereIn('apartment_id', $apartmentIds);

        // Filter by apartment
        if ($request->has('apartment_id')) {
            $query->where('apartment_id', $request->get('apartment_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $tenants = $query->with(['apartment', 'rentals'])->paginate($perPage);

        return TenantResource::collection($tenants);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $apartmentIds = $this->getManagedApartmentIds();

        $validated = $request->validate([
            'apartment_id' => ['required', 'exists:apartments,id', Rule::in($apartmentIds)],
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

        $validated['managed_by'] = Auth::id();

        $tenant = Tenants::create($validated);

        return response()->json([
            'message' => 'Tenant created successfully',
            'data' => new TenantResource($tenant->load('apartment')),
        ], 201);
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenants $tenant): TenantResource|JsonResponse
    {
        $apartmentIds = $this->getManagedApartmentIds();

        if (!in_array($tenant->apartment_id, $apartmentIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new TenantResource($tenant->load(['apartment', 'rentals', 'utilities']));
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, Tenants $tenant): JsonResponse
    {
        $apartmentIds = $this->getManagedApartmentIds();

        if (!in_array($tenant->apartment_id, $apartmentIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'apartment_id' => ['sometimes', 'required', 'exists:apartments,id', Rule::in($apartmentIds)],
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
            'data' => new TenantResource($tenant->load('apartment')),
        ]);
    }

    /**
     * Archive a tenant.
     */
    public function archive(Tenants $tenant): JsonResponse
    {
        $apartmentIds = $this->getManagedApartmentIds();

        if (!in_array($tenant->apartment_id, $apartmentIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

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
     * Get active tenants managed by supervisor.
     */
    public function active(): AnonymousResourceCollection
    {
        $apartmentIds = $this->getManagedApartmentIds();

        $tenants = Tenants::whereIn('apartment_id', $apartmentIds)
            ->where('status', 'active')
            ->with('apartment')
            ->get();

        return TenantResource::collection($tenants);
    }
}
