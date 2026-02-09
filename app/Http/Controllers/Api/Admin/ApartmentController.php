<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApartmentResource;
use App\Models\Apartments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ApartmentController extends Controller
{
    /**
     * Display a listing of apartments.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Apartments::query();

        // Filter by floor
        if ($request->has('floor_id')) {
            $query->where('floor_id', $request->get('floor_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by supervisor
        if ($request->has('supervisor_id')) {
            $query->where('supervisor_id', $request->get('supervisor_id'));
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('apartment_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $apartments = $query->with(['floor', 'supervisor', 'tenants'])->paginate($perPage);

        return ApartmentResource::collection($apartments);
    }

    /**
     * Store a newly created apartment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'floor_id' => ['required', 'exists:floors,id'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'apartment_number' => ['required', 'string', 'max:50', 'unique:apartments,apartment_number'],
            'monthly_rent' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['available', 'occupied', 'maintenance'])],
            'description' => ['nullable', 'string'],
        ]);

        $apartment = Apartments::create($validated);

        return response()->json([
            'message' => 'Apartment created successfully',
            'data' => new ApartmentResource($apartment->load(['floor', 'supervisor'])),
        ], 201);
    }

    /**
     * Display the specified apartment.
     */
    public function show(Apartments $apartment): ApartmentResource
    {
        return new ApartmentResource($apartment->load(['floor', 'supervisor', 'tenants', 'rentals']));
    }

    /**
     * Update the specified apartment.
     */
    public function update(Request $request, Apartments $apartment): JsonResponse
    {
        $validated = $request->validate([
            'floor_id' => ['sometimes', 'required', 'exists:floors,id'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'apartment_number' => ['sometimes', 'required', 'string', 'max:50', 'unique:apartments,apartment_number,' . $apartment->id],
            'monthly_rent' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status' => ['sometimes', 'required', Rule::in(['available', 'occupied', 'maintenance'])],
            'description' => ['nullable', 'string'],
        ]);

        $apartment->update($validated);

        return response()->json([
            'message' => 'Apartment updated successfully',
            'data' => new ApartmentResource($apartment->load(['floor', 'supervisor'])),
        ]);
    }

    /**
     * Remove the specified apartment.
     */
    public function destroy(Apartments $apartment): JsonResponse
    {
        $apartment->delete();

        return response()->json([
            'message' => 'Apartment deleted successfully',
        ]);
    }

    /**
     * Get available apartments.
     */
    public function available(Request $request): AnonymousResourceCollection
    {
        $query = Apartments::where('status', 'available');

        if ($request->has('floor_id')) {
            $query->where('floor_id', $request->get('floor_id'));
        }

        $perPage = $request->get('per_page', 15);
        $apartments = $query->with(['floor', 'supervisor'])->paginate($perPage);

        return ApartmentResource::collection($apartments);
    }
}
