<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Tenants;
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
        $query = Apartments::with(['floor', 'tenants', 'supervisor']);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('apartment_number', 'like', "%{$search}%");
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $apartments = $query->get();
        
        // Group apartments by floor in ascending order
        $apartmentsByFloor = $apartments->filter(function($apartment) {
            return $apartment->floor !== null;
        })->groupBy(function($apartment) {
            return $apartment->floor->id;
        })->sortBy(function($group) {
            return $group->first()->floor->id;
        });
        
        $floorsWithApartments = Floors::with('apartments')->orderBy('id', 'asc')->get();
        $floors = Floors::orderBy('id', 'asc')->get();
        $statuses = ['available', 'occupied', 'maintenance'];
        $supervisors = User::role('supervisor')->get();
        $availableTenants = Tenants::where('status', 'active')->whereNull('apartment_id')->get();

        return view('admin.apartments.index', compact('apartmentsByFloor', 'floors', 'floorsWithApartments', 'statuses', 'supervisors', 'availableTenants'));
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
     * Assign a tenant to an apartment
     */
    public function assignTenant(Request $request, Apartments $apartment)
    {
        $validated = $request->validate([
            'tenant_option' => 'required|in:existing,new',
            'tenant_id' => 'nullable|required_if:tenant_option,existing|exists:tenants,id',
            'name' => 'nullable|required_if:tenant_option,new|string|max:255',
            'email' => 'nullable|required_if:tenant_option,new|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'id_pdf' => 'nullable|file|mimes:pdf|max:5120',
            'move_in_date' => 'required|date',
            'deposit' => 'required|numeric|min:0',
        ]);

        $tenant = null;
        $documentPath = null;

        // Handle PDF file upload
        if ($request->hasFile('id_pdf')) {
            $documentPath = $request->file('id_pdf')->store('tenants/id_documents', 'public');
        }

        if ($validated['tenant_option'] === 'existing') {
            // Use existing tenant
            $tenant = Tenants::findOrFail($validated['tenant_id']);
        } else {
            // Create new tenant
            $tenant = Tenants::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'document_path' => $documentPath,
                'apartment_id' => $apartment->id,
                'status' => 'active',
            ]);
        }
        
        // Update tenant information
        $updateData = [
            'apartment_id' => $apartment->id,
            'move_in_date' => $validated['move_in_date'],
            'deposit' => $validated['deposit'],
            'status' => 'active',
        ];

        if ($documentPath) {
            $updateData['document_path'] = $documentPath;
        }

        $tenant->update($updateData);

        // Update apartment status to occupied
        $apartment->update(['status' => 'occupied']);

        return redirect()->route('admin.apartments.index')->with('success', 'Tenant assigned successfully');
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
