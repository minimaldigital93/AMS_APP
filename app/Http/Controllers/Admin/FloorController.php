<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Floors;
use App\Models\Property;
use App\Models\Tenants;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FloorController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(Request $request, \App\Services\Property\PropertyContext $propertyContext): View
    {
        $query = Floors::query();

        // When the top-bar is on a single property, that already scopes the list.
        // When it's on "All properties", offer a per-page property filter so the
        // user can narrow to one building without changing the global selection.
        $showingAll = $propertyContext->showingAllProperties();
        $properties = collect();
        $selectedPropertyId = null;

        if ($showingAll) {
            $properties = $propertyContext->accessibleProperties();
            $requested = $request->integer('property') ?: null;

            if ($requested !== null && $properties->contains('id', $requested)) {
                $selectedPropertyId = $requested;
                $query->forProperty($requested);
            }
        } else {
            $query->forActiveProperty();
        }

        // Search functionality — keep the OR group nested so it can't escape the
        // property scope above.
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('floor_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Rooms are now listed inline under each floor (the merged "Floors And
        // Rooms" page), so eager-load the tenants/supervisor the room table needs.
        $floors = $query->with(['property', 'apartments' => function ($query) {
            $query->with(['supervisor', 'tenants' => fn ($q) => $q->whereNull('deleted_at')])
                ->orderBy('apartment_number');
        }])->withCount('apartments')->get();

        // Natural sort (Floor 1, Floor 2, ... Floor 10) rather than alphabetical
        // (which would put "Floor 10" before "Floor 2") — grouped by property
        // first so "All properties" lists floors building by building instead of
        // interleaving them in creation order. Free-text floor names mean this
        // has to happen in PHP (strnatcasecmp), not the DB, so we paginate the
        // sorted collection manually instead of Floors::paginate().
        $sortedFloors = $floors->sort(function ($a, $b) {
            $propertyOrder = strnatcasecmp($a->property?->name ?? '', $b->property?->name ?? '');

            return $propertyOrder !== 0 ? $propertyOrder : strnatcasecmp($a->floor_name, $b->floor_name);
        })->values();

        $perPage = 10;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $floors = new LengthAwarePaginator(
            $sortedFloors->forPage($page, $perPage)->values(),
            $sortedFloors->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        // Unassigned active tenants power the "Existing Tenant" tab of the shared
        // assign-tenant modal embedded on this page.
        $availableTenants = Tenants::where('status', 'active')->whereNull('apartment_id')->get();

        return view('admin.floors.index', compact('floors', 'showingAll', 'properties', 'selectedPropertyId', 'availableTenants'));
    }

    public function create(): View
    {
        // Floors are always added to the globally selected property (top-bar
        // selector) — there is no per-form property picker.
        $activeProperty = app(\App\Services\Property\PropertyContext::class)->activeProperty();

        return view('admin.floors.create', compact('activeProperty'));
    }

    /**
     * 3D visualization of all floors and their apartments,
     * highlighting available vs occupied units.
     */
    public function plan3d(): View
    {
        $floors = Floors::forActiveProperty()->with(['apartments' => function ($query) {
            $query->orderBy('apartment_number')
                ->with([
                    'tenants' => fn ($q) => $q->whereNull('archived_at'),
                    'rentals' => fn ($q) => $q->active()->latest('start_date'),
                ]);
        }])->orderBy('id')->get();

        // Shape data for the renderer.
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

        // Unassigned active tenants for the "Existing Tenant" tab of the assign-tenant modal
        $availableTenants = Tenants::where('status', 'active')->whereNull('apartment_id')->get();

        return view('admin.floors.plan3d', compact('floorsData', 'summary', 'availableTenants'));
    }

    public function edit(Floors $floor): View
    {
        $floor->load('apartments');
        $properties = Property::orderBy('name')->get();

        return view('admin.floors.edit', compact('floor', 'properties'));
    }

    public function store(Request $request)
    {
        // The floor always belongs to the globally selected property; resolve it
        // server-side rather than trusting a form field.
        $propertyId = current_property_id();

        if ($propertyId === null) {
            return back()->withInput()->with('error', __('messages.no_properties_yet'));
        }

        $validated = $request->validate([
            'floor_name' => [
                'required', 'string', 'max:255',
                // Floor names are unique within their property, not across the whole
                // account — two properties may each have a "Floor 1".
                Rule::unique('floors', 'floor_name')
                    ->where('property_id', $propertyId)
                    ->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string',
            'apartments' => 'nullable|array',
            'apartments.*.apartment_number' => [
                'required', 'string', 'max:255', 'distinct',
                // Uniqueness is per-floor (matching the DB index on
                // floor_id + apartment_number). This is a brand-new floor, so no
                // existing room can belong to it yet — `distinct` is what stops the
                // batch from listing the same number twice. A unit "101" may still
                // exist on other floors of this property (and in other properties).
            ],
            'apartments.*.monthly_rent' => 'nullable|numeric|min:0',
            'apartments.*.status' => 'nullable|in:available,occupied',
        ], [
            'floor_name.unique' => __('messages.validation_floor_name_taken'),
            'apartments.*.apartment_number.distinct' => __('messages.validation_apartment_number_taken_generic'),
        ]);
        $validated = convert_money_input($validated, ['monthly_rent', 'deposit', 'apartments.*.monthly_rent']);

        // Floors are unlimited on every plan; only the room cap applies here.
        $accountId = current_account_id();
        $newRooms = count($validated['apartments'] ?? []);

        if ($newRooms > 0 && ! $this->subscriptions->canAddRooms($accountId, $newRooms)) {
            $plan = $this->subscriptions->activePlan($accountId);

            return back()->withInput()->with('error', __('messages.flash_plan_limit_apartments_floor', ['plan' => $plan?->name, 'max' => $plan?->max_rooms]));
        }

        $floor = Floors::create([
            'property_id' => $propertyId,
            'floor_name' => $validated['floor_name'],
            'description' => $validated['description'] ?? null,
        ]);

        $apartmentsCreated = 0;
        foreach ($validated['apartments'] ?? [] as $apt) {
            try {
                $floor->apartments()->create([
                    'apartment_number' => $apt['apartment_number'],
                    'monthly_rent' => $apt['monthly_rent'] ?? 0,
                    'status' => $apt['status'] ?? 'available',
                ]);
                $apartmentsCreated++;
            } catch (\Exception $e) {
                Log::error('Error creating apartment for floor '.$floor->id.': '.$e->getMessage());
            }
        }

        $message = $apartmentsCreated > 0
            ? __('messages.flash_floor_created_with_units', ['count' => $apartmentsCreated])
            : __('messages.flash_floor_created');

        return redirect()->route('admin.floors.index')->with('success', $message);
    }

    public function update(Request $request, Floors $floor)
    {
        $action = $request->input('action', 'update_floor');

        // ACTION: Add New Apartment to Existing Floor
        if ($action === 'add_apartment') {
            $validated = $request->validate([
                'apartment_number' => [
                    'required',
                    'string',
                    'max:255',
                    // Per-floor uniqueness (matching the DB index): the same unit
                    // number may live on other floors of this property, just not
                    // twice on this one.
                    Rule::unique('apartments', 'apartment_number')
                        ->where('floor_id', $floor->id)
                        ->whereNull('deleted_at'),
                ],
                'monthly_rent' => 'nullable|numeric|min:0',
            ], [
                'apartment_number.unique' => __('messages.validation_apartment_number_taken', ['number' => $request->input('apartment_number')]),
            ]);
            $validated = convert_money_input($validated, ['monthly_rent', 'deposit', 'apartments.*.monthly_rent']);

            // Enforce the account's subscription plan room cap.
            $accountId = current_account_id();
            if (! $this->subscriptions->canAddRooms($accountId)) {
                $plan = $this->subscriptions->activePlan($accountId);

                return redirect()->route('admin.floors.edit', $floor)
                    ->with('error', __('messages.flash_plan_limit_rooms', ['plan' => $plan?->name, 'max' => $plan?->max_rooms]));
            }

            // Create the apartment
            try {
                $floor->apartments()->create([
                    'apartment_number' => $validated['apartment_number'],
                    'monthly_rent' => $validated['monthly_rent'] ?? 0,
                    'status' => 'available',
                ]);

                return redirect()->route('admin.floors.edit', $floor)
                    ->with('success', __('messages.flash_unit_added'));
            } catch (\Exception $e) {
                Log::error('Error creating apartment for floor '.$floor->id.': '.$e->getMessage());

                return redirect()->route('admin.floors.edit', $floor)
                    ->withErrors(['apartment_number' => 'Error adding apartment']);
            }
        }

        // ACTION: Update Floor Information
        $validated = $request->validate([
            'property_id' => [
                'required',
                Rule::exists('properties', 'id')->where('account_id', current_account_id()),
            ],
            'floor_name' => [
                'required', 'string', 'max:255',
                // Unique within the (possibly newly chosen) property, ignoring itself.
                Rule::unique('floors', 'floor_name')
                    ->ignore($floor->id)
                    ->where('property_id', $request->input('property_id'))
                    ->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string',
        ], [
            'floor_name.unique' => __('messages.validation_floor_name_taken'),
        ]);
        $validated = convert_money_input($validated, ['monthly_rent', 'deposit', 'apartments.*.monthly_rent']);

        try {
            $floor->update([
                'property_id' => $validated['property_id'],
                'floor_name' => $validated['floor_name'],
                'description' => $validated['description'] ?? null,
            ]);

            return redirect()
                ->route('admin.floors.index')
                ->with('success', __('messages.flash_floor_updated'));
        } catch (\Exception $e) {
            Log::error('Error updating floor '.$floor->id.': '.$e->getMessage());

            return redirect()
                ->route('admin.floors.edit', $floor)
                ->withErrors(['error' => 'Error updating floor']);
        }
    }

    public function destroy(Floors $floor)
    {
        // Don't orphan rooms — a soft-deleted floor leaves its apartments pointing
        // at an invisible floor ($apartment->floor === null). Require it be empty
        // first (apartments() already excludes soft-deleted rooms).
        if ($floor->apartments()->exists()) {
            return back()->with('error', __('messages.flash_floor_has_apartments'));
        }

        $floor->delete();

        return redirect()->route('admin.floors.index')->with('success', __('messages.flash_floor_deleted'));
    }
}
