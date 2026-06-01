<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single KHQRPay dynamic-QR payment attempt.
 *
 * Holds the full checkout context (`checkout_payload`) so the booked payment
 * can be replayed server-side once Bakong confirms — see KhqrPaymentService.
 */
class KhqrPayment extends Model
{
    protected $fillable = [
        'transaction_id',
        'rental_id',
        'subscription_id',
        'fiscal_period_id',
        'user_id',
        'amount',
        'currency',
        'status',
        'checkout_payload',
        'qr_url',
        'provider_ref',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'checkout_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rentals::class, 'rental_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
