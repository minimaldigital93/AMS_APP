<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Floors;
use App\Models\Property;
use App\Models\Tenants;
use App\Services\Property\PropertyContext;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FloorController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /**
     * The floors list, with each floor's rooms rendered inline underneath.
     */
    public function index(Request $request, PropertyContext $propertyContext): View
    {
        $query = Floors::query();
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

        // Rooms render inline under each floor, so eager-load everything the
        // room rows read or the view goes N+1 per room.
        $floors = $query->with(['property', 'apartments' => function ($query) {
            $query->with([
                'supervisor',
                'tenants' => fn ($q) => $q->whereNull('deleted_at'),
                'rentals.payments' => fn ($q) => $q->whereNotNull('paid_at'),
            ])->orderBy('apartment_number');
        }])->withCount('apartments')->get();

        // Natural sort by property then floor name ("Floor 2" before "Floor 10").
        // Free-text names force it into PHP, hence the manual paginator.
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

        // Summary cards count the whole filtered set, not the visible page —
        // the list is paginated in PHP above.
        $floorCount = $sortedFloors->count();
        $roomCount = $sortedFloors->sum('apartments_count');

        // Lightweight list of every floor (not just the current page) that powers
        // the universal "Edit floor" selector in the header.
        $allFloors = $sortedFloors->map(fn ($floor) => [
            'id' => $floor->id,
            'name' => $floor->floor_name,
            'property' => $floor->property?->name,
        ])->values();

        // Unassigned active tenants power the "Existing Tenant" tab of the shared
        // assign-tenant modal embedded on this page.
        $availableTenants = Tenants::where('status', 'active')->whereNull('apartment_id')->get();

        return view('admin.floors.index', compact('floors', 'showingAll', 'properties', 'selectedPropertyId', 'availableTenants', 'allFloors', 'floorCount', 'roomCount'));
    }

    /**
     * Show the form for creating a floor, with optional rooms in the same submit.
     */
    public function create(): View
    {
        // Floors are always added to the globally selected property (top-bar
        // selector) — there is no per-form property picker.
        $activeProperty = app(PropertyContext::class)->activeProperty();

        return view('admin.floors.create', compact('activeProperty'));
    }

    /**
     * Store a floor plus any rooms listed on the form, subject to the room cap.
     */
    public function store(Request $request): RedirectResponse
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
            'description' => 'nullable|string|max:65535',
            'apartments' => 'nullable|array',
            'apartments.*.apartment_number' => [
                'required', 'string', 'max:255', 'distinct',
            ],
            'apartments.*.monthly_rent' => 'nullable|numeric|min:0|max:99999999.99',
            'apartments.*.status' => 'nullable|in:available,occupied',
        ], [
            'floor_name.unique' => __('messages.validation_floor_name_taken'),
            'apartments.*.apartment_number.distinct' => __('messages.validation_apartment_number_taken_generic'),
        ]);

        $validated = convert_money_input($validated, ['apartments.*.monthly_rent']);

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

    /**
     * Show the form for editing a floor (and adding rooms to it).
     */
    public function edit(Floors $floor): View
    {
        $floor->load('apartments');
        $properties = Property::orderBy('name')->get();
        $allFloors = Floors::forActiveProperty()->with('property')->get()
            ->sortBy(
                fn ($f) => sprintf('%s|%020d', $f->property?->name ?? '~', $f->id),
                SORT_NATURAL | SORT_FLAG_CASE
            )
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->floor_name,
                'property' => $f->property?->name,
            ])->values();

        return view('admin.floors.edit', compact('floor', 'properties', 'allFloors'));
    }

    public function update(Request $request, Floors $floor): RedirectResponse
    {
        $action = $request->input('action', 'update_floor');

        // Add one room to an existing floor.
        if ($action === 'add_apartment') {
            $validated = $request->validate([
                'apartment_number' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('apartments', 'apartment_number')
                        ->where('floor_id', $floor->id)
                        ->whereNull('deleted_at'),
                ],
                'monthly_rent' => 'nullable|numeric|min:0|max:99999999.99',
            ], [
                'apartment_number.unique' => __('messages.validation_apartment_number_taken', ['number' => $request->input('apartment_number')]),
            ]);

            $validated = convert_money_input($validated, ['monthly_rent']);
            $accountId = current_account_id();
            if (! $this->subscriptions->canAddRooms($accountId)) {
                $plan = $this->subscriptions->activePlan($accountId);

                return redirect()->route('admin.floors.edit', $floor)
                    ->with('error', __('messages.flash_plan_limit_rooms', ['plan' => $plan?->name, 'max' => $plan?->max_rooms]));
            }

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
            'description' => 'nullable|string|max:65535',
        ], [
            'floor_name.unique' => __('messages.validation_floor_name_taken'),
        ]);

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

    /**
     * Soft-delete an empty floor.
     */
    public function destroy(Floors $floor): RedirectResponse
    {
        // A soft-deleted floor leaves its rooms pointing at an invisible parent
        // ($apartment->floor === null), so require it be empty first.
        if ($floor->apartments()->exists()) {
            return back()->with('error', __('messages.flash_floor_has_apartments'));
        }

        $floor->delete();

        return redirect()->route('admin.floors.index')->with('success', __('messages.flash_floor_deleted'));
    }

    public function plan3d(): View
    {
        $floors = Floors::forActiveProperty()->with(['apartments' => function ($query) {
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
                    // A moved-out tenant's rental still matches active(), so gate
                    // on tenant + occupied or a freed unit keeps its progress gauge.
                    $stay = ($tenant && $apt->status === 'occupied')
                        ? ($apt->rentals->first()?->stayProgress() ?? [])
                        : [];

                    return [
                        'id' => $apt->id,
                        'number' => $apt->apartment_number,
                        'status' => $apt->status,
                        'under_maintenance' => (bool) $apt->under_maintenance,
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

        // Maintenance units are reported on their own, not folded into
        // available/total, so occupancy counts only rentable stock.
        $summary = [
            'floors' => $floors->count(),
            'total' => $floors->sum(fn ($f) => $f->apartments->where('under_maintenance', false)->count()),
            'available' => $floors->sum(fn ($f) => $f->apartments->where('under_maintenance', false)->where('status', 'available')->count()),
            'occupied' => $floors->sum(fn ($f) => $f->apartments->where('under_maintenance', false)->where('status', 'occupied')->count()),
            'maintenance' => $floors->sum(fn ($f) => $f->apartments->where('under_maintenance', true)->count()),
        ];

        // Feeds the "Existing Tenant" tab of the assign-tenant modal.
        $availableTenants = Tenants::where('status', 'active')->whereNull('apartment_id')->get();

        return view('shared.apartments.plan3d', compact('floorsData', 'summary', 'availableTenants') + ['panel' => 'admin']);
    }
}
