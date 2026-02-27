<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Apartments extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'floor_id',
        'supervisor_id',
        'apartment_number',
        'monthly_rent',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'monthly_rent' => 'float',
        ];
    }

    // Relationships

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floors::class, 'floor_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenants::class, 'apartment_id')->whereNull('deleted_at');
    }

    public function archivedTenants(): HasMany
    {
        return $this->hasMany(Tenants::class, 'apartment_id')->whereNotNull('archived_at')->whereNull('deleted_at');
    }

    public function allTenants(): HasMany
    {
        return $this->hasMany(Tenants::class, 'apartment_id')->whereNull('deleted_at');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rentals::class, 'apartment_id');
    }
}
