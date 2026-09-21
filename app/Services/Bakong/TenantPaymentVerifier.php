<?php

namespace App\Services\Bakong;

use App\Models\KhqrPayment;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Support\Facades\Log;

/**
 * Turns a tenant's rent QR into a confirmed payment WITHOUT the landlord
 * pressing anything — but only when the landlord has said so and supplied a
 * credential of their own.
 *
 * This is the one place rent talks to NBC, and the rules it exists to keep:
 *
 *  - IT IS OFF UNTIL SWITCHED ON. No token, not enabled, or an expired token
 *    means this does nothing at all and the landlord confirms by hand from the
 *    queue, exactly as before. Silence is the default because the alternative
 *    is spending someone's metered allowance they never agreed to spend.
 *
 *  - A REFUSAL IS NOT A VERDICT. Only a 2xx from Bakong can say "unpaid". Over
 *    limit, backed off, no token, cooldown — every one of those is
 *    VERIFY_REFUSED, and acting on it as though the tenant had not paid would
 *    write real money out of the books with no way back. Nothing here ever
 *    expires or fails a row on a refusal.
 *
 *  - IT BOOKS THROUGH THE ONE PATH. A confirmed payment goes through
 *    KhqrPaymentService::finalize(), the same call the landlord's manual
 *    confirm makes, so there is exactly one route from "the money arrived" to
 *    "the books say so" — and settling twice is impossible because finalize()
 *    is a no-op on an already-paid row.
 *
 * Http:: deliberately does not appear here. Every request goes through
 * BakongProviderClient, which owns the eleven gates.
 */
class TenantPaymentVerifier
{
    private bool $refused = false;

    public function __construct(
        private readonly BakongTransactionService $bakong,
        private readonly MerchantBakongCredentials $credentials,
        private readonly KhqrPaymentService $khqr,
    ) {}

    /**
     * Ask about this row if we are allowed to, and settle it if it is paid.
     *
     * Returns the row as it now stands — unchanged when verification is off,
     * refused, or genuinely still unpaid.
     */
    public function attempt(KhqrPayment $row): KhqrPayment
    {
        $this->refused = false;

        if ($row->isPaid() || ! $this->isAutomatic($row)) {
            return $row;
        }

        $outcome = $this->bakong->verifyOutcome($row);

        if ($outcome === BakongTransactionService::VERIFY_REFUSED) {
            // Nothing is known. Leave the row alone and let the page say the
            // check could not be made, rather than spinning in silence.
            $this->refused = true;

            return $row;
        }

        if ($outcome !== BakongTransactionService::VERIFY_PAID) {
            return $row;
        }

        try {
            $this->khqr->finalize($row);
        } catch (\Throwable $e) {
            // The money is real and Bakong has confirmed it; failing to book it
            // is an operational problem, not a reason to tell the tenant their
            // payment did not happen. The landlord's queue still holds the row.
            Log::error('Bakong confirmed a rent payment that could not be booked', [
                'transaction_id' => $row->transaction_id,
                'error' => $e->getMessage(),
            ]);

            $this->refused = true;

            return $row->fresh() ?? $row;
        }

        return $row->fresh() ?? $row;
    }

    /**
     * Is this row eligible for hands-off confirmation?
     *
     * Every condition is local — no request is made to find out, because
     * discovering "we had no credential" by spending a metered call is the
     * mistake this whole integration is built to avoid.
     */
    public function isAutomatic(KhqrPayment $row): bool
    {
        $accountId = $this->bakong->merchantAccountFor($row);

        if ($accountId === null) {
            return false;
        }

        return $this->credentials->tokenFor($accountId) !== null;
    }

    /** Did the last attempt fail to get an answer, as opposed to "unpaid"? */
    public function lastRefused(): bool
    {
        return $this->refused;
    }
}
