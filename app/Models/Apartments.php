<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\FiltersByProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Apartments extends Model
{
    use BelongsToAccount, FiltersByProperty, SoftDeletes;

    /** Apartments reach a property through their floor. */
    protected function propertyPath(): ?string
    {
        return 'floor';
    }

    // Status constants
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_OCCUPIED = 'occupied';

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

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE,
            self::STATUS_OCCUPIED,
        ];
    }

    /**
     * Get validation rule for status
     */
    public static function getStatusValidationRule(): string
    {
        return 'required|in:'.implode(',', self::getStatuses());
    }

    // Relationships

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floors::class, 'floor_id');
    }

    /** The owning property, derived through the floor. */
    public function getPropertyAttribute(): ?Property
    {
        return $this->floor?->property;
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

    public function fixedExpenses(): HasMany
    {
        return $this->hasMany(ApartmentFixedExpense::class, 'apartment_id');
    }

    public function activeFixedExpenses(): HasMany
    {
        return $this->hasMany(ApartmentFixedExpense::class, 'apartment_id')->where('is_active', true);
    }
}
