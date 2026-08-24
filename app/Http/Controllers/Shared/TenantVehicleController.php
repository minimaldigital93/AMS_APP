<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Concerns\ScopesToSupervisorProperties;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\StoreTenantVehicleRequest;
use App\Models\Tenants;
use App\Models\TenantVehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The single write path for tenant vehicles, shared by both panels — one
 * implementation, two thin subclasses that only pin the panel slug.
 *
 * Two pages post here and there is deliberately no second implementation for
 * the other: the tenant-detail "Vehicles & Parking" card, and the property-wide
 * vehicle management page (Shared\VehicleController), whose per-room forms are
 * rendered already bound to that room's sitting tenant. The page that submitted
 * is carried by `redirect_to` so each returns to itself.
 *
 * Writing a vehicle never touches the books: a priced vehicle only changes what
 * the tenant's monthly `parking` charge comes out to, and that charge is still
 * created by the normal bill run (MonthlyBillingService). Booked months are
 * therefore never restated by an edit here.
 */
abstract class TenantVehicleController extends Controller
{
    use ScopesToSupervisorProperties;

    /** Panel slug ('admin' or 'supervisor') — drives the redirect route. */
    abstract protected function panel(): string;

    public function store(StoreTenantVehicleRequest $request, Tenants $tenant): RedirectResponse
    {
        $this->authorizeTenant($tenant);
        $this->verifyRoom($request, $tenant);

        $validated = $request->validated();

        $tenant->vehicles()->create([
            'vehicle_type' => $validated['vehicle_type'],
            'vehicle_model' => $validated['vehicle_model'] ?? null,
            'plate_number' => $validated['plate_number'],
            'monthly_fee' => $validated['monthly_fee'] ?? 0,
        ]);

        return $this->back($request, $tenant, __('messages.flash_vehicle_added', [
            'plate' => $validated['plate_number'],
        ]));
    }

    /**
     * Correct a recorded vehicle in place — plate, model, type or fee.
     *
     * Like the card itself this only restates what the *next* bill run will
     * charge. A parking charge already raised for this month keeps the figure
     * it was billed at; the tenant page flags the difference (`parkingState`
     * 'mismatch') rather than silently restating booked or owed money.
     */
    public function update(StoreTenantVehicleRequest $request, Tenants $tenant, TenantVehicle $vehicle): RedirectResponse
    {
        $this->authorizeTenant($tenant);
        $this->authorizeVehicle($tenant, $vehicle);
        $this->verifyRoom($request, $tenant);

        $validated = $request->validated();

        $vehicle->update([
            'vehicle_type' => $validated['vehicle_type'],
            'vehicle_model' => $validated['vehicle_model'] ?? null,
            'plate_number' => $validated['plate_number'],
            'monthly_fee' => $validated['monthly_fee'] ?? 0,
        ]);

        return $this->back($request, $tenant, __('messages.flash_vehicle_updated', [
            'plate' => $validated['plate_number'],
        ]));
    }

    public function destroy(Request $request, Tenants $tenant, TenantVehicle $vehicle): RedirectResponse
    {
        $this->authorizeTenant($tenant);
        $this->authorizeVehicle($tenant, $vehicle);

        $plate = $vehicle->plate_number;
        $vehicle->delete();

        // Charges already billed for this vehicle stay put — they are settled
        // (or owed) money, not a description of today's vehicle list.
        return $this->back($request, $tenant, __('messages.flash_vehicle_removed', ['plate' => $plate]));
    }

    /**
     * Back to whichever page submitted — the vehicle management page when it
     * says so, the tenant detail page otherwise (the card's own default).
     */
    private function back(Request $request, Tenants $tenant, string $message): RedirectResponse
    {
        $route = $request->input('redirect_to') === 'vehicles'
            ? $this->panel().'.vehicles.index'
            : $this->panel().'.tenants.show';

        $params = $route === $this->panel().'.vehicles.index' ? [] : [$tenant->id];

        return redirect()->route($route, $params)->with('success', $message);
    }

    /** A vehicle is only ever managed through the tenant that owns it. */
    private function authorizeVehicle(Tenants $tenant, TenantVehicle $vehicle): void
    {
        if ($vehicle->tenant_id !== $tenant->id) {
            throw new AccessDeniedHttpException('This vehicle does not belong to that tenant.');
        }
    }

    /**
     * When the submitting page named the room it drew the form under, check the
     * tenant is still sitting in it.
     *
     * The vehicle management page renders one form per room, bound to the tenant
     * who occupied it when the page loaded. If that tenant has since moved room
     * or moved out, the form would silently file the vehicle against the wrong
     * room — so the room it was drawn under travels with the post and is
     * verified here. The tenant card posts no `apartment_id` and is unaffected.
     */
    private function verifyRoom(Request $request, Tenants $tenant): void
    {
        $expected = $request->integer('apartment_id') ?: null;

        if ($expected === null) {
            return;
        }

        if ((int) $tenant->apartment_id !== $expected) {
            throw ValidationException::withMessages([
                'apartment_id' => __('messages.validation_vehicle_room_changed', ['name' => $tenant->name]),
            ]);
        }
    }

    /**
     * Property-scoped supervisors may only manage tenants whose room is in one
     * of their assigned properties. Admins/superadmins are not scoped.
     */
    private function authorizeTenant(Tenants $tenant): void
    {
        if ($this->seesWholeAccount()) {
            return;
        }

        $propertyId = $tenant->apartment?->floor?->property_id;
        if ($propertyId === null || ! $this->supervisorPropertyIds()->contains($propertyId)) {
            throw new AccessDeniedHttpException('This tenant is not in one of your assigned properties.');
        }
    }
}
