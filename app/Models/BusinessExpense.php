<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessExpense extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'user_id',
        'fiscal_period_id',
        'expense_name',
        'category',
        'amount',
        'expense_date',
        'billing_month',
        'billing_year',
        'is_recurring',
        'note',
        'attachment',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'expense_date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriods::class, 'fiscal_period_id');
    }

    // Scopes

    public function scopeForMonth($query, $month, $year)
    {
        return $query->where('billing_month', $month)->where('billing_year', $year);
    }
}
