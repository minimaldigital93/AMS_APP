<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payments extends Model
{
    use BelongsToAccount, SoftDeletes;

    protected $fillable = [
        'rental_id',
        'amount',
        'due_date',
        'paid_at',
        'payment_method',
        'payment_status',
        'payment_type',
        'transaction_reference',
        'late_fee',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'late_fee' => 'float',
        ];
    }

    // Relationships

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rentals::class, 'rental_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Accounts::class, 'payment_id');
    }
}
