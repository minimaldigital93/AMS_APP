<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded refund against a paid KHQR payment. See the create migration for
 * why this is a recorded reversal rather than a provider API call. NOT
 * account-scoped — refunds are a platform (super admin) concern.
 */
class Refund extends Model
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'khqr_payment_id',
        'subscription_id',
        'amount',
        'currency',
        'reason',
        'status',
        'initiated_by',
        'provider_ref',
        'requested_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(KhqrPayment::class, 'khqr_payment_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
