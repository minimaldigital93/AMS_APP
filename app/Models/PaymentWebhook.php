<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One inbound payment-webhook delivery. See the create migration for the role
 * of `event_id` (idempotency key) and the retained raw `payload`.
 *
 * NOT account-scoped — webhooks arrive before any user context and are read by
 * the superadmin platform panel across all accounts.
 */
class PaymentWebhook extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'provider',
        'event_id',
        'transaction_id',
        'khqr_payment_id',
        'status',
        'signature_valid',
        'http_status',
        'payload',
        'error',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(KhqrPayment::class, 'khqr_payment_id');
    }
}
