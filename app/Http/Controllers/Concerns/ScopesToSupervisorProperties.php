<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Supervisor visibility is scoped to the properties they are assigned to
 * (properties.supervisor_id). A supervisor only sees floors/rooms/tenants that
 * live under one of their properties.
 *
 * Admins/superadmins that reach a supervisor route (the supervisor routes allow
 * admin|superadmin) are NOT property-scoped — they see the whole account, since
 * the account-level global scope already isolates them correctly.
 */
trait ScopesToSupervisorProperties
{
    /** True when the actor should see everything in the account (admin/superadmin). */
    private function seesWholeAccount(): bool
    {
        $user = Auth::user();

        return $user !== null && ($user->hasRole('admin') || $user->hasRole('superadmin'));
    }

    /** IDs of the properties assigned to the current supervisor. */
    protected function supervisorPropertyIds(): Collection
    {
        return Property::where('supervisor_id', Auth::id())->pluck('id');
    }

    /** Apartments query limited to the supervisor's assigned properties. */
    protected function supervisorVisibleApartments(): Builder
    {
        if ($this->seesWholeAccount()) {
            return Apartments::query();
        }

        $propertyIds = $this->supervisorPropertyIds();

        return Apartments::query()
            ->whereHas('floor', fn (Builder $q) => $q->whereIn('property_id', $propertyIds));
    }

    /** Floors query limited to the supervisor's assigned properties. */
    protected function supervisorVisibleFloors(): Builder
    {
        if ($this->seesWholeAccount()) {
            return Floors::query();
        }

        return Floors::query()->whereIn('property_id', $this->supervisorPropertyIds());
    }

    /** @return array<int> apartment IDs visible to the current supervisor. */
    protected function supervisorApartmentIds(): array
    {
        return $this->supervisorVisibleApartments()->pluck('id')->all();
    }

    /** Whether the current supervisor may act on a specific apartment. */
    protected function supervisorCanAccessApartment(Apartments $apartment): bool
    {
        if ($this->seesWholeAccount()) {
            return true;
        }

        $propertyId = $apartment->floor?->property_id;

        return $propertyId !== null && $this->supervisorPropertyIds()->contains($propertyId);
    }

    /** Abort with 403 when the supervisor may not access the apartment. */
    protected function authorizeSupervisorApartment(Apartments $apartment): void
    {
        if (! $this->supervisorCanAccessApartment($apartment)) {
            throw new AccessDeniedHttpException('This room is not in one of your assigned properties.');
        }
    }
}
