<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * One reusable property filter for every property-owned model, parameterized by
 * how that model reaches a property. This is the single place property filtering
 * lives — controllers/services call ->forActiveProperty() (or ->forProperty($id))
 * instead of repeating where/whereHas chains.
 *
 * A null property id is a deliberate no-op: it means "no active property" (or an
 * account-wide row), mirroring the BelongsToAccount/Accounts null convention so
 * global lookups and shared data keep working.
 */
trait FiltersByProperty
{
    /**
     * How this model reaches a property:
     *  - null → the model has its own `property_id` column (e.g. Floors).
     *  - a relation path whose final relation is a Floors row that owns
     *    `property_id` — e.g. 'floor', 'apartment.floor', 'rental.apartment.floor'.
     */
    abstract protected function propertyPath(): ?string;

    /** Limit the query to one property (null = no-op). */
    public function scopeForProperty(Builder $query, ?int $propertyId): Builder
    {
        if ($propertyId === null) {
            return $query;
        }

        $path = $this->propertyPath();

        if ($path === null) {
            return $query->where($this->getTable().'.property_id', $propertyId);
        }

        return $query->whereHas($path, fn (Builder $q) => $q->where('property_id', $propertyId));
    }

    /** Limit the query to the globally active property (the top-bar selection). */
    public function scopeForActiveProperty(Builder $query): Builder
    {
        return $query->forProperty(current_property_id());
    }
}
