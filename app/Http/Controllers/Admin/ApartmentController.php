<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApartmentController extends Controller
{
    /**
     * Display a listing of apartments.
     */
    public function index(Request $request): View
    {
        $query = Apartments::with('floor');

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('apartment_number', 'like', "%{$search}%");
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $apartments = $query->paginate(15);
        $floorsWithApartments = Floors::with('apartments')->get();
        $floors = Floors::all();
        $statuses = ['available', 'occupied', 'maintenance'];
        $supervisors = User::role('supervisor')->get();

        return view('admin.propertyManagement.apartments', compact('apartments', 'floors', 'floorsWithApartments', 'statuses', 'supervisors'));
    }

    /**
     * Store a newly created apartment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'apartment_number' => 'required|string|unique:apartments',
            'floor_id' => 'required|exists:floors,id',
            'monthly_rent' => 'required|numeric|min:0',
            'status' => 'required|in:available,occupied,maintenance',
        ]);

        Apartments::create($validated);

        return redirect()->route('admin.propertymanagement.apartments.index')->with('success', 'Apartment created successfully');
    }

    /**
     * Update the specified apartment.
     */
    public function update(Request $request, Apartments $apartment)
    {
        $validated = $request->validate([
            'apartment_number' => 'required|string|unique:apartments,apartment_number,' . $apartment->id,
            'monthly_rent' => 'required|numeric|min:0',
            'status' => 'required|in:available,occupied,maintenance',
        ]);

        $apartment->update($validated);

        return redirect()->route('admin.propertymanagement.apartments.index')->with('success', 'Apartment updated successfully');
    }

    /**
     * Delete the specified apartment.
     */
    public function destroy(Apartments $apartment)
    {
        $apartment->delete();
        return redirect()->route('admin.propertymanagement.apartments.index')->with('success', 'Apartment deleted successfully');
    }
}
