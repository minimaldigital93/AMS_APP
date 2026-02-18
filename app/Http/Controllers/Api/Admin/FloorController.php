<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FloorResource;
use App\Models\Floors;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class FloorController extends Controller
{
    /**
     * Display a listing of floors.
     */
    public function index(Request $request)
    {
        $query = Floors::query()->withCount('apartments');

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
        $perPage = $request->get('per_page', $request->wantsJson() ? 15 : 10);
        $floors = $query->with('apartments')->paginate($perPage);

        // Return view for web requests, JSON for API requests
        if ($request->wantsJson() || $request->is('api/*')) {
            return FloorResource::collection($floors);
        }

        // Get supervisors for the dropdown
        $supervisors = User::role('supervisor')->orderBy('name')->get();

        return view('admin.PropertyManagement.floors', compact('floors', 'supervisors'));
    }

    /**
     * Store a newly created floor.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'floor_name' => ['required', 'string', 'max:255', Rule::unique('floors', 'floor_name')->whereNull('deleted_at')],
                'description' => ['nullable', 'string'],
                'apartments' => ['nullable', 'array'],
                'apartments.*.apartment_number' => [
                    'required_with:apartments', 
                    'string', 
                    'max:50', 
                    Rule::unique('apartments', 'apartment_number')->whereNull('deleted_at')
                ],
                'apartments.*.monthly_rent' => ['required_with:apartments', 'numeric', 'min:0'],
                'apartments.*.status' => ['required_with:apartments', 'in:available,occupied,maintenance'],
                'apartments.*.supervisor_id' => ['nullable', 'exists:users,id'],
                'apartments.*.description' => ['nullable', 'string'],
            ]);

            // Create floor
            $floor = Floors::create([
                'floor_name' => $validated['floor_name'],
                'description' => $validated['description'] ?? null,
            ]);

            // Create apartments if provided
            if (isset($validated['apartments']) && is_array($validated['apartments'])) {
                foreach ($validated['apartments'] as $apartmentData) {
                    $floor->apartments()->create([
                        'apartment_number' => $apartmentData['apartment_number'],
                        'monthly_rent' => $apartmentData['monthly_rent'],
                        'status' => $apartmentData['status'],
                        'supervisor_id' => $apartmentData['supervisor_id'] ?? null,
                        'description' => $apartmentData['description'] ?? null,
                    ]);
                }
            }

            $floor->load('apartments');

            // Return JSON for API requests, redirect for web requests
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Floor created successfully' . (isset($validated['apartments']) ? ' with ' . count($validated['apartments']) . ' apartments' : ''),
                    'data' => new FloorResource($floor),
                ], 201);
            }

            $apartmentCount = isset($validated['apartments']) ? count($validated['apartments']) : 0;
            $message = $apartmentCount > 0 
                ? "Floor created successfully with {$apartmentCount} apartment(s)"
                : 'Floor created successfully';
            
            return redirect()->route('admin.floors.index')
                           ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Floor creation failed: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Failed to create floor',
                    'error' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                           ->with('error', 'Failed to create floor: ' . $e->getMessage())
                           ->withInput();
        }
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
    public function update(Request $request, Floors $floor)
    {
        try {
            $validated = $request->validate([
                'floor_name' => [
                    'required', 
                    'string', 
                    'max:255', 
                    Rule::unique('floors', 'floor_name')
                        ->ignore($floor->id)
                        ->whereNull('deleted_at')
                ],
                'description' => ['nullable', 'string'],
            ]);

            $floor->update($validated);

            // Return JSON for API requests, redirect for web requests
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Floor updated successfully',
                    'data' => new FloorResource($floor),
                ]);
            }

            return redirect()->route('admin.floors.index')
                           ->with('success', 'Floor updated successfully');
        } catch (\Exception $e) {
            Log::error('Floor update failed: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Failed to update floor',
                    'error' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                           ->with('error', 'Failed to update floor: ' . $e->getMessage())
                           ->withInput();
        }
    }

    /**
     * Remove the specified floor.
     */
    public function destroy(Request $request, Floors $floor)
    {
        try {
            // Get apartment count before deletion
            $apartmentCount = $floor->apartments()->count();
            
            // Delete all apartments first (cascade delete)
            if ($apartmentCount > 0) {
                $floor->apartments()->delete();
            }

            // Then delete the floor
            $floor->delete();

            // Return JSON for API requests, redirect for web requests
            if ($request->wantsJson() || $request->is('api/*')) {
                $message = $apartmentCount > 0 
                    ? "Floor and {$apartmentCount} apartment(s) deleted successfully"
                    : 'Floor deleted successfully';
                return response()->json([
                    'message' => $message,
                ]);
            }

            $message = $apartmentCount > 0 
                ? "Floor and {$apartmentCount} apartment(s) deleted successfully"
                : 'Floor deleted successfully';
            return redirect()->route('admin.floors.index')
                           ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Floor deletion failed: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Failed to delete floor',
                    'error' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                           ->with('error', 'Failed to delete floor: ' . $e->getMessage());
        }
    }
    
    /**
     * Get apartments for a specific floor.
     */
    public function getApartments(Floors $floor)
    {
        // Get apartments (SoftDeletes trait automatically excludes deleted records)
        $apartments = $floor->apartments()
                           ->with('supervisor')
                           ->orderBy('apartment_number')
                           ->get();

        return response()->json([
            'success' => true,
            'data' => $apartments,
            'count' => $apartments->count(),
        ]);
    }
}
