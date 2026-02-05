<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Accounts extends Model
{
    protected $fillable = [
        'fiscal_period_id',
        'payment_id',
        'user_id',
        'account_type',
        'category',
        'description',
        'amount',
        'transaction_date',
        'reference_number',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    // Relationships

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriods::class, 'fiscal_period_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payments::class, 'payment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
