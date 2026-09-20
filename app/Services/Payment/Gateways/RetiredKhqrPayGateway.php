<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\KhqrPayment;

/**
 * A TOMBSTONE for khqr.cc, which this app stopped using in 2026-09.
 *
 * It exists for one reason: khqr_payments.provider says 'khqrpay' on every row
 * minted before the migration, including paid ones that platform finance and
 * the payments console still read. Deleting the driver outright would make
 * PaymentManager::for() throw on that history — turning a settled payment from
 * last month into a 500 on a reporting page.
 *
 * So the key still resolves, and answers the only thing it honestly can:
 * nothing here can ask khqr.cc anything, because there is no longer a client to
 * ask with. verify() is therefore FALSE — "no confirmation", never "unpaid".
 * Nothing acts on that negative: the reconcile command that used to expire rows
 * on it is gone, and a legacy row that genuinely was paid is settled by hand
 * (the landlord's confirm button for rent; SuperAdmin → Accounts → change plan
 * for a subscription, the sanctioned out-of-band path).
 *
 * Do not grow an HTTP call back into this class. If khqr.cc is ever wanted
 * again it is a new driver, written deliberately, not a tombstone quietly
 * coming back to life.
 */
final class RetiredKhqrPayGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'khqrpay';
    }

    public function verify(KhqrPayment $payment): bool
    {
        return false;
    }
}
