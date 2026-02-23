<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantLeave extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'rental_id',
        'apartment_id',
        'leave_date',
        'original_move_out_date',
        'stay_days',
        'pro_rata_rent',
        'electricity_reading',
        'electricity_charge',
        'water_reading',
        'water_charge',
        'internet_charge',
        'parking_charge',
        'total_amount_due',
        'deposit_applied',
        'balance_due',
        'refund_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'leave_date' => 'date',
            'original_move_out_date' => 'date',
            'pro_rata_rent' => 'float',
            'electricity_reading' => 'float',
            'electricity_charge' => 'float',
            'water_reading' => 'float',
            'water_charge' => 'float',
            'internet_charge' => 'float',
            'parking_charge' => 'float',
            'total_amount_due' => 'float',
            'deposit_applied' => 'float',
            'balance_due' => 'float',
            'refund_amount' => 'float',
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

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartments::class, 'apartment_id');
    }
}

