<?php

namespace App\Services\Property;

use App\Models\Apartments;
use App\Models\Property;
use App\Models\Tenants;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The single source of truth for the "active property" — the global property
 * context that filters every property-related module to one building at a time.
 *
 * Mirrors the account context (current_account_id()): a request-scoped singleton
 * (bound in AppServiceProvider) whose lookups are memoized so the active property,
 * the accessible list, and the active property's apartment IDs are each resolved
 * at most once per request.
 *
 * The Fiscal Period is deliberately NOT touched here — property and period are
 * independent global contexts.
 */
class PropertyContext
{
    /** Session key holding the user's current selection. */
    public const SESSION_KEY = 'active_property_id';

    private ?Collection $accessibleCache = null;

    private Property|null|false $activeCache = false;

    private ?array $apartmentIdsCache = null;

    /**
     * Properties the current user is authorized to view, newest-name-first.
     *
     * - admin/superadmin → every property in their account (BelongsToAccount
     *   already isolates the query to current_account_id()).
     * - supervisor → only the properties assigned to them (properties.supervisor_id).
     * - tenant → the property their apartment lives under.
     * - unauthenticated → empty.
     */
    public function accessibleProperties(): Collection
    {
        if ($this->accessibleCache !== null) {
            return $this->accessibleCache;
        }

        $user = Auth::user();

        if ($user === null) {
            return $this->accessibleCache = collect();
        }

        if ($user->hasRole('admin') || $user->hasRole('superadmin')) {
            return $this->accessibleCache = Property::orderBy('name')->get();
        }

        if ($user->hasRole('supervisor')) {
            return $this->accessibleCache = Property::where('supervisor_id', $user->getKey())
                ->orderBy('name')
                ->get();
        }

        // Tenant: the property/properties their apartment(s) belong to.
        $apartmentIds = Tenants::where('user_id', $user->getKey())->pluck('apartment_id')->filter();

        if ($apartmentIds->isEmpty()) {
            return $this->accessibleCache = collect();
        }

        return $this->accessibleCache = Property::whereHas(
            'floors',
            fn ($q) => $q->whereHas('apartments', fn ($a) => $a->whereIn('id', $apartmentIds))
        )->orderBy('name')->get();
    }

    /** IDs of the accessible properties (for fast membership checks). */
    public function accessiblePropertyIds(): Collection
    {
        return $this->accessibleProperties()->pluck('id');
    }

    /**
     * The active property for the current request, or null when the user has
     * none. Resolution order: validated session selection → the user's last
     * remembered property → the first accessible property. The resolved id is
     * written back to the session so it survives subsequent requests.
     */
    public function activeProperty(): ?Property
    {
        if ($this->activeCache !== false) {
            return $this->activeCache;
        }

        $accessible = $this->accessibleProperties();

        if ($accessible->isEmpty()) {
            return $this->activeCache = null;
        }

        // 1) An explicit, still-valid session selection.
        $sessionId = session(self::SESSION_KEY);
        if ($sessionId && ($match = $accessible->firstWhere('id', (int) $sessionId))) {
            return $this->activeCache = $match;
        }

        // 2) The user's last remembered property (restores after a fresh login).
        $lastId = Auth::user()?->last_property_id;
        if ($lastId && ($match = $accessible->firstWhere('id', (int) $lastId))) {
            session([self::SESSION_KEY => $match->id]);

            return $this->activeCache = $match;
        }

        // 3) Default to the first accessible property.
        $first = $accessible->first();
        session([self::SESSION_KEY => $first->id]);

        return $this->activeCache = $first;
    }

    public function activePropertyId(): ?int
    {
        return $this->activeProperty()?->id;
    }

    /**
     * Switch the active property. Server-side authorization gate: an id outside
     * the user's accessible set is rejected, so tampering with the request/URL/
     * session cannot surface another property's (or account's) data.
     */
    public function setActiveProperty(int $propertyId): Property
    {
        $property = $this->accessibleProperties()->firstWhere('id', $propertyId);

        if ($property === null) {
            throw new AccessDeniedHttpException('You do not have access to this property.');
        }

        session([self::SESSION_KEY => $property->id]);
        $this->rememberForUser($property->id);

        // Invalidate per-request caches so the rest of this request sees the switch.
        $this->activeCache = $property;
        $this->apartmentIdsCache = null;

        return $property;
    }

    /** Apartment IDs under the active property — feeds dashboards and chain filters. */
    public function apartmentIdsForActiveProperty(): array
    {
        if ($this->apartmentIdsCache !== null) {
            return $this->apartmentIdsCache;
        }

        $propertyId = $this->activePropertyId();

        if ($propertyId === null) {
            return $this->apartmentIdsCache = [];
        }

        return $this->apartmentIdsCache = Apartments::whereHas(
            'floor',
            fn ($q) => $q->where('property_id', $propertyId)
        )->pluck('id')->all();
    }

    public function hasSingleProperty(): bool
    {
        return $this->accessibleProperties()->count() === 1;
    }

    /** Whether to render an interactive selector (more than one choice). */
    public function selectorEnabled(): bool
    {
        return $this->accessibleProperties()->count() > 1;
    }

    /** Persist the selection on the user row so it restores after re-login. */
    private function rememberForUser(int $propertyId): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        $user->last_property_id = $propertyId;
        $user->saveQuietly();
    }
}
