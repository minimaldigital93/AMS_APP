<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Month-end close decision for the platform P&L. Present only for closed
 * months. `owner_withdrawal` of 0 means the month's profit was carried forward;
 * any positive amount was distributed to the owner and leaves the carried cash.
 *
 * See PlatformFinanceService for how the running carried-forward balance is
 * derived from these rows. Intentionally NOT account-scoped (platform ledger).
 */
class PlatformMonthlyClose extends Model
{
    protected $fillable = [
        'year',
        'month',
        'net_income',
        'owner_withdrawal',
        'withdrawal_note',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'net_income' => 'decimal:2',
            'owner_withdrawal' => 'decimal:2',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
