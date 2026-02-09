<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FloorResource;
use App\Models\Floors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FloorController extends Controller
{
    /**
     * Display a listing of floors.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Floors::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('floor_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $floors = $query->with('apartments')->paginate($perPage);

        return FloorResource::collection($floors);
    }

    /**
     * Store a newly created floor.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'floor_name' => ['required', 'string', 'max:255', 'unique:floors,floor_name'],
            'description' => ['nullable', 'string'],
        ]);

        $floor = Floors::create($validated);

        return response()->json([
            'message' => 'Floor created successfully',
            'data' => new FloorResource($floor),
        ], 201);
    }

    /**
     * Display the specified floor.
     */
    public function show(Floors $floor): FloorResource
    {
        return new FloorResource($floor->load('apartments'));
    }

    /**
     * Update the specified floor.
     */
    public function update(Request $request, Floors $floor): JsonResponse
    {
        $validated = $request->validate([
            'floor_name' => ['sometimes', 'required', 'string', 'max:255', 'unique:floors,floor_name,' . $floor->id],
            'description' => ['nullable', 'string'],
        ]);

        $floor->update($validated);

        return response()->json([
            'message' => 'Floor updated successfully',
            'data' => new FloorResource($floor),
        ]);
    }

    /**
     * Remove the specified floor.
     */
    public function destroy(Floors $floor): JsonResponse
    {
        $floor->delete();

        return response()->json([
            'message' => 'Floor deleted successfully',
        ]);
    }
}
