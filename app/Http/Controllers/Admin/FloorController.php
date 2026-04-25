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

    public function create(): View
    {
        return view('admin.floors.create');
    }

    public function edit(Floors $floor): View
    {
        $floor->load('apartments');
        return view('admin.floors.edit', compact('floor'));
    }

    public function getApartments(Floors $floor): View
    {
        $apartments = $floor->apartments()->paginate(10);
        return view('admin.apartments.index', compact('floor', 'apartments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'floor_name'                        => 'required|string|max:255',
            'description'                       => 'nullable|string',
            'apartments'                        => 'nullable|array',
            'apartments.*.apartment_number'     => [
                'required', 'string', 'max:255',
                Rule::unique('apartments', 'apartment_number')->where('deleted_at', null),
            ],
            'apartments.*.monthly_rent'         => 'nullable|numeric|min:0',
            'apartments.*.status'               => 'nullable|in:available,occupied,maintenance',
        ]);

        $floor = Floors::create([
            'floor_name'  => $validated['floor_name'],
            'description' => $validated['description'] ?? null,
        ]);

        $apartmentsCreated = 0;
        foreach ($validated['apartments'] ?? [] as $apt) {
            try {
                $floor->apartments()->create([
                    'apartment_number' => $apt['apartment_number'],
                    'monthly_rent'     => $apt['monthly_rent'] ?? 0,
                    'status'           => $apt['status'] ?? 'available',
                ]);
                $apartmentsCreated++;
            } catch (\Exception $e) {
                Log::error('Error creating apartment for floor ' . $floor->id . ': ' . $e->getMessage());
            }
        }

        $message = 'Floor created successfully';
        if ($apartmentsCreated > 0) {
            $message .= " with {$apartmentsCreated} apartment(s)";
        }

        return redirect()->route('admin.floors.index')->with('success', $message);
    }


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
        
        // ACTION: Add New Apartment to Existing Floor
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


        // ACTION: Update Floor Information
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

    public function destroy(Floors $floor)
    {
        Log::warning('!!! FLOOR DESTROY METHOD CALLED !!!', [
            'floor_id' => $floor->id,
            'floor_name' => $floor->floor_name,
        ]);
        $floor->delete();
        return redirect()->route('admin.floors.index')->with('success', 'Floor deleted successfully');
    }
}
