<?php

namespace App\Policies;

use App\Models\Rentals;
use App\Models\User;

/**
 * Authorization for lease/rental actions.
 *
 * Contract generation, viewing, printing, downloading and regeneration are
 * restricted to account owners and the platform owner (admin / superadmin).
 * Supervisors and tenants cannot manage contracts.
 *
 * Account isolation is already enforced by route-model binding (the Rentals
 * `account` global scope 404s a lease from another account); this policy adds
 * the role gate on top.
 */
class RentalsPolicy
{
    public function manageContract(User $user, Rentals $rental): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin']);
    }
}
