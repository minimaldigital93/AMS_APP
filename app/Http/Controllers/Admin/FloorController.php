<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Floors;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class FloorController extends Controller
{
    /**
     * Display a listing of floors.
     */
    public function index(Request $request): View
    {
        $query = Floors::query()->withCount('apartments');

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('floor_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        $floors = $query->with('apartments')->paginate(10);

        return view('admin.propertyManagement.floors', compact('floors'));
    }

    /**
     * Store a newly created floor.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'floor_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'number_of_apartments' => 'integer|min:1',
        ]);

        Floors::create($validated);

        return redirect()->route('admin.propertymanagement.floors.index')->with('success', 'Floor created successfully');
    }

    /**
     * Update the specified floor.
     */
    public function update(Request $request, Floors $floor)
    {
        $validated = $request->validate([
            'floor_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $floor->update($validated);

        return redirect()->route('admin.propertymanagement.floors.index')->with('success', 'Floor updated successfully');
    }

    /**
     * Delete the specified floor.
     */
    public function destroy(Floors $floor)
    {
        $floor->delete();
        return redirect()->route('admin.propertymanagement.floors.index')->with('success', 'Floor deleted successfully');
    }

    /**
     * Get apartments for a specific floor.
     */
    public function getApartments(Floors $floor): View
    {
        $apartments = $floor->apartments()->paginate(10);
        return view('admin.propertyManagement.apartments', compact('floor', 'apartments'));
    }
}
