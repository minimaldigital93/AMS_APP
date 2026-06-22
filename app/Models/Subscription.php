<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer account's subscription to a Plan. The account is the owning admin
 * user (subscriptions.account_id). One subscription is "active" per account at
 * a time; activation is driven by the KHQR signup/renew payment.
 *
 * NOTE: this model is intentionally NOT account-scoped — it is read by the
 * superadmin platform panel across every account, and by the signup flow before
 * a role is assigned.
 */
class Subscription extends Model
{
    protected $fillable = [
        'account_id',
        'plan_id',
        'billing_cycle',
        'price_paid',
        'status',
        'started_at',
        'expires_at',
        'cancelled_at',
        'cancel_reason',
        'trial_started_at',
        'khqr_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'price_paid' => 'float',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'trial_started_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function onTrial(): bool
    {
        return $this->status === 'trialing'
            && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    /** A trial was ever started on this account — one free trial, ever. */
    public function trialUsed(): bool
    {
        return $this->trial_started_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || (in_array($this->status, ['active', 'trialing'], true) && $this->expires_at !== null && $this->expires_at->isPast());
    }
}
