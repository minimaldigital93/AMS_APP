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
        'max_floors',
        'max_apartments',
        'billing_period_days',
        'trial_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_usd' => 'float',
            'max_floors' => 'integer',
            'max_apartments' => 'integer',
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

    public function hasUnlimitedFloors(): bool
    {
        return $this->max_floors === null;
    }

    public function hasUnlimitedApartments(): bool
    {
        return $this->max_apartments === null;
    }
}
