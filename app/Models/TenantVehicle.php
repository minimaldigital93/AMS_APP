<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
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
 */
class TenantVehicle extends Model
{
    use BelongsToAccount;

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
}
