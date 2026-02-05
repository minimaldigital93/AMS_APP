<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalPeriods extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'opening_date',
        'closing_date',
        'opening_balance',
        'closing_balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opening_date' => 'date',
            'closing_date' => 'date',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Accounts::class, 'fiscal_period_id');
    }

    public function balanceSheets(): HasMany
    {
        return $this->hasMany(BalanceSheet::class, 'fiscal_period_id');
    }
}
