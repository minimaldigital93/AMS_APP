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

    /**
     * Sentinel selection meaning "All properties" — a consolidated view across
     * every accessible property rather than one building. It resolves to a null
     * active property id, which the FiltersByProperty scope and the dashboard
     * services already treat as "don't narrow to a single property".
     */
    public const ALL_PROPERTIES = 0;

    private ?Collection $accessibleCache = null;

    private Property|null|false $activeCache = false;

    private ?bool $showingAllCache = null;

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
     * The active property for the current request, or null when the user is
     * viewing "All properties" (the consolidated view) or has none accessible.
     * Use showingAllProperties() to tell those two null cases apart.
     */
    public function activeProperty(): ?Property
    {
        $this->resolve();

        return $this->activeCache === false ? null : $this->activeCache;
    }

    /**
     * Whether the user is viewing the consolidated "All properties" view (an
     * explicit choice, not the absence of any property). Only ever true when
     * there are 2+ accessible properties to combine.
     */
    public function showingAllProperties(): bool
    {
        $this->resolve();

        return $this->showingAllCache ?? false;
    }

    /**
     * Resolve (once per request) both the active property and whether we're in
     * the consolidated "All properties" view, memoizing the result.
     *
     * Resolution order: explicit session/remembered selection → first accessible
     * property. Either the session or the remembered value may be the
     * ALL_PROPERTIES sentinel, which selects the consolidated view (provided
     * there are 2+ properties to combine). The resolved choice is written back to
     * the session so it survives subsequent requests.
     */
    private function resolve(): void
    {
        if ($this->activeCache !== false) {
            return;
        }

        $accessible = $this->accessibleProperties();

        if ($accessible->isEmpty()) {
            $this->activeCache = null;
            $this->showingAllCache = false;

            return;
        }

        // The current selection: the session first, then the value remembered on
        // the user row (restores after a fresh login). Either may be ALL_PROPERTIES.
        $selection = session(self::SESSION_KEY);
        if ($selection === null) {
            $selection = Auth::user()?->last_property_id;
        }
        $selection = $selection === null ? null : (int) $selection;

        // "All properties" — only meaningful when there are 2+ to consolidate.
        if ($selection === self::ALL_PROPERTIES && $accessible->count() > 1) {
            session([self::SESSION_KEY => self::ALL_PROPERTIES]);
            $this->activeCache = null;
            $this->showingAllCache = true;

            return;
        }

        // A still-valid single-property selection.
        if ($selection && ($match = $accessible->firstWhere('id', $selection))) {
            session([self::SESSION_KEY => $match->id]);
            $this->activeCache = $match;
            $this->showingAllCache = false;

            return;
        }

        // Default to the first accessible property.
        $first = $accessible->first();
        session([self::SESSION_KEY => $first->id]);
        $this->activeCache = $first;
        $this->showingAllCache = false;
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
        $this->showingAllCache = false;
        $this->apartmentIdsCache = null;

        return $property;
    }

    /**
     * Switch to the consolidated "All properties" view. No-ops when there are
     * fewer than 2 accessible properties (with a single property "all" is
     * identical to that property, so there is nothing to consolidate).
     */
    public function setAllProperties(): void
    {
        if ($this->accessibleProperties()->count() < 2) {
            return;
        }

        session([self::SESSION_KEY => self::ALL_PROPERTIES]);
        $this->rememberForUser(self::ALL_PROPERTIES);

        // Invalidate per-request caches so the rest of this request sees the switch.
        $this->activeCache = null;
        $this->showingAllCache = true;
        $this->apartmentIdsCache = null;
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
