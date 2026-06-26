<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Floors;
use App\Models\Property;
use App\Models\Tenants;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FloorController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        // Scope to the globally selected property (top-bar selector).
        $query = Floors::query()->forActiveProperty();

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
        $properties = Property::orderBy('name')->get();

        return view('admin.floors.create', compact('properties'));
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

    public function getApartments(Floors $floor): View
    {
        $apartments = $floor->apartments()->paginate(10);

        return view('admin.apartments.index', compact('floor', 'apartments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_id' => [
                'required',
                Rule::exists('properties', 'id')->where('account_id', current_account_id()),
            ],
            'floor_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'apartments' => 'nullable|array',
            'apartments.*.apartment_number' => [
                'required', 'string', 'max:255',
                // Scope uniqueness to the current account so each admin's units are
                // independent — number "101" in one account must not clash with another.
                Rule::unique('apartments', 'apartment_number')
                    ->where('account_id', current_account_id())
                    ->whereNull('deleted_at'),
            ],
            'apartments.*.monthly_rent' => 'nullable|numeric|min:0',
            'apartments.*.status' => 'nullable|in:available,occupied',
        ], [
            'apartments.*.apartment_number.unique' => __('messages.validation_apartment_number_taken_generic'),
        ]);

        // Floors are unlimited on every plan; only the room cap applies here.
        $accountId = current_account_id();
        $newRooms = count($validated['apartments'] ?? []);

        if ($newRooms > 0 && ! $this->subscriptions->canAddRooms($accountId, $newRooms)) {
            $plan = $this->subscriptions->activePlan($accountId);

            return back()->withInput()->with('error', __('messages.flash_plan_limit_apartments_floor', ['plan' => $plan?->name, 'max' => $plan?->max_rooms]));
        }

        $floor = Floors::create([
            'property_id' => $validated['property_id'],
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
                    Rule::unique('apartments', 'apartment_number')
                        ->where('account_id', current_account_id())
                        ->whereNull('deleted_at'),
                ],
                'monthly_rent' => 'nullable|numeric|min:0',
            ], [
                'apartment_number.unique' => __('messages.validation_apartment_number_taken', ['number' => $request->input('apartment_number')]),
            ]);

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
            'floor_name' => 'required|string|max:255',
            'description' => 'nullable|string',
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

    public function destroy(Floors $floor)
    {
        $floor->delete();

        return redirect()->route('admin.floors.index')->with('success', __('messages.flash_floor_deleted'));
    }
}
