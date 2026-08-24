<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\FiltersByProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A vehicle a tenant keeps on the property.
 *
 * `monthly_fee` above zero makes it a billable parking spot: the tenant's
 * vehicle fees are summed into their monthly `parking` Utilities charge by
 * MonthlyBillingService, which is the single lane every downstream money flow
 * already reads (bill row, checkout, income ledger, receipt, move-out
 * settlement). Nothing is billed off this row directly.
 *
 * A vehicle belongs to a **tenant**; the room is *derived* through that tenant
 * (`tenant.apartment`) and deliberately not stored here. A tenant who moves
 * room takes their vehicles with them, so a second `apartment_id` column would
 * only be a copy that goes stale — same reason rent is derived rather than
 * invoiced. `room()`/`isVerified()` are that derivation, and the vehicle
 * management page flags every row where it comes back empty.
 */
class TenantVehicle extends Model
{
    use BelongsToAccount, FiltersByProperty;

    /** Vehicles reach a property through tenant → apartment → floor. */
    protected function propertyPath(): ?string
    {
        return 'tenant.apartment.floor';
    }

    /** The vehicle vocabulary — mirrored by the store request's `in:` rule. */
    public const TYPES = ['car', 'tuktuk', 'motorbike'];

    protected $fillable = [
        'tenant_id',
        'vehicle_type',
        // Free text ("Honda Dream") — description only; the plate is the
        // identity and nothing about billing reads the model.
        'vehicle_model',
        'plate_number',
        'monthly_fee',
    ];

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'float',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenants::class, 'tenant_id');
    }

    /** True when this vehicle carries a price and so gets billed as parking. */
    public function isBillable(): bool
    {
        return $this->monthly_fee > 0;
    }

    /**
     * The room this vehicle parks against — derived through its tenant, never
     * stored. Null means the vehicle can't be placed on the property: the
     * tenant has left (soft-deleted, so the FK cascade never fired) or has no
     * room assigned yet.
     */
    public function room(): ?Apartments
    {
        return $this->tenant?->apartment;
    }

    /** True when the vehicle resolves to a live tenant sitting in a room. */
    public function isVerified(): bool
    {
        return $this->room() !== null;
    }
}
