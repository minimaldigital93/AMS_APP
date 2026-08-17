<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Concerns\ScopesToSupervisorProperties;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\StoreTenantVehicleRequest;
use App\Models\Tenants;
use App\Models\TenantVehicle;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The tenant-detail "Vehicles & Parking" card, shared by both panels — one
 * implementation, two thin subclasses that only pin the panel slug.
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

        $validated = $request->validated();

        $tenant->vehicles()->create([
            'vehicle_type' => $validated['vehicle_type'],
            'vehicle_model' => $validated['vehicle_model'] ?? null,
            'plate_number' => $validated['plate_number'],
            'monthly_fee' => $validated['monthly_fee'] ?? 0,
        ]);

        return $this->backToTenant($tenant, __('messages.flash_vehicle_added', [
            'plate' => $validated['plate_number'],
        ]));
    }

    public function destroy(Tenants $tenant, TenantVehicle $vehicle): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        if ($vehicle->tenant_id !== $tenant->id) {
            throw new AccessDeniedHttpException('This vehicle does not belong to that tenant.');
        }

        $plate = $vehicle->plate_number;
        $vehicle->delete();

        // Charges already billed for this vehicle stay put — they are settled
        // (or owed) money, not a description of today's vehicle list.
        return $this->backToTenant($tenant, __('messages.flash_vehicle_removed', ['plate' => $plate]));
    }

    private function backToTenant(Tenants $tenant, string $message): RedirectResponse
    {
        return redirect()
            ->route($this->panel().'.tenants.show', $tenant->id)
            ->with('success', $message);
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
