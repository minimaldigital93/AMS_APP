<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\AssignTenantRequest;
use App\Models\Apartments;
use App\Models\FiscalPeriods;
use App\Models\Floors;
use App\Models\Rentals;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenants\AssignTenantException;
use App\Services\Tenants\LeaseSyncService;
use App\Services\Tenants\TenantAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApartmentController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function create(): View
    {
        $floors = Floors::all();
        $supervisors = User::role('supervisor')->get();
        $statuses = Apartments::getStatuses();

        return view('admin.apartments.create', compact('floors', 'supervisors', 'statuses'));
    }

    public function show(Apartments $apartment): View
    {
        $apartment = $apartment->load('floor', 'supervisor');

        // Get the active rental for this apartment
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

        return view('shared.apartments.show', compact('apartment', 'activeRental') + ['panel' => 'admin']);
    }

    public function edit(Apartments $apartment): View
    {
        $apartment = $apartment->load('floor', 'supervisor');
        $floors = Floors::all();
        $supervisors = User::role('supervisor')->get();
        $statuses = Apartments::getStatuses();

        return view('admin.apartments.edit', compact('apartment', 'floors', 'supervisors', 'statuses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'apartment_number' => [
                'required', 'string', 'max:255',
                // Per-floor uniqueness: a unit "101" may exist on more than one
                // floor of the same building (and across properties).
                Rule::unique('apartments', 'apartment_number')
                    ->where('floor_id', $request->input('floor_id'))
                    ->whereNull('deleted_at'),
            ],
            'floor_id' => 'required|exists:floors,id',
            'monthly_rent' => 'required|numeric|min:0|max:99999999.99',
            'status' => Apartments::getStatusValidationRule(),
            'supervisor_id' => [
                'nullable',
                // Same-account supervisors only — the bare exists:users,id let a
                // crafted request stamp another account's user (even an admin)
                // onto the apartment (2026-07 validation audit).
                Rule::exists('users', 'id')->where('account_id', current_account_id()),
            ],
            'description' => 'nullable|string|max:65535',
        ], [
            'apartment_number.unique' => __('messages.validation_apartment_number_taken', ['number' => $request->input('apartment_number')]),
        ]);
        $validated = convert_money_input($validated, ['monthly_rent', 'deposit', 'apartments.*.monthly_rent']);

        // Enforce the account's subscription plan room cap.
        $accountId = current_account_id();
        if (! $this->subscriptions->canAddRooms($accountId)) {
            $plan = $this->subscriptions->activePlan($accountId);

            return back()->withInput()->with('error', __('messages.flash_plan_limit_rooms', ['plan' => $plan?->name, 'max' => $plan?->max_rooms]));
        }

        Apartments::create($validated);

        return redirect()->route('admin.floors.index')->with('success', __('messages.flash_apartment_created'));
    }

    public function update(Request $request, Apartments $apartment, LeaseSyncService $leases)
    {
        $validated = $request->validate([
            'apartment_number' => [
                'required', 'string', 'max:255',
                // Per-floor uniqueness, ignoring this apartment's own row. The edit
                // form can't move an apartment between floors, so the floor is fixed
                // to this apartment's existing floor_id.
                Rule::unique('apartments', 'apartment_number')
                    ->ignore($apartment->id)
                    ->where('floor_id', $apartment->floor_id)
                    ->whereNull('deleted_at'),
            ],
            'monthly_rent' => 'required|numeric|min:0|max:99999999.99',
            'status' => Apartments::getStatusValidationRule(),
            'under_maintenance' => 'nullable|boolean',
            'supervisor_id' => [
                'nullable',
                // Same-account supervisors only — the bare exists:users,id let a
                // crafted request stamp another account's user (even an admin)
                // onto the apartment (2026-07 validation audit).
                Rule::exists('users', 'id')->where('account_id', current_account_id()),
            ],
            'description' => 'nullable|string|max:65535',
        ], [
            'apartment_number.unique' => __('messages.validation_apartment_number_taken', ['number' => $request->input('apartment_number')]),
        ]);
        $validated = convert_money_input($validated, ['monthly_rent', 'deposit', 'apartments.*.monthly_rent']);

        // Maintenance mode takes the unit out of the rentable stock, so it must
        // be empty first — otherwise a living tenant's rent would silently drop
        // out of every occupancy/expected-revenue figure while they still owe it.
        // Move the tenant out (or to another room) before switching this on.
        $wantsMaintenance = (bool) ($validated['under_maintenance'] ?? false);
        if ($wantsMaintenance && ! $apartment->under_maintenance && $apartment->isCurrentlyOccupied()) {
            return back()->withInput()->with('error', __('messages.flash_maintenance_blocked_occupied'));
        }

        // The room price and the sitting tenant's rent are two different
        // columns: `apartments.monthly_rent` is the asking price, while every
        // bill is derived from the lease's own `rent_amount`. Repricing an
        // occupied room has to move both, or the rent-collection page lists the
        // new price and charges the old one.
        $newRent = (float) $validated['monthly_rent'];
        $repriced = 0;

        DB::transaction(function () use ($apartment, $validated, $leases, $newRent, &$repriced) {
            $apartment->update($validated);
            $repriced = $leases->repriceActiveLeases($apartment, $newRent);
        });

        $message = $repriced > 0
            ? __('messages.flash_apartment_updated_rent_synced', ['rent' => money($newRent)])
            : __('messages.flash_apartment_updated');

        return redirect()->route('admin.floors.index')->with('success', $message);
    }

    /**
     * Instant-save switch for maintenance mode (its own route so the toggle on
     * the edit page saves on click and confirms, rather than silently riding
     * along on the main "Update Room" submit).
     */
    public function toggleMaintenance(Request $request, Apartments $apartment)
    {
        $validated = $request->validate([
            'under_maintenance' => 'required|boolean',
        ]);

        $wantsMaintenance = (bool) $validated['under_maintenance'];

        // Double submit / stale page — nothing to do, and no flash to avoid
        // telling the user something changed when it didn't.
        if ($wantsMaintenance === (bool) $apartment->under_maintenance) {
            return back();
        }

        // Same guard as update(): the unit must be empty before it leaves the
        // rentable stock, or a living tenant's rent would drop out of every
        // occupancy/expected-revenue figure while they still owe it.
        if ($wantsMaintenance && $apartment->isCurrentlyOccupied()) {
            return back()->with('error', __('messages.flash_maintenance_blocked_occupied'));
        }

        $apartment->update(['under_maintenance' => $wantsMaintenance]);

        $message = $wantsMaintenance
            ? __('messages.flash_maintenance_enabled', ['number' => $apartment->apartment_number])
            : __('messages.flash_maintenance_disabled', ['number' => $apartment->apartment_number]);

        return back()->with('success', $message);
    }

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
                $this->activeFiscalPeriod(),
            );
        } catch (AssignTenantException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('messages.flash_assignment_failed'));
        }

        return redirect()->route('admin.floors.index')->with('success', __('messages.flash_tenant_assigned'));
    }

    private function activeFiscalPeriod(): ?FiscalPeriods
    {
        return FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'open')
            ->orderBy('opening_date', 'desc')
            ->first();
    }

    public function destroy(Apartments $apartment)
    {
        // Block deletion while a tenant still lives here. Soft-delete does not
        // cascade to rentals/tenants, so removing an occupied unit would orphan
        // the live rental ($rental->apartment === null) and break ledger writes.
        if ($apartment->isCurrentlyOccupied()) {
            return back()->with('error', __('messages.flash_apartment_has_active_tenant'));
        }

        $apartment->delete();

        // Check if request came from floor edit page
        $referrer = request()->headers->get('referer');
        if ($referrer && str_contains($referrer, '/admin/floors/') && str_contains($referrer, '/edit')) {
            return back()->with('success', __('messages.flash_apartment_deleted'));
        }

        return redirect()->route('admin.floors.index')->with('success', __('messages.flash_apartment_deleted'));
    }
}
