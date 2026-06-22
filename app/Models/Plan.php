<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'price_usd',
        'price_yearly_usd',
        'max_properties',
        'max_floors',
        'max_rooms',
        'max_staff',
        'billing_period_days',
        'trial_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_usd' => 'float',
            'price_yearly_usd' => 'float',
            'max_properties' => 'integer',
            'max_floors' => 'integer',
            'max_rooms' => 'integer',
            'max_staff' => 'integer',
            'billing_period_days' => 'integer',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    /** A yearly price is offered. */
    public function hasYearly(): bool
    {
        return $this->price_yearly_usd !== null && $this->price_yearly_usd > 0;
    }

    /**
     * The price for a given billing cycle. Falls back to the monthly price when a
     * yearly price hasn't been set.
     */
    public function priceFor(string $cycle): float
    {
        return $cycle === 'yearly' && $this->hasYearly()
            ? (float) $this->price_yearly_usd
            : (float) $this->price_usd;
    }

    public function hasUnlimitedProperties(): bool
    {
        return $this->max_properties === null;
    }

    public function hasUnlimitedFloors(): bool
    {
        return $this->max_floors === null;
    }

    public function hasUnlimitedRooms(): bool
    {
        return $this->max_rooms === null;
    }

    public function hasUnlimitedStaff(): bool
    {
        return $this->max_staff === null;
    }
}
