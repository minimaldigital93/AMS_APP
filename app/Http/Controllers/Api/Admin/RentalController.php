<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RentalResource;
use App\Models\Rentals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RentalController extends Controller
{
    /**
     * Display a listing of rentals.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Rentals::query();

        // Filter by apartment
        if ($request->has('apartment_id')) {
            $query->where('apartment_id', $request->get('apartment_id'));
        }

        // Filter by tenant
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->get('tenant_id'));
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('start_date', '>=', $request->get('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('end_date', '<=', $request->get('end_date'));
        }

        // Active rentals only
        if ($request->boolean('active_only')) {
            $query->where('start_date', '<=', now())
                  ->where(function ($q) {
                      $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                  });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $rentals = $query->with(['apartment', 'tenant', 'payments'])->paginate($perPage);

        return RentalResource::collection($rentals);
    }

    /**
     * Store a newly created rental.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'apartment_id' => ['required', 'exists:apartments,id'],
            'tenant_id' => ['required', 'exists:tenants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rental = Rentals::create($validated);

        return response()->json([
            'message' => 'Rental created successfully',
            'data' => new RentalResource($rental->load(['apartment', 'tenant'])),
        ], 201);
    }

    /**
     * Display the specified rental.
     */
    public function show(Rentals $rental): RentalResource
    {
        return new RentalResource($rental->load(['apartment', 'tenant', 'payments', 'utilities']));
    }

    /**
     * Update the specified rental.
     */
    public function update(Request $request, Rentals $rental): JsonResponse
    {
        $validated = $request->validate([
            'apartment_id' => ['sometimes', 'required', 'exists:apartments,id'],
            'tenant_id' => ['sometimes', 'required', 'exists:tenants,id'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rental->update($validated);

        return response()->json([
            'message' => 'Rental updated successfully',
            'data' => new RentalResource($rental->load(['apartment', 'tenant'])),
        ]);
    }

    /**
     * Remove the specified rental.
     */
    public function destroy(Rentals $rental): JsonResponse
    {
        $rental->delete();

        return response()->json([
            'message' => 'Rental deleted successfully',
        ]);
    }

    /**
     * Get active rentals.
     */
    public function active(Request $request): AnonymousResourceCollection
    {
        $query = Rentals::where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });

        $perPage = $request->get('per_page', 15);
        $rentals = $query->with(['apartment', 'tenant'])->paginate($perPage);

        return RentalResource::collection($rentals);
    }
}
