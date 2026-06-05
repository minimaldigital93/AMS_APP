<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A platform (SaaS) fiscal period — a span of calendar months the superadmin
 * manages (start month → end month). Holds the period's name, date range,
 * opening cash balance and open/closed status. The month-by-month P&L is
 * computed live elsewhere; this is the wrapper the superadmin can create,
 * rename, delete, and close.
 *
 * See PlatformFinanceService for how `opening_balance` seeds the carry-forward
 * and how `status` locks the period. Intentionally NOT account-scoped.
 */
class PlatformFiscalPeriod extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'opening_balance',
        'status',
        'closing_balance',
        'withdrawn_total',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'withdrawn_total' => 'decimal:2',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(PlatformWithdrawal::class)->orderByDesc('withdrawn_at')->orderByDesc('id');
    }
}
