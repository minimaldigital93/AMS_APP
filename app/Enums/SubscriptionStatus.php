<?php

namespace App\Enums;

/**
 * Lifecycle of an account's subscription. Stored as VARCHAR (see the
 * status-to-string migration) — the original `enum(pending,active,expired,
 * cancelled)` column silently rejected the `trialing` value the code writes
 * under MySQL strict mode.
 *
 *   pending    created, awaiting first payment
 *   trialing   on a free trial (no payment yet), full access until expiry
 *   active     paid + within the billing period
 *   past_due   payment lapsed but inside a grace window (reserved)
 *   expired    billing period passed with no renewal
 *   cancelled  the account cancelled (access continues until expiry)
 */
enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** States that grant access (subject to expires_at). */
    public static function liveValues(): array
    {
        return [self::Active->value, self::Trialing->value];
    }
}
