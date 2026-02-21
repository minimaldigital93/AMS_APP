<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Utilities extends Model
{
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
}
