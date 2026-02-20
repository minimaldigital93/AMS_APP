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

        return view('admin.apartments.index', compact('apartments', 'floors', 'floorsWithApartments', 'statuses', 'supervisors'));
    }

    /**
     * Show the form for creating a new apartment.
     */
    public function create(): View
    {
        $floors = Floors::all();
        $supervisors = User::role('supervisor')->get();
        $statuses = ['available', 'occupied', 'maintenance'];

        return view('admin.apartments.create', compact('floors', 'supervisors', 'statuses'));
    }

    /**
     * Show the apartment details.
     */
    public function show(Apartments $apartment): View
    {
        $apartment = $apartment->load('floor', 'supervisor');
        return view('admin.apartments.show', compact('apartment'));
    }

    /**
     * Show the form for editing an apartment.
     */
    public function edit(Apartments $apartment): View
    {
        $apartment = $apartment->load('floor', 'supervisor');
        $floors = Floors::all();
        $supervisors = User::role('supervisor')->get();
        $statuses = ['available', 'occupied', 'maintenance'];

        return view('admin.apartments.edit', compact('apartment', 'floors', 'supervisors', 'statuses'));
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
            'supervisor_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        Apartments::create($validated);

        return redirect()->route('admin.apartments.index')->with('success', 'Apartment created successfully');
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
            'supervisor_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        $apartment->update($validated);

        return redirect()->route('admin.apartments.index')->with('success', 'Apartment updated successfully');
    }

    /**
     * Delete the specified apartment.
     */
    public function destroy(Apartments $apartment)
    {
        $floor = $apartment->floor;
        $apartment->delete();
        
        // Check if request came from floor edit page
        $referrer = request()->headers->get('referer');
        if ($referrer && str_contains($referrer, '/admin/floors/') && str_contains($referrer, '/edit')) {
            return back()->with('success', 'Apartment deleted successfully');
        }
        
        return redirect()->route('admin.apartments.index')->with('success', 'Apartment deleted successfully');
    }
}
