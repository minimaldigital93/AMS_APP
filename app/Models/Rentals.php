<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rentals extends Model
{
    use BelongsToAccount, SoftDeletes;

    protected $fillable = [
        'apartment_id',
        'tenant_id',
        'start_date',
        'end_date',
        'rent_amount',
        'deposit',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'float',
            'deposit' => 'float',
        ];
    }

    // Relationships

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartments::class, 'apartment_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenants::class, 'tenant_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payments::class, 'rental_id');
    }

    public function utilities(): HasMany
    {
        return $this->hasMany(Utilities::class, 'rental_id');
    }

    // Scopes

    /**
     * Only rentals that are currently active (no end_date, or end_date is in the future).
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')->orWhere('end_date', '>=', now());
        });
    }
}
