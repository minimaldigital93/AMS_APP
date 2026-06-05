<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ad-hoc owner withdrawal taken against a fiscal period's carried-forward
 * cash, independent of the month-end close. Each row lowers the period's
 * carried balance and adds to its total withdrawn.
 *
 * See PlatformFinanceService for how these fold into the period's running
 * balance. Intentionally NOT account-scoped (platform ledger).
 */
class PlatformWithdrawal extends Model
{
    protected $fillable = [
        'platform_fiscal_period_id',
        'amount',
        'note',
        'withdrawn_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'withdrawn_at' => 'date',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PlatformFiscalPeriod::class, 'platform_fiscal_period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
