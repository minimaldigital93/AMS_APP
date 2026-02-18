<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApartmentResource;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ApartmentController extends Controller
{
    /**
     * Display a listing of apartments.
     */
    public function index(Request $request)
    {
        $query = Apartments::with(['floor', 'supervisor']);

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
        $perPage = $request->get('per_page', $request->wantsJson() ? 15 : 10);
        $apartments = $query->with(['floor', 'supervisor', 'tenants'])->paginate($perPage);

        // Return view for web requests, JSON for API requests
        if ($request->wantsJson() || $request->is('api/*')) {
            return ApartmentResource::collection($apartments);
        }

        // Get all floors and supervisors for dropdowns
        $floors = Floors::orderBy('floor_name')->get();
        $supervisors = User::role('supervisor')->orderBy('name')->get();

        return view('admin.PropertyManagement.apartments', compact('apartments', 'floors', 'supervisors'));
    }

    /**
     * Store a newly created apartment.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'floor_id' => ['required', 'exists:floors,id'],
                'supervisor_id' => ['nullable', 'exists:users,id'],
                'apartment_number' => [
                    'required', 
                    'string', 
                    'max:50', 
                    Rule::unique('apartments', 'apartment_number')->whereNull('deleted_at')
                ],
                'monthly_rent' => ['required', 'numeric', 'min:0'],
                'status' => ['required', Rule::in(['available', 'occupied', 'maintenance'])],
                'description' => ['nullable', 'string'],
            ]);

            $apartment = Apartments::create($validated);

            // Return JSON for API requests, redirect for web requests
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Apartment created successfully',
                    'data' => new ApartmentResource($apartment->load(['floor', 'supervisor'])),
                ], 201);
            }

            return redirect()->route('admin.apartments.index')
                           ->with('success', 'Apartment created successfully');
        } catch (\Exception $e) {
            Log::error('Apartment creation failed: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Failed to create apartment',
                    'error' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                           ->with('error', 'Failed to create apartment: ' . $e->getMessage())
                           ->withInput();
        }
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
    public function update(Request $request, Apartments $apartment)
    {
        try {
            $validated = $request->validate([
                'floor_id' => ['required', 'exists:floors,id'],
                'supervisor_id' => ['nullable', 'exists:users,id'],
                'apartment_number' => [
                    'required', 
                    'string', 
                    'max:50', 
                    Rule::unique('apartments', 'apartment_number')
                        ->ignore($apartment->id)
                        ->whereNull('deleted_at')
                ],
                'monthly_rent' => ['required', 'numeric', 'min:0'],
                'status' => ['required', Rule::in(['available', 'occupied', 'maintenance'])],
                'description' => ['nullable', 'string'],
            ]);

            $apartment->update($validated);

            // Return JSON for API requests, redirect for web requests
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Apartment updated successfully',
                    'data' => new ApartmentResource($apartment->load(['floor', 'supervisor'])),
                ]);
            }

            return redirect()->route('admin.apartments.index')
                           ->with('success', 'Apartment updated successfully');
        } catch (\Exception $e) {
            Log::error('Apartment update failed: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Failed to update apartment',
                    'error' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                           ->with('error', 'Failed to update apartment: ' . $e->getMessage())
                           ->withInput();
        }
    }

    /**
     * Remove the specified apartment.
     */
    public function destroy(Request $request, Apartments $apartment)
    {
        try {
            // Check if apartment has active rentals
            if ($apartment->rentals()->where('status', 'active')->count() > 0) {
                if ($request->wantsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Cannot delete apartment with active rentals'
                    ], 400);
                }
                
                return redirect()->back()
                               ->with('error', 'Cannot delete apartment with active rentals.');
            }

            $apartment->delete();

            // Return JSON for API requests, redirect for web requests
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Apartment deleted successfully',
                ]);
            }

            return redirect()->route('admin.apartments.index')
                           ->with('success', 'Apartment deleted successfully');
        } catch (\Exception $e) {
            Log::error('Apartment deletion failed: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Failed to delete apartment',
                    'error' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                           ->with('error', 'Failed to delete apartment: ' . $e->getMessage());
        }
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
