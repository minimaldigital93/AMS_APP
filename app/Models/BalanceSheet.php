<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceSheet extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'fiscal_period_id',
        'user_id',
        'item_type',
        'sub_type',
        'name',
        'description',
        'amount',
        'as_of_date',
        'reference_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'as_of_date' => 'date',
        ];
    }

    // Relationships

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriods::class, 'fiscal_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
