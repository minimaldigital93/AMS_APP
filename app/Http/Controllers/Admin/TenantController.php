<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Models\TenantLeave;
use App\Models\Rentals;
use App\Models\Apartments;
use App\Services\TenantLeaveCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class TenantController extends Controller
{
    protected TenantLeaveCalculator $leaveCalculator;

    public function __construct(TenantLeaveCalculator $leaveCalculator)
    {
        $this->leaveCalculator = $leaveCalculator;
    }

    /**
     * Display active tenants
     */
    public function index(Request $request): View
    {
        $query = Tenants::whereIn('status', ['active', 'pending'])
            ->with(['apartment']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function(Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apartment filter
        if ($request->has('apartment') && !empty($request->apartment)) {
            $query->where('apartment_id', $request->apartment);
        }

        // Status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $tenants = $query->orderBy('id', 'desc')->paginate(15);
        $apartments = Apartments::all();

        return view('admin.tenantManagement.activeTenants', compact('tenants', 'apartments'));
    }

    /**
     * Display archived tenants (soft deleted)
     */
    public function archived(): View
    {
        $tenants = Tenants::onlyTrashed()
            ->with(['apartment', 'leaves'])
            ->orderBy('deleted_at', 'desc')
            ->paginate(15);

        return view('admin.tenantManagement.archivedTenants', compact('tenants'));
    }

    /**
     * Show leave form for a tenant
     */
    public function leave(Tenants $tenant): View|RedirectResponse
    {
        // Check if tenant exists
        if (!$tenant) {
            return redirect()->route('admin.tenants.index')
                ->with('error', 'Tenant not found');
        }

        $tenant->load(['apartment', 'rentals']);

        // Get the current active rental (allow any rental, not just active ones)
        $rental = $tenant->rentals()
            ->where('apartment_id', $tenant->apartment_id)
            ->latest()
            ->first();

        // If no rental exists, create a rental object with data from apartment
        if (!$rental) {
            $rental = new Rentals();
            $rental->id = null;
            $rental->apartment_id = $tenant->apartment_id;
            $rental->tenant_id = $tenant->id;
            $rental->rent_amount = $tenant->apartment?->monthly_rent ?? 0;
            $rental->start_date = $tenant->move_in_date;
            $rental->end_date = null;
        }

        return view('admin.tenantManagement.leave', compact('tenant', 'rental'));
    }

    /**
     * Process tenant leave and create settlement
     */
    public function processLeave(Request $request, Tenants $tenant)
    {
        try {
            $tenant->load(['apartment', 'rentals']);

            // Get the current active rental
            $rental = $tenant->rentals()
                ->where('apartment_id', $tenant->apartment_id)
                ->where(function($query) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>', now());
                })
                ->latest()
                ->first();

            // If no rental exists, create one with tenant and apartment data
            if (!$rental) {
                $rental = Rentals::create([
                    'apartment_id' => $tenant->apartment_id,
                    'tenant_id' => $tenant->id,
                    'rent_amount' => $tenant->apartment?->monthly_rent ?? 0,
                    'start_date' => $tenant->move_in_date,
                    'end_date' => null,
                ]);
                
                \Log::info('Created rental record for tenant', [
                    'tenant_id' => $tenant->id,
                    'rental_id' => $rental->id,
                    'apartment_id' => $tenant->apartment_id,
                    'rent_amount' => $rental->rent_amount,
                    'start_date' => $rental->start_date,
                ]);
            }

            // Validate input
            $validated = $request->validate([
                'leave_date' => 'required|date|after_or_equal:' . $tenant->move_in_date->format('Y-m-d'),
                'electricity_reading' => 'nullable|numeric|min:0',
                'water_reading' => 'nullable|numeric|min:0',
                'internet_charge' => 'nullable|numeric|min:0',
                'parking_charge' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $leaveDate = Carbon::parse($validated['leave_date']);

            // Calculate charges
            $proRataRent = $this->leaveCalculator->calculateProRataRent($rental, $leaveDate);

            $meterReadings = [];
            if ($validated['electricity_reading']) {
                $meterReadings['electricity_reading'] = $validated['electricity_reading'];
            }
            if ($validated['water_reading']) {
                $meterReadings['water_reading'] = $validated['water_reading'];
            }

            $utilityCharges = $this->leaveCalculator->calculateUtilityCharges(
                $rental,
                $leaveDate,
                $meterReadings
            );

            // Override with provided values if any
            if (isset($validated['internet_charge'])) {
                $utilityCharges['internet'] = $validated['internet_charge'];
            }
            if (isset($validated['parking_charge'])) {
                $utilityCharges['parking'] = $validated['parking_charge'];
            }

            // Calculate settlement
            $charges = array_merge([
                'pro_rata_rent' => $proRataRent,
            ], $utilityCharges);

            $settlement = $this->leaveCalculator->calculateSettlement(
                $rental,
                $tenant,
                $leaveDate,
                $charges,
                $tenant->deposit ?? 0
            );

            // Create tenant leave record
            $tenantLeave = TenantLeave::create([
                'tenant_id' => $tenant->id,
                'rental_id' => $rental->id,
                'apartment_id' => $tenant->apartment_id,
                'leave_date' => $leaveDate,
                'original_move_out_date' => $rental->end_date,
                'stay_days' => $settlement['stay_days'],
                'pro_rata_rent' => $settlement['pro_rata_rent'],
                'electricity_reading' => $validated['electricity_reading'] ?? null,
                'electricity_charge' => $settlement['electricity_charge'],
                'water_reading' => $validated['water_reading'] ?? null,
                'water_charge' => $settlement['water_charge'],
                'internet_charge' => $settlement['internet_charge'],
                'parking_charge' => $settlement['parking_charge'],
                'total_amount_due' => $settlement['total_amount_due'],
                'deposit_applied' => $settlement['deposit_applied'],
                'balance_due' => $settlement['balance_due'],
                'refund_amount' => $settlement['refund_amount'],
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            \Log::info('Created tenant leave record', [
                'tenant_leave_id' => $tenantLeave->id,
                'tenant_id' => $tenant->id,
            ]);

            // Update rental end date
            $rental->update(['end_date' => $leaveDate]);

            \Log::info('Updated rental end date', [
                'rental_id' => $rental->id,
                'end_date' => $leaveDate,
            ]);

            // Save apartment reference before clearing tenant
            $apartment = $tenant->apartment;

            \Log::info('Starting tenant archival process', [
                'tenant_id' => $tenant->id,
                'apartment_id' => $apartment?->id,
            ]);

            // Archive tenant (set status to moved_out and archived_at)
            $archiveResult = $this->leaveCalculator->archiveTenant($tenant, now());
            \Log::info('Archived tenant', [
                'tenant_id' => $tenant->id,
                'result' => $archiveResult,
            ]);

            // Clear tenant from apartment (remove apartment assignment)
            $clearResult = $this->leaveCalculator->clearTenantFromApartment($tenant);
            \Log::info('Cleared tenant from apartment', [
                'tenant_id' => $tenant->id,
                'result' => $clearResult,
            ]);

            // Mark apartment as available (using saved reference)
            if ($apartment) {
                $apartmentResult = $this->leaveCalculator->markApartmentAvailable($apartment);
                \Log::info('Marked apartment as available', [
                    'apartment_id' => $apartment->id,
                    'result' => $apartmentResult,
                ]);
            }

            // Soft delete the tenant record (will be preserved in tenant_leaves history)
            $deleteResult = $tenant->delete();
            \Log::info('Soft deleted tenant', [
                'tenant_id' => $tenant->id,
                'result' => $deleteResult,
            ]);

            return redirect()
                ->route('admin.tenants.archived')
                ->with('success', 'Tenant leave processed successfully. Settlement created.');

        } catch (\Exception $e) {
            \Log::error('Error processing tenant leave: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Error processing leave: ' . $e->getMessage());
        }
    }

    /**
     * Show create tenant form
     */
    public function create(): View
    {
        $apartments = Apartments::all();
        return view('admin.tenantManagement.createTenant', compact('apartments'));
    }

    /**
     * Store a newly created tenant
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'apartment_id' => 'required|exists:apartments,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'move_in_date' => 'required|date',
            'move_out_date' => 'nullable|date|after:move_in_date',
            'status' => 'required|in:pending,active,inactive',
            'deposit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        Tenants::create($validated);

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant created successfully!');
    }

    /**
     * Show tenant details
     */
    public function show(Tenants $tenant): View
    {
        $tenant->load(['apartment', 'rentals', 'utilities']);
        return view('admin.tenantManagement.showTenant', compact('tenant'));
    }

    /**
     * Show edit tenant form
     */
    public function edit(Tenants $tenant): View
    {
        $apartments = Apartments::all();
        return view('admin.tenantManagement.editTenant', compact('tenant', 'apartments'));
    }

    /**
     * Update a tenant
     */
    public function update(Request $request, Tenants $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'apartment_id' => 'required|exists:apartments,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:tenants,email,' . $tenant->id,
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'move_in_date' => 'required|date',
            'move_out_date' => 'nullable|date|after:move_in_date',
            'status' => 'required|in:pending,active,inactive',
            'deposit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $tenant->update($validated);

        return redirect()->route('admin.tenants.show', $tenant->id)
            ->with('success', 'Tenant updated successfully!');
    }
}

