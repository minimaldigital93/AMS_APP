<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Concerns\ScopesToSupervisorProperties;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\AssignTenantRequest;
use App\Models\Apartments;
use App\Models\FiscalPeriods;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Services\Tenants\AssignTenantException;
use App\Services\Tenants\TenantAssignmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApartmentController extends Controller
{
    use ScopesToSupervisorProperties;

    /**
     * Display all apartments grouped by floor.
     */
    public function index(Request $request): View
    {
        $query = $this->supervisorVisibleApartments()
            ->forActiveProperty()
            ->with(['floor', 'tenants', 'supervisor']);

        if ($request->filled('search')) {
            $query->where('apartment_number', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $apartments = $query->get();

        $apartmentsByFloor = $apartments->filter(fn ($apt) => $apt->floor !== null)
            ->groupBy(fn ($apt) => $apt->floor->id)
            ->sortBy(fn ($group) => $group->first()->floor->id);

        $floors = $this->supervisorVisibleFloors()
            ->forActiveProperty()
            ->whereHas('apartments')
            ->orderBy('id', 'asc')->get();

        $statuses = Apartments::getStatuses();

        $availableTenants = Tenants::where('status', 'active')->whereNull('apartment_id')->get();

        return view('supervisor.apartments.index', compact('apartmentsByFloor', 'floors', 'statuses', 'apartments', 'availableTenants'));
    }

    /**
     * 3D visualization of all floors and their apartments,
     * highlighting available vs occupied units. Mirrors the admin view.
     */
    public function plan3d(): View
    {
        $floors = $this->supervisorVisibleFloors()->forActiveProperty()->with(['apartments' => function ($query) {
            $query->orderBy('apartment_number')
                ->with([
                    'tenants' => fn ($q) => $q->whereNull('archived_at'),
                    'rentals' => fn ($q) => $q->active()->latest('start_date'),
                ]);
        }])->orderBy('id')->get();

        $floorsData = $floors->map(function ($floor) {
            return [
                'id' => $floor->id,
                'name' => $floor->floor_name,
                'apartments' => $floor->apartments->map(function ($apt) {
                    $tenant = $apt->tenants->first();
                    $stay = $apt->rentals->first()?->stayProgress() ?? [];

                    return [
                        'id' => $apt->id,
                        'number' => $apt->apartment_number,
                        'status' => $apt->status,
                        'rent' => (float) $apt->monthly_rent,
                        'tenant' => $tenant?->name,
                        'tenant_id' => $tenant?->id,
                        'stay_label' => $stay['stay_label'] ?? null,
                        'cycle_percent' => $stay['cycle_percent'] ?? null,
                        'days_left' => $stay['days_left'] ?? null,
                        'next_renewal_label' => $stay['next_renewal_label'] ?? null,
                    ];
                })->values(),
            ];
        })->values();

        $summary = [
            'floors' => $floors->count(),
            'total' => $floors->sum(fn ($f) => $f->apartments->count()),
            'available' => $floors->sum(fn ($f) => $f->apartments->where('status', 'available')->count()),
            'occupied' => $floors->sum(fn ($f) => $f->apartments->where('status', 'occupied')->count()),
        ];

        $availableTenants = Tenants::where('status', 'active')->whereNull('apartment_id')->get();

        return view('shared.apartments.plan3d', compact('floorsData', 'summary', 'availableTenants') + ['panel' => 'supervisor']);
    }

    /**
     * Show apartment details with rent payment progress.
     */
    public function show(Apartments $apartment): View
    {
        $this->authorizeApartment($apartment);

        $apartment->load('floor', 'supervisor');

        $activePeriod = $this->activeAdminFiscalPeriod();

        $activeRental = Rentals::where('apartment_id', $apartment->id)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->with('tenant')
            ->latest('start_date')
            ->first();

        // Load the tenant's own relations for the embedded universal tenant view.
        if ($activeRental && $activeRental->tenant) {
            $activeRental->tenant->load(['apartment.floor', 'rentals.apartment', 'rentals.payments']);
        }

        return view('shared.apartments.show', compact('apartment', 'activeRental', 'activePeriod') + ['panel' => 'supervisor']);
    }

    /**
     * Assign tenant to an available apartment. AssignTenantRequest authorizes
     * the room (assigned properties only) before validating; the deposit books
     * into the ADMIN's open period — supervisor writes land in the admin's books.
     */
    public function assignTenant(AssignTenantRequest $request, Apartments $apartment, TenantAssignmentService $assigner)
    {
        $validated = $request->validated();

        // Only accept uploads when creating a new tenant — prevents accidental
        // overwrite of an existing tenant's photo/document via crafted requests.
        $isNewTenant = $validated['tenant_option'] === 'new';

        try {
            $assigner->assign(
                $apartment,
                $validated,
                $isNewTenant ? $request->file('attached_photo') : null,
                $isNewTenant ? $request->file('id_pdf') : null,
                $this->activeAdminFiscalPeriod(),
            );
        } catch (AssignTenantException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('messages.flash_assignment_failed'));
        }

        return redirect()->route('supervisor.apartments.index')
            ->with('success', __('messages.flash_tenant_assigned'));
    }

    private function activeAdminFiscalPeriod(): ?FiscalPeriods
    {
        return FiscalPeriods::where('status', 'open')
            ->whereHas('user', function ($q) {
                $q->role('admin');
            })
            ->orderBy('opening_date', 'desc')
            ->first();
    }

    /**
     * Supervisors may only manage rooms in their assigned properties (403 otherwise).
     * Admins/superadmins reaching this route are not property-scoped.
     */
    private function authorizeApartment(Apartments $apartment): void
    {
        $this->authorizeSupervisorApartment($apartment);
    }
}
