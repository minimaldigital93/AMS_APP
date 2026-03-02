<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyPeriod extends Model
{
    protected $fillable = [
        'fiscal_period_id',
        'user_id',
        'name',
        'month_number',
        'year',
        'start_date',
        'end_date',
        'opening_balance',
        'closing_balance',
        'total_income',
        'total_expenses',
        'net_income',
        'status',
        'closed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'opening_balance' => 'float',
            'closing_balance' => 'float',
            'total_income' => 'float',
            'total_expenses' => 'float',
            'net_income' => 'float',
            'closed_at' => 'datetime',
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

    // Scopes

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->where('month_number', $month)->where('year', $year);
    }

    // Helpers

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function canClose(): bool
    {
        return $this->status === 'open';
    }

    public function canReopen(): bool
    {
        return $this->status === 'closed';
    }
}
