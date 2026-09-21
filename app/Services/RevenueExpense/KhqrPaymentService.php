<?php

namespace App\Services\RevenueExpense;

use App\Enums\PaymentStatus;
use App\Models\FiscalPeriods;
use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Models\MonthlyPeriod;
use App\Models\Plan;
use App\Models\Rentals;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Bakong\BakongQrService;
use App\Services\Bakong\MerchantBakongCredentials;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tenant rent KHQR, and the one place a confirmed payment is BOOKED.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * THIS CLASS NO LONGER TALKS TO ANY GATEWAY. khqr.cc was retired in 2026-09;
 * there is no client, no signature, no poll and no webhook left in it, and
 * `Http::` does not appear anywhere in the file. That is a property worth
 * keeping rather than a coincidence of the current implementation — it is what
 * makes the rent channel free of quota, free of outages and free of credentials
 * to leak. If a rent payment ever needs verifying against a provider again,
 * write a driver behind App\Contracts\PaymentGateway; do not reintroduce an
 * outbound call here.
 * ───────────────────────────────────────────────────────────────────────────
 *
 * TWO RESPONSIBILITIES, and they are deliberately different sizes:
 *
 *  1. THE RENT CHANNEL (Flow B, settlement_target=merchant). A KHQR is built on
 *     this server from the landlord's own Bakong account id — exact amount,
 *     their money, their bank — and shown to the tenant.
 *
 *     HOW IT IS CONFIRMED depends on whether the landlord holds a Bakong token
 *     of their OWN (merchant_payment_settings.bakong_token):
 *
 *       - Without one — the default — the row is minted manual/manual and the
 *         landlord confirms receipt by hand (confirmManual) after checking
 *         their banking app, or rejects it (rejectManual). Nothing auto-
 *         confirms it, because nothing is watching.
 *       - With one, the row is minted bakong/api and TenantPaymentVerifier
 *         checks it against that landlord's own credential and own allowance.
 *
 *     What must NEVER happen is rent being verified with the PLATFORM's token.
 *     That one is metered at roughly 100 requests a day for the whole
 *     installation and is shared with every subscription: every landlord's
 *     every tenant on it would let the busiest building lock out everyone else,
 *     and it would confirm one party's money with another party's credentials.
 *     BakongProviderClient refuses a merchant-target call that names no account
 *     precisely so that cannot be done by accident.
 *
 *  2. BOOKING (both flows). finalize() replays the stored checkout payload
 *     through IncomeRecordingService::checkout(), idempotent under a row lock,
 *     and finalizeSubscription() activates a paid plan. The DIRECT BAKONG
 *     integration calls both of these rather than reimplementing them
 *     (BakongTransactionService, ReconcileBakongPayments), so there is exactly
 *     one path from "the money arrived" to "the books say so", whichever
 *     provider found out.
 */
class KhqrPaymentService
{
    /** Is the tenant-rent KHQR channel available at all? */
    public static function featureEnabled(): bool
    {
        return true;
    }

    /**
     * Create a pending KhqrPayment for a tenant RENT payment (Flow B).
     *
     * The channel follows the landlord's own credential: 'api' when they hold a
     * Bakong token that can verify the payment, 'manual' when they do not and
     * will confirm it by hand. Legacy khqr.cc rows (also 'api') survive in the
     * table and are read exactly as they were left — no gateway remains that
     * could answer about them, which is why they are never polled.
     *
     * @param  array  $payload  checkout payload (pay_rent, pay_utilities, rent_amount, late_fee, payment_date, note)
     * @return KhqrPayment with the QR payload populated
     */
    public function createQr(Rentals $rental, FiscalPeriods $period, int $userId, float $amount, array $payload): KhqrPayment
    {
        $settings = MerchantPaymentSetting::forAccount($rental->account_id);
        $demo = (bool) config('rent_qr.demo');

        // Refuse before any row exists. A checkout session nothing can settle is
        // worse than no session: it sits open, shows the tenant a QR pointing
        // nowhere, and is swept by every net that looks for open rows.
        if (! $demo && ! $this->canCollect($settings)) {
            throw new \RuntimeException(__('messages.khqr_payment_settings_missing'));
        }

        $transactionId = 'KHQR-'.$rental->id.'-'.now()->format('YmdHis').'-'.random_int(100, 999);

        // A rent QR is a VERIFIABLE session only when the landlord holds a
        // Bakong token of their own. That single fact decides three things at
        // once, which is why it is settled here at mint time rather than
        // re-derived by each reader:
        //
        //   - whether the provider client will answer about it at all
        //     (isActiveBakongSession() requires bakong/api),
        //   - whether `khqr:expire-abandoned` may close it — it only touches
        //     channel='api', and closing a row automatically is only safe when
        //     a conclusive "unpaid" is actually obtainable,
        //   - and which gateway a historical row reads back as.
        //
        // Without a token it stays manual/manual: the landlord confirms it by
        // hand and nothing ever expires it on a guess.
        $verifiable = $this->merchantCanSelfVerify($rental->account_id);

        $row = KhqrPayment::create([
            'transaction_id' => $transactionId,
            'rental_id' => $rental->id,
            'fiscal_period_id' => $period->id,
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => ($settings?->currency) ?: config('rent_qr.currency', 'USD'),
            'status' => 'pending',
            'settlement_target' => 'merchant',
            'provider' => $verifiable ? 'bakong' : 'manual',
            'channel' => $verifiable ? 'api' : 'manual',
            'checkout_payload' => $payload,
            'expires_at' => now()->addMinutes($this->qrTtlMinutes()),
        ]);

        $row->forceFill($this->qrAttributes($settings, $transactionId, $amount));
        $row->transitionTo(PaymentStatus::QrGenerated);
        $row->save();

        return $row;
    }

