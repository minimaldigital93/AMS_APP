<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\FiltersByProperty;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Keep the denormalized property_id in sync: derive it from the floor on
     * create so ledger rows and reports can read an apartment's property without
     * a join, regardless of which create path is used. Unit-number uniqueness is
     * enforced per floor (see the apartments_floor_..._unique index).
     */
    protected static function booted(): void
    {
        static::creating(function (Apartments $apartment) {
            if ($apartment->property_id === null && $apartment->floor_id !== null) {
                $apartment->property_id = $apartment->floor?->property_id;
            }
        });
    }

    // Status constants
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_OCCUPIED = 'occupied';

    /**
     * Pseudo-status used for display only (dots, badges, labels). Maintenance
     * is a flag on top of `status`, never a stored status value.
     */
    public const DISPLAY_STATUS_MAINTENANCE = 'maintenance';

    protected $fillable = [
        'floor_id',
        'supervisor_id',
        'apartment_number',
        'monthly_rent',
        'status',
        'under_maintenance',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'monthly_rent' => 'float',
            'under_maintenance' => 'boolean',
        ];
    }

    /**
     * The rentable stock: units that can hold a tenant today or in future.
     * This is the scope every occupancy / expected-revenue / break-even
     * denominator must use — a unit under maintenance is not vacant stock the
     * owner failed to rent, it is temporarily out of inventory.
     *
     * Do NOT use this to scope historical money queries: a unit put under
     * maintenance after a tenant left still earned real income earlier, and
     * dropping it would erase booked transactions from the reports.
     */
    public function scopeRentable(Builder $query): Builder
    {
        return $query->where('under_maintenance', false);
    }

    public function scopeUnderMaintenance(Builder $query): Builder
    {
        return $query->where('under_maintenance', true);
    }

    /** Available AND in the rentable stock — the only state a tenant may be assigned into. */
    public function isAssignable(): bool
    {
        return ! $this->under_maintenance && $this->status === self::STATUS_AVAILABLE;
    }

    /**
     * Status for badges/dots/labels: 'maintenance' wins over the stored status
     * so a unit under maintenance never reads as "Available" in the UI.
     */
    public function displayStatus(): string
    {
        return $this->under_maintenance
            ? self::DISPLAY_STATUS_MAINTENANCE
            : $this->status;
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

    /**
     * Rentals that are still ongoing — no end date, or an end date in the
     * future. Mirrors the "active rental" definition used in
     * ApartmentController::show().
     */
    public function activeRentals(): HasMany
    {
        return $this->hasMany(Rentals::class, 'apartment_id')
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }

    /**
     * True when a tenant currently lives here. Apartments soft-delete, which
     * does NOT cascade to rentals/tenants at the DB level, so deleting an
     * occupied unit would leave a live rental pointing at an invisible
     * apartment ($rental->apartment === null) and break the ledger. Callers
     * must block deletion when this is true — see ApartmentController::destroy().
     */
    public function isCurrentlyOccupied(): bool
    {
        return $this->activeRentals()->exists()
            || $this->tenants()->where('status', 'active')->exists();
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
