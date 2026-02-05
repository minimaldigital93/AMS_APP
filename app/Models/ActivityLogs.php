<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLogs extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'ip_address',
        'description',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the target model (polymorphic-like manual relationship)
     */
    public function getTargetAttribute()
    {
        if (!$this->target_type || !$this->target_id) {
            return null;
        }

        $modelClass = match ($this->target_type) {
            'tenants' => Tenants::class,
            'apartments' => Apartments::class,
            'payments' => Payments::class,
            'rentals' => Rentals::class,
            'floors' => Floors::class,
            'utilities' => Utilities::class,
            'users' => User::class,
            default => null,
        };

        return $modelClass ? $modelClass::find($this->target_id) : null;
    }
}
