<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApartmentResource;
use App\Models\Apartments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ApartmentController extends Controller
{
    /**
     * Display apartments managed by the supervisor.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Apartments::where('supervisor_id', Auth::id());

        // Filter by floor
        if ($request->has('floor_id')) {
            $query->where('floor_id', $request->get('floor_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('apartment_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $apartments = $query->with(['floor', 'tenants'])->paginate($perPage);

        return ApartmentResource::collection($apartments);
    }

    /**
     * Display the specified apartment (if managed by supervisor).
     */
    public function show(Apartments $apartment): ApartmentResource|JsonResponse
    {
        if ($apartment->supervisor_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new ApartmentResource($apartment->load(['floor', 'tenants', 'rentals']));
    }

    /**
     * Update apartment status.
     */
    public function update(Request $request, Apartments $apartment): JsonResponse
    {
        if ($apartment->supervisor_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['available', 'occupied', 'maintenance'])],
            'description' => ['nullable', 'string'],
        ]);

        $apartment->update($validated);

        return response()->json([
            'message' => 'Apartment updated successfully',
            'data' => new ApartmentResource($apartment),
        ]);
    }

    /**
     * Get available apartments managed by supervisor.
     */
    public function available(): AnonymousResourceCollection
    {
        $apartments = Apartments::where('supervisor_id', Auth::id())
            ->where('status', 'available')
            ->with('floor')
            ->get();

        return ApartmentResource::collection($apartments);
    }
}
