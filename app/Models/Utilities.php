<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\FiltersByProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Utilities extends Model
{
    use BelongsToAccount, FiltersByProperty;

    /** Utilities reach a property through rental → apartment → floor. */
    protected function propertyPath(): ?string
    {
        return 'rental.apartment.floor';
    }

    protected $fillable = [
        'tenant_id',
        'rental_id',
        'utility_type',
        'meter_number',
        'meter_reading_in',
        'meter_reading_out',
        'charge_amount',
        'billing_month',
        'billing_year',
        'paid_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'meter_reading_in' => 'float',
            'meter_reading_out' => 'float',
            'charge_amount' => 'float',
            'paid_status' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenants::class, 'tenant_id');
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rentals::class, 'rental_id');
    }

    // Scopes

    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->where('billing_month', $month)->where('billing_year', $year);
    }

    public function scopePaid($query)
    {
        return $query->where('paid_status', true);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('paid_status', false);
    }
}
