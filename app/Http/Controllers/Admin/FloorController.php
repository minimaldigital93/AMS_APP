<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Floors;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class FloorController extends Controller
{
    /**
     * Show the form for creating a new floor.
     */
    public function create(): View
    {
        $tempApartments = session('temp_apartments', []);
        return view('admin.floors.create', compact('tempApartments'));
    }

    /**
     * Show the form for editing the specified floor.
     */
    public function edit(Floors $floor): View
    {
        $floor->load('apartments');
        return view('admin.floors.edit', compact('floor'));
    }

    /**
     * Display a listing of floors.
     */
    public function index(Request $request): View
    {
        $query = Floors::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('floor_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        $floors = $query->with(['apartments' => function ($query) {
            $query->with('supervisor')->orderBy('apartment_number');
        }])->withCount('apartments')->paginate(10);

        return view('admin.floors.index', compact('floors'));
    }

    /**
     * Store a newly created floor.
     */
    public function store(Request $request)
    {
        $action = $request->input('action', 'create_floor');
        
        // If adding an apartment to the list
        if ($action === 'add_apartment') {
            $validated = $request->validate([
                'floor_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'apartment_number' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('apartments', 'apartment_number')
                        ->where('deleted_at', null)
                ],
                'monthly_rent' => 'nullable|numeric|min:0',
                'apartment_status' => 'nullable|in:available,occupied,maintenance',
            ]);

            // Get existing temp apartments
            $tempApartments = session('temp_apartments', []);
            
            // Add new apartment
            $tempApartments[] = [
                'apartment_number' => $validated['apartment_number'],
                'monthly_rent' => $validated['monthly_rent'] ?? 0,
                'status' => $validated['apartment_status'] ?? 'available',
            ];
            
            // Store in session
            session(['temp_apartments' => $tempApartments]);
            session(['floor_data' => [
                'floor_name' => $validated['floor_name'],
                'description' => $validated['description'] ?? null,
            ]]);

            return redirect()->route('admin.floors.create')
                ->with('success', 'Unit added successfully!')
                ->withInput($request->only('floor_name', 'description'));
        }
        
        // Creating the floor
        $validated = $request->validate([
            'floor_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'apartments' => 'nullable|array',
        ]);

        // Create the floor
        $floor = Floors::create([
            'floor_name' => $validated['floor_name'],
            'description' => $validated['description'] ?? null,
        ]);

        // Create apartments ONLY from session (they contain the submitted data via hidden inputs)
        $tempApartments = session('temp_apartments', []);
        
        $apartmentsCreated = 0;
        if (!empty($tempApartments)) {
            foreach ($tempApartments as $apartmentData) {
                try {
                    $floor->apartments()->create([
                        'apartment_number' => $apartmentData['apartment_number'],
                        'monthly_rent' => $apartmentData['monthly_rent'] ?? 0,
                        'status' => $apartmentData['status'] ?? 'available',
                    ]);
                    $apartmentsCreated++;
                } catch (\Exception $e) {
                    Log::error('Error creating apartment for floor ' . $floor->id . ': ' . $e->getMessage());
                }
            }
        }

        // Clear session
        session()->forget('temp_apartments');
        session()->forget('floor_data');

        $message = 'Floor created successfully';
        if ($apartmentsCreated > 0) {
            $message .= " with $apartmentsCreated apartment(s)";
        }

        return redirect()->route('admin.floors.index')->with('success', $message);
    }

    /**
     * Update the specified floor.
     */
    public function update(Request $request, Floors $floor)
    {
        Log::info('=== FLOOR UPDATE METHOD CALLED ===', [
            'method' => $request->method(),
            'path' => $request->path(),
            'action' => $request->input('action', 'update_floor'),
            'all_inputs' => $request->all(),
            'floor_id' => $floor->id,
            '_method_input' => $request->input('_method')
        ]);
        
        $action = $request->input('action', 'update_floor');
        
        // If adding a new apartment
        if ($action === 'add_apartment') {
            $validated = $request->validate([
                'apartment_number' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('apartments', 'apartment_number')
                        ->where('deleted_at', null)
                ],
                'monthly_rent' => 'nullable|numeric|min:0',
                'apartment_status' => 'required|in:available,occupied,maintenance',
            ]);

            // Create the apartment
            try {
                $floor->apartments()->create([
                    'apartment_number' => $validated['apartment_number'],
                    'monthly_rent' => $validated['monthly_rent'] ?? 0,
                    'status' => $validated['apartment_status'] ?? 'available',
                ]);

                return redirect()->route('admin.floors.edit', $floor)
                    ->with('success', 'Unit added successfully!');
            } catch (\Exception $e) {
                Log::error('Error creating apartment for floor ' . $floor->id . ': ' . $e->getMessage());
                return redirect()->route('admin.floors.edit', $floor)
                    ->withErrors(['apartment_number' => 'Error adding apartment']);
            }
        }

        // Update floor information only (default action: update_floor)
        $validated = $request->validate([
            'floor_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $floor->update([
                'floor_name' => $validated['floor_name'],
                'description' => $validated['description'] ?? null,
            ]);

            Log::info('Floor updated successfully, redirecting to index', ['floor_id' => $floor->id]);

            return redirect()
                ->route('admin.floors.index')
                ->with('success', 'Floor updated successfully');
        } catch (\Exception $e) {
            Log::error('Error updating floor ' . $floor->id . ': ' . $e->getMessage());
            return redirect()
                ->route('admin.floors.edit', $floor)
                ->withErrors(['error' => 'Error updating floor']);
        }
    }

    /**
     * Delete the specified floor.
     */
    public function destroy(Floors $floor)
    {
        Log::warning('!!! FLOOR DESTROY METHOD CALLED !!!', [
            'floor_id' => $floor->id,
            'floor_name' => $floor->floor_name,
        ]);
        $floor->delete();
        return redirect()->route('admin.floors.index')->with('success', 'Floor deleted successfully');
    }

    /**
     * Get apartments for a specific floor.
     */
    public function getApartments(Floors $floor): View
    {
        $apartments = $floor->apartments()->paginate(10);
        return view('admin.apartments.index', compact('floor', 'apartments'));
    }
}
