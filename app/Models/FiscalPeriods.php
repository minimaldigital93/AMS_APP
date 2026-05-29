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
        'opening_assets',
        'opening_liabilities',
        'opening_equity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opening_date' => 'date',
            'closing_date' => 'date',
            'opening_balance' => 'float',
            'closing_balance' => 'float',
            'opening_assets' => 'float',
            'opening_liabilities' => 'float',
            'opening_equity' => 'float',
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

    public function monthlyPeriods(): HasMany
    {
        return $this->hasMany(MonthlyPeriod::class, 'fiscal_period_id');
    }

    /**
     * Get the current (first open) monthly period.
     */
    public function currentMonthlyPeriod()
    {
        return $this->monthlyPeriods()->where('status', 'open')->orderBy('start_date')->first();
    }

    /**
     * Get the next monthly period that can be opened after closing the current one.
     */
    public function nextMonthlyPeriod(MonthlyPeriod $current)
    {
        return $this->monthlyPeriods()
            ->where('start_date', '>', $current->end_date)
            ->orderBy('start_date')
            ->first();
    }
}