    /**
     * Can this landlord's own credential confirm their tenants' payments?
     *
     * Resolved locally and defensively — a failure to answer means "no", which
     * keeps the row on the manual path rather than minting a session nothing
     * can ever verify.
     */
    private function merchantCanSelfVerify(?int $accountId): bool
    {
        if ($accountId === null) {
            return false;
        }

        try {
            return app(MerchantBakongCredentials::class)->tokenFor($accountId) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Can this account collect rent by QR or bank transfer at all?
     *
     * A Bakong account id is enough on its own (the QR is built from it); so is
     * an uploaded static image or a bank account number, which the checkout
     * modal shows instead.
     */
    private function canCollect(?MerchantPaymentSetting $settings): bool
    {
        return $settings !== null
            && (filled($settings->bakong_account_id) || $settings->canUseManual());
    }

    /**
     * What to stamp on the row so the QR can be rendered later.
     *
     * The PAYLOAD is stored, not the image. An <img src> of a rendered QR does
     * not fit in qr_url (varchar 255), and rebuilding the payload at display
     * time would let a settings edit between minting and scanning silently
     * change what the tenant is paying into. Storing the bytes is the same rule
     * the subscription flow follows, and for the same reason.
     */
    private function qrAttributes(?MerchantPaymentSetting $settings, string $transactionId, float $amount): array
    {
        $accountId = $this->payoutAccountId($settings);

        if ($accountId !== '') {
            $qr = app(BakongQrService::class)->build(
                billNumber: $transactionId,
                amount: $amount,
                bakongAccountId: $accountId,
                merchantName: $settings?->bank_account_name ?: $settings?->bank_name,
                merchantCity: null,
                currency: $settings?->currency ?: config('rent_qr.currency', 'USD'),
                // Rent QRs live by their own clock (config/rent_qr.php), not
                // Bakong's — nothing here is metered — but the tag is required
                // for any QR carrying an amount, whoever confirms it.
                expiresAt: now()->addMinutes((int) config('rent_qr.ttl', 30)),
            );

            return ['qr_payload' => $qr->payload, 'qr_md5' => $qr->md5];
        }

        // No Bakong id: fall back to the landlord's uploaded static image. It is
        // a fixed QR with no amount in it, so the checkout modal prints the
        // figure beside it and the landlord still confirms by hand.
        //
        // asset() keeps the /ams_app sub-path prefix on the live server;
        // Storage::url() would emit a bare /storage/... that 404s there.
        return filled($settings?->khqr_image_path)
            ? ['qr_url' => asset('storage/'.$settings->khqr_image_path)]
            : [];
    }

    /**
     * WHERE THE RENT LANDS. The landlord's own Bakong id, never the platform's.
     *
     * Demo mode is the single exception and it is confined to non-production
     * (see config/rent_qr.php): falling back to a placeholder on a live server
     * would build a QR that collects someone else's money while looking
     * perfectly valid to the tenant.
     */
    private function payoutAccountId(?MerchantPaymentSetting $settings): string
    {
        $accountId = trim((string) $settings?->bakong_account_id);

        if ($accountId !== '') {
            return $accountId;
        }

        return config('rent_qr.demo') ? 'demo@aclb' : '';
    }

    /**
     * The scannable QR for a row that carries its own payload, as a data URI.
     *
     * Inline rather than a URL: the old manual channel handed the payload to
     * api.qrserver.com as a query parameter, which put a live payment
     * instruction — the landlord's Bakong id and the amount — on a third party's
     * server, and made the QR fail to appear whenever that service was
     * unreachable.
     */
    public function qrImage(KhqrPayment $row, int $size = 280): ?string
    {
        if (blank($row->qr_payload)) {
            return null;
        }

        return app(BakongQrService::class)->dataUri($row->qr_payload, $size);
    }

    /**
     * Landlord confirms a manual-channel payment after checking their banking
     * app. Books the payment via the same idempotent finalize path.
     */
    public function confirmManual(KhqrPayment $row): void
    {
        if ($row->channel !== 'manual') {
            throw new \LogicException('Only manual-channel payments can be confirmed by hand.');
        }

        $this->finalize($row);
    }

    /**
     * Landlord rejects a manual-channel payment (money never arrived).
     */
    public function rejectManual(KhqrPayment $row): void
    {
        if ($row->channel !== 'manual') {
            throw new \LogicException('Only manual-channel payments can be rejected.');
        }

        DB::transaction(function () use ($row) {
            $locked = KhqrPayment::whereKey($row->getKey())->lockForUpdate()->first();
            if ($locked && $locked->isOpen()) {
                $locked->transitionTo(PaymentStatus::Rejected);
                $locked->save();
            }
        });
    }

    /**
     * Mark that the payer has opened the checkout and the client is now polling
     * (qr_generated → waiting_payment). Idempotent and cheap: only the first poll
     * takes the lock; later polls short-circuit on the in-memory status.
     */
    public function markWaiting(KhqrPayment $row): void
    {
        if ($row->statusEnum() !== PaymentStatus::QrGenerated) {
            return;
        }

        DB::transaction(function () use ($row) {
            $locked = KhqrPayment::whereKey($row->getKey())->lockForUpdate()->first();
            if ($locked && $locked->statusEnum() === PaymentStatus::QrGenerated) {
                $locked->transitionTo(PaymentStatus::WaitingPayment);
                $locked->save();
            }
        });
    }

    /**
     * Advance a rent row from the checkout modal's status poll.
     *
     * ENTIRELY LOCAL, and that is the headline change from the khqr.cc version:
     * this used to make a metered verify call on (almost) every poll, which is
     * how a forgotten tab could spend an allowance overnight. There is nobody to
     * ask now — the landlord is the oracle — so the poll registers the payer as
     * waiting, reports whatever confirmManual() may have written in the
     * meantime, and expires a dead QR. It cannot cost anything and cannot fail.
     */
    public function pollAndAdvance(KhqrPayment $row): KhqrPayment
    {
        $this->markWaiting($row);

        if ($row->isPaid()) {
            $this->finalize($row);

            return $row->refresh();
        }

        if ($this->expireIfElapsed($row)) {
            return $row->refresh();
        }

        return $row->refresh();
    }

    /**
     * Kept so the three checkout poll endpoints keep one shape between
     * providers. The rent channel contacts nobody, so it can never be refused
     * by one — only the direct Bakong subscription poll can answer true.
     */
    public function lastPollRefused(): bool
    {
        return false;
    }

    /**
     * Lazily expire an open row whose QR lifetime has elapsed, so the poller sees
     * it immediately instead of waiting up to five minutes for the cron.
     */
    public function expireIfElapsed(KhqrPayment $row): bool
    {
        if (! $row->isOpen() || $row->expires_at === null || $row->expires_at->isFuture()) {
            return false;
        }

        return $this->expireRow($row);
    }

    /** Transition an open row to expired under a lock. */
    private function expireRow(KhqrPayment $row): bool
    {
        return (bool) DB::transaction(function () use ($row) {
            $locked = KhqrPayment::whereKey($row->getKey())->lockForUpdate()->first();
            if ($locked && $locked->isOpen()) {
                $locked->transitionTo(PaymentStatus::Expired);
                $locked->save();

                return true;
            }

            return false;
        });
    }

    private function qrTtlMinutes(): int
    {
        return max(1, (int) config('rent_qr.ttl', 30));
    }

    /**
     * Record the payment for a confirmed KHQR row, exactly once.
     * Replays the stored checkout payload through IncomeRecordingService.
     */
    public function finalize(KhqrPayment $row): void
    {
        if ($row->isPaid()) {
            return;
        }

        // Subscription payments activate the plan instead of booking a rental.
        if ($row->subscription_id) {
            $this->finalizeSubscription($row);

            return;
        }

        DB::transaction(function () use ($row) {
            // Re-load under a lock so two confirmations can't double-book.
            $locked = KhqrPayment::whereKey($row->getKey())->lockForUpdate()->first();
            if (! $locked || ! $locked->isOpen()) {
                return;
            }

            $period = FiscalPeriods::find($locked->fiscal_period_id);
            $rental = Rentals::with(['apartment', 'tenant'])->find($locked->rental_id);
            if (! $period || ! $rental) {
                Log::warning('KHQR finalize skipped: missing period/rental', ['tran' => $locked->transaction_id]);

                return;
            }

            $payload = $locked->checkout_payload;
            $payload['payment_method'] = 'khqr';
            $payload['transaction_reference'] = $locked->transaction_id;

            // NotInClosedMonth was validated when the QR was generated — but a
            // confirmation can land after the month has since been closed, and a
            // backdated ledger row would silently desync the frozen totals.
            // Re-date the booking to today instead (withoutAccountScope: this
            // can run from a cron with no authenticated user, and the account
            // scope must not leak another account's months in).
            $originalDate = Carbon::parse($payload['payment_date'] ?? now());
            $closedMonth = MonthlyPeriod::withoutAccountScope()
                ->where('fiscal_period_id', $period->id)
                ->where('status', 'closed')
                ->whereDate('start_date', '<=', $originalDate)
                ->whereDate('end_date', '>=', $originalDate)
                ->exists();
            if ($closedMonth) {
                $payload['payment_date'] = now()->toDateString();
                $payload['note'] = trim(($payload['note'] ?? '')
                    .' | Original payment date '.$originalDate->toDateString()
                    .' fell in a closed month; booked on confirmation date.', ' |');
            }

            (new IncomeRecordingService(userId: $locked->user_id, period: $period))
                ->checkout($rental, $payload);

            $locked->transitionTo(PaymentStatus::Paid);
            $locked->forceFill(['paid_at' => now()])->save();
        });
    }

    /**
     * Activate a subscription whose KHQR payment has been confirmed, exactly once.
     * Marks the subscription active (+ expiry), promotes the account user to the
     * `admin` role, and links the paying KHQR row. Idempotent under a row lock.
     */
    public function finalizeSubscription(KhqrPayment $row): void
    {
        DB::transaction(function () use ($row) {
            $locked = KhqrPayment::whereKey($row->getKey())->lockForUpdate()->first();
            if (! $locked || ! $locked->isOpen()) {
                return;
            }

            $subscription = Subscription::with('plan')->find($locked->subscription_id);
            if (! $subscription) {
                Log::warning('finalizeSubscription skipped: missing subscription', ['tran' => $locked->transaction_id]);

                return;
            }

            // The plan/cycle the customer actually PAID for, carried on the
            // payment row. An upgrade lands HERE and nowhere else, so an
            // abandoned checkout leaves the live plan alone. Falls back to the
            // subscription for rows minted before this field existed, and for a
            // plan deleted between minting and payment.
            $payload = $locked->checkout_payload ?? [];
            $plan = (isset($payload['plan_id']) ? Plan::find((int) $payload['plan_id']) : null)
                ?? $subscription->plan;
            $cycle = $payload['billing_cycle'] ?? $subscription->billing_cycle;

            $days = $cycle === 'yearly'
                ? 365
                : ($plan?->billing_period_days ?? 30);

            // Early renewals EXTEND the remaining time instead of resetting it.
            $base = ($subscription->expires_at !== null && $subscription->expires_at->isFuture() && $subscription->status !== 'trialing')
                ? $subscription->expires_at->copy()
                : now();

            $subscription->forceFill([
                'status' => 'active',
                'plan_id' => $plan?->id ?? $subscription->plan_id,
                'billing_cycle' => $cycle,
                'price_paid' => $locked->amount, // snapshot — plan price may change later
                'started_at' => $subscription->started_at ?? now(),
                'expires_at' => $base->addDays($days),
                'cancelled_at' => null,
                'cancel_reason' => null,
                'khqr_payment_id' => $locked->id,
            ])->save();

            // Promote the account owner to admin (signup) — no-op on renewals —
            // and flip the account active so it can log in (LoginRequest gates on this).
            $owner = User::find($subscription->account_id);
            if ($owner) {
                if (! $owner->hasRole('admin')) {
                    $owner->assignRole('admin');
                }
                if ($owner->status !== 'active') {
                    $owner->forceFill(['status' => 'active'])->save();
                }
            }

            $locked->transitionTo(PaymentStatus::Paid);
            $locked->forceFill(['paid_at' => now()])->save();

            // Actor is null here — activation runs from a poll / cron.
            app(\App\Services\Audit\AuditLogger::class)->record('subscription.activated', $subscription, [
                'transaction_id' => $locked->transaction_id,
                'plan' => $plan?->slug, // the purchased plan; ->plan is stale after the forceFill
                'amount' => (float) $locked->amount,
                'currency' => $locked->currency,
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ]);
        });
    }
}
