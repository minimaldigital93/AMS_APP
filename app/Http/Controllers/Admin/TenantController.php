<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Models\TenantLeave;
use App\Models\Rentals;
use App\Services\TenantLeaveCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
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
    public function index(): View
    {
        $tenants = Tenants::whereIn('status', ['active', 'pending'])
            ->with(['apartment'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('admin.tenantManagement.activeTenants', compact('tenants'));
    }

    /**
     * Display archived tenants
     */
    public function archived(): View
    {
        $tenants = Tenants::where(function($query) {
                $query->whereNotNull('archived_at')
                      ->orWhere('status', 'moved_out');
            })
            ->with(['apartment', 'leaves'])
            ->orderBy('archived_at', 'desc')
            ->paginate(15);

        return view('admin.tenantManagement.archivedTenants', compact('tenants'));
    }

    /**
     * Show leave form for a tenant
     */
    public function leave(Tenants $tenant): View|RedirectResponse
    {
        // Check if tenant exists and is active
        if (!$tenant || $tenant->status !== 'active') {
            return redirect()->route('admin.tenants.index')
                ->with('error', 'Tenant not found or is not active');
        }

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

        if (!$rental) {
            return redirect()->route('admin.tenants.index')
                ->with('error', 'No active rental found for this tenant. Cannot process leave.');
        }

        return view('admin.tenantManagement.leaveProcessing', compact('tenant', 'rental'));
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

            if (!$rental) {
                return back()->with('error', 'No active rental found for this tenant');
            }

            // Validate input
            $validated = $request->validate([
                'leave_date' => 'required|date|after_or_equal:' . $tenant->move_in_date,
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

        // Update rental end date
        $rental->update(['end_date' => $leaveDate]);

        // Archive tenant
        $this->leaveCalculator->archiveTenant($tenant, now());

        // Clear tenant from apartment (remove apartment assignment)
        $this->leaveCalculator->clearTenantFromApartment($tenant);

        // Mark apartment as available
        $this->leaveCalculator->markApartmentAvailable($tenant->apartment);

            return redirect()
                ->route('admin.tenants.archived')
                ->with('success', 'Tenant leave processed successfully. Settlement created.');
        } catch (\Exception $e) {
            \Log::error('Error processing tenant leave: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'exception' => $e
            ]);
            return back()->with('error', 'Error processing leave: ' . $e->getMessage());
        }
    }
}

