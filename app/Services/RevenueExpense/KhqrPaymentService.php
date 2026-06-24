<?php

namespace App\Services\RevenueExpense;

use App\Enums\PaymentStatus;
use App\Models\FiscalPeriods;
use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Models\Rentals;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * KHQRPay (khqr.cc) client + payment finalizer for BOTH settlement targets:
 *
 *  - Flow A (settlement_target=platform): merchant pays the super admin for a
 *    subscription. Signed with the PLATFORM credentials from config/services.
 *  - Flow B (settlement_target=merchant): tenant pays the landlord. Signed with
 *    the LANDLORD's own credentials from merchant_payment_settings — rent money
 *    settles directly in the landlord's bank, never the platform's.
 *
 * Flow B channels:
 *  - api:    landlord has KHQRPay credentials → dynamic QR, auto-verified by
 *            poll + webhook.
 *  - manual: no API credentials → show the landlord's static KHQR image (or a
 *            locally generated Bakong KHQR / bank details) and let the landlord
 *            confirm receipt by hand. verify() never auto-confirms manual rows.
 *
 * finalize() replays the stored checkout payload through
 * IncomeRecordingService::checkout(), idempotent under a row lock — safe to
 * call from the status poll, the webhook, and the manual-confirm action.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * PROVIDER-SPECIFIC WIRING (fill from your KHQRPay dashboard integration page):
 *   - sign()                  : the exact SHA1 hash formula.
 *   - createQr() response keys : where the QR image URL + provider ref live.
 *   - verify() endpoint/keys   : the status/verify call and its "paid" shape.
 * Everything else is final. Search for "TODO(khqrpay)" to find each spot.
 * ───────────────────────────────────────────────────────────────────────────
 */
class KhqrPaymentService
{
    /**
     * Create a pending KhqrPayment for a tenant RENT payment (Flow B) using the
     * landlord's own payment settings. Picks the best available channel:
     * api (dynamic QR) → manual (static image / generated Bakong QR / bank info).
     *
     * @param  array  $payload  checkout payload (pay_rent, pay_utilities, rent_amount, late_fee, payment_date, note)
     * @return KhqrPayment with channel + qr_url (+ provider_ref for api) populated
     */
    public function createQr(Rentals $rental, FiscalPeriods $period, int $userId, float $amount, array $payload): KhqrPayment
    {
        $settings = MerchantPaymentSetting::forAccount($rental->account_id);
        $demo = (bool) config('services.khqrpay.demo');

        $canApi = $settings !== null && $settings->canUseApi();
        $canManual = $settings !== null && ($settings->canUseManual() || filled($settings->bakong_account_id));

        // Demo mode tolerates missing settings so the flow stays demonstrable.
        if (! $canApi && ! $canManual && ! $demo) {
            throw new \RuntimeException(__('messages.khqr_payment_settings_missing'));
        }

        $transactionId = 'KHQR-'.$rental->id.'-'.now()->format('YmdHis').'-'.random_int(100, 999);

        $row = KhqrPayment::create([
            'transaction_id' => $transactionId,
            'rental_id' => $rental->id,
            'fiscal_period_id' => $period->id,
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => ($settings?->currency) ?: config('services.khqrpay.currency', 'USD'),
            'status' => 'pending',
            'settlement_target' => 'merchant',
            'channel' => ($canApi || ! $canManual) ? 'api' : 'manual',
            'checkout_payload' => $payload,
            'expires_at' => now()->addMinutes($this->qrTtlMinutes()),
        ]);

        if ($row->channel === 'manual') {
            $row->forceFill(['qr_url' => $this->manualQrUrl($settings, $transactionId, $amount)]);
            $row->transitionTo(PaymentStatus::QrGenerated);
            $row->save();

            return $row;
        }

        // Demo mode: render a local example KHQR and skip the live API entirely.
        if ($demo) {
            return $this->fillDemo($row, $amount);
        }

        return $this->requestQr($row, KhqrCredentials::forMerchant($settings));
    }

    /**
     * Create a pending KhqrPayment for a plan SUBSCRIPTION (signup or renewal)
     * + mint the QR with the PLATFORM credentials (Flow A — money goes to the
     * super admin).
     */
    public function createSubscriptionQr(Subscription $subscription, float $amount): KhqrPayment
    {
        // Fallback guard: with no platform KHQRPay credentials configured (the
        // cleared / unconfigured state), don't call the gateway with empty creds
        // — fail fast with a clear message the entry points already catch, so the
        // signup/billing pages show "payment unavailable" instead of a 500.
        if (! config('services.khqrpay.demo') && ! KhqrCredentials::platform()->isConfigured()) {
            throw new \App\Exceptions\KhqrPlatformCredentialsMissingException(
                __('messages.khqr_payment_settings_missing')
            );
        }

        // Each call mints a FRESH transaction. KHQRPay hosted-checkout sessions
        // are single-use and short-lived on khqr.cc's side: once a transaction_id
        // has been opened there and its session lapses, redirecting to it again
        // shows "payment session expired — return to the shop to refresh". So we
        // never reuse a transaction_id across checkout initiations (signup,
        // re-register, renew). Instead we retire any QR still open for this
        // subscription before minting the new one — that keeps the double-payment
        // invariant (at most one payable QR per subscription at a time) while
        // guaranteeing the customer is always handed a live session.
        KhqrPayment::where('subscription_id', $subscription->id)
            ->where('settlement_target', 'platform')
            ->whereIn('status', PaymentStatus::openValues())
            ->get()
            ->each(fn (KhqrPayment $open) => $this->expireRow($open));

        $transactionId = 'SUB-'.$subscription->id.'-'.now()->format('YmdHis').'-'.random_int(100, 999);

        $row = KhqrPayment::create([
            'transaction_id' => $transactionId,
            'subscription_id' => $subscription->id,
            'amount' => $amount,
            'currency' => KhqrCredentials::platform()->currency,
            'status' => 'pending',
            'settlement_target' => 'platform',
            'channel' => 'api',
            'checkout_payload' => ['type' => 'subscription', 'subscription_id' => $subscription->id],
            'expires_at' => now()->addMinutes($this->qrTtlMinutes()),
        ]);

        if (config('services.khqrpay.demo')) {
            return $this->fillDemo($row, $amount);
        }

        // KHQRPay is a HOSTED-CHECKOUT gateway — there is no headless "mint me a
        // QR image" API to call here (the old qr-api-khqrcc endpoint never
        // existed for this profile and 502'd every time). Instead the customer is
        // redirected to the signed checkout URL (see subscriptionCheckoutUrl) and
        // pays on khqr.cc, which settles back via the signed webhook to
        // khqr.callback. Just mark the row payable so the return page can poll it.
        $row->transitionTo(PaymentStatus::QrGenerated);
        $row->save();

        return $row;
    }

    /**
     * Build the signed KHQRPay HOSTED-CHECKOUT URL for a subscription payment.
     *
     * KHQRPay has no headless QR API: the customer is redirected (GET) to this
     * URL, pays on khqr.cc, and settlement returns via the signed webhook to
     * khqr.callback (which must be set as the profile's Global Webhook URL).
     * $successUrl is where khqr.cc sends the customer's browser back afterwards.
     *
     *   {baseUrl}/api/payment/request/{profileId}?transaction_id&amount&success_url&remark&hash
     *   hash = sha1(secret + transaction_id + amount + success_url + remark)
     */
    public function subscriptionCheckoutUrl(KhqrPayment $row, string $successUrl): string
    {
        $creds = KhqrCredentials::platform();

        $params = [
            'transaction_id' => $row->transaction_id,
            'amount' => number_format((float) $row->amount, 2, '.', ''),
            'success_url' => $successUrl,
            'remark' => $this->buildQrRemark($row),
        ];
        $params['hash'] = $this->sign($params, $creds->secret);

        return rtrim($creds->baseUrl, '/')
            .'/api/payment/request/'.$creds->profileId
            .'?'.http_build_query($params);
    }

    /**
     * Ask KHQRPay (with the row's own credentials) to mint the dynamic QR.
     */
    private function requestQr(KhqrPayment $row, KhqrCredentials $creds): KhqrPayment
    {
        // KHQRPay uses success_url as the webhook callback target when the
        // profile has no Global Webhook URL set — so point it at our own signed
        // callback endpoint, not a browser page. A Global Webhook URL configured
        // on the profile still takes priority over this.
        $params = [
            'transaction_id' => $row->transaction_id,
            'amount' => number_format($row->amount, 2, '.', ''),
            'success_url' => route('khqr.callback'),
            'remark' => $this->buildQrRemark($row),
        ];
        $params['hash'] = $this->sign($params, $creds->secret);

        $endpoint = $this->qrApiEndpoint($creds);

        // Log the outgoing request (avoid logging secrets)
        Log::info('KHQRPay request', [
            'endpoint' => $endpoint,
            'transaction' => $row->transaction_id,
            'amount' => $row->amount,
        ]);

        $response = Http::asForm()->acceptJson()
            ->connectTimeout(3)->timeout(10)
            ->post($endpoint, $params);

        // Capture response body for diagnosis (safe to log; no secret in response)
        $responseBody = $response->body();
        Log::debug('KHQRPay response', ['status' => $response->status(), 'tran' => $row->transaction_id, 'body' => $responseBody]);

        if (! $response->successful()) {
            Log::warning('KHQRPay createQr failed', ['status' => $response->status(), 'tran' => $row->transaction_id, 'body' => $responseBody]);
            $row->transitionTo(PaymentStatus::Failed);
            $row->save();
            throw new \RuntimeException('KHQRPay did not return a QR (HTTP '.$response->status().').');
        }

        $body = $response->json() ?? [];
        if (isset($body['responseCode']) && (int) $body['responseCode'] !== 0) {
            Log::warning('KHQRPay createQr returned error', ['code' => $body['responseCode'] ?? null, 'message' => $body['responseMessage'] ?? null, 'tran' => $row->transaction_id]);
            $row->transitionTo(PaymentStatus::Failed);
            $row->save();
            throw new \RuntimeException('KHQRPay returned a non-success response.');
        }

        $data = $body['data'] ?? $body;

        // qr_url must be the hosted PNG image URL — the checkout view renders it
        // as <img src>. data.qr is the raw EMV string (for local QR rendering),
        // NOT an image, so it must never land in qr_url.
        $row->forceFill([
            'qr_url' => $data['qr_url'] ?? $data['qrImage'] ?? $data['checkout_url'] ?? null,
            'provider_ref' => $data['md5'] ?? $data['tran'] ?? $data['transaction_id'] ?? null,
        ]);
        $row->transitionTo(PaymentStatus::QrGenerated);
        $row->save();

        return $row;
    }

    /**
     * QR image for the manual channel: prefer a locally generated dynamic Bakong
     * KHQR (exact amount, merchant's own Bakong ID), else the uploaded static
     * image, else null (checkout shows bank details only).
     */
    private function manualQrUrl(MerchantPaymentSetting $settings, string $transactionId, float $amount): ?string
    {
        if (filled($settings->bakong_account_id)) {
            $payload = $this->buildKhqrPayload(
                $transactionId,
                $amount,
                bakongId: $settings->bakong_account_id,
                merchantName: $settings->bank_account_name ?: 'Merchant',
                currency: $settings->currency ?: 'USD',
            );

            return 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&ecc=M&data='.rawurlencode($payload);
        }

        if (filled($settings->khqr_image_path)) {
            // asset() keeps the /ams_app sub-path prefix on the live server;
            // Storage::url() would emit a bare /storage/... that 404s there.
            return asset('storage/'.$settings->khqr_image_path);
        }

        return null;
    }

    /**
     * Ask KHQRPay whether the payment has been confirmed by Bakong.
     * Manual-channel rows are never auto-confirmed — the landlord confirms by
     * hand (confirmManual). On confirmation, finalize() is the caller's job.
     */
    public function verify(KhqrPayment $row): bool
    {
        if ($row->isPaid()) {
            return true;
        }

        // Terminal rows (failed/expired/cancelled/refunded/rejected) never settle.
        if (! $row->isOpen()) {
            return false;
        }

        if ($row->channel === 'manual') {
            return false;
        }

        // Demo mode: auto-confirm a few seconds after the QR is generated so the
        // full scan → waiting → paid → record flow can be demonstrated end-to-end.
        if (config('services.khqrpay.demo')) {
            return $row->created_at !== null && $row->created_at->diffInSeconds(now()) >= 8;
        }

        // Cooldown: a public status poll fires every few seconds — never make a
        // live provider call more than once per verify_cooldown window. The last
        // result is cached, so a confirmed payment is still seen promptly.
        $cooldownKey = 'khqr:verify:'.$row->transaction_id;
        $cached = Cache::get($cooldownKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        $result = $this->queryProviderPaid($row);
        Cache::put($cooldownKey, $result, now()->addSeconds((int) config('services.khqrpay.verify_cooldown', 4)));

        return $result;
    }

    /**
     * Live provider call: ask KHQRPay whether the transaction has settled. Pinned
     * to the row's own credentials, with the same amount/currency defence the
     * webhook applies. Any error / non-success / mismatch reads as "unpaid".
     */
    private function queryProviderPaid(KhqrPayment $row): bool
    {
        $creds = $this->credentialsFor($row);
        if ($creds === null) {
            return false;
        }

        // KHQRPay "Check Transaction V2" endpoint (fast confirmation with Bakong
        // fallback) — the path the live khqr.cc gateway actually answers. The old
        // /check-trans path 404s, so verify() never confirmed a polled payment.
        // POST https://{baseUrl}/api/{profileId}/payment-gateway/v1/payments/check-transv2-khqrcc
        $endpoint = rtrim($creds->baseUrl, '/')
            .'/api/'.$creds->profileId
            .'/payment-gateway/v1/payments/check-transv2-khqrcc';

        $params = [
            'transaction_id' => $row->transaction_id,
        ];
        // KHQRPay expects sha1(profile_key . transaction_id)
        $params['hash'] = sha1($creds->secret.$row->transaction_id);

        try {
            $response = Http::asForm()->acceptJson()
                ->connectTimeout(3)->timeout(8)->retry(2, 200, throw: false)
                ->post($endpoint, $params);
        } catch (\Throwable $e) {
            Log::warning('KHQRPay verify error', ['tran' => $row->transaction_id, 'msg' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $body = $response->json() ?? [];

        // A non-zero responseCode (e.g. "transaction not found yet") means the
        // payment has NOT settled — treat it as unpaid, never as confirmed.
        if (isset($body['responseCode']) && (int) $body['responseCode'] !== 0) {
            return false;
        }

        // The real paid/unpaid state lives inside the data envelope (same shape
        // as the createQr response). The envelope's responseCode === 0 only means
        // the *query* succeeded, NOT that money arrived — relying on it would
        // auto-confirm every poll before the payer has actually paid.
        $data = $body['data'] ?? $body;

        $status = strtoupper((string) (
            $data['status']
            ?? $data['payment_status']
            ?? $data['transaction_status']
            ?? ''
        ));

        $paid = ($data['verified'] ?? false) === true
            || ($data['paid'] ?? false) === true
            || in_array($status, ['COMPLETED', 'PAID', 'SUCCESS', 'PAID_SUCCESS'], true);

        // Mirror the webhook's defence: if the provider echoes the settled amount/
        // currency, they must match the row this QR was minted for. A "paid" that
        // settled a different amount must never finalize a $500 subscription.
        if ($paid && ! $this->amountCurrencyMatches($row, $data)) {
            return false;
        }

        return $paid;
    }

    /**
     * True when the provider-reported amount/currency (if present) match the row.
     * Absent fields are treated as "can't contradict" → match, since some verify
     * responses omit them.
     */
    private function amountCurrencyMatches(KhqrPayment $row, array $data): bool
    {
        if (isset($data['amount']) && abs((float) $data['amount'] - (float) $row->amount) > 0.01) {
            Log::warning('KHQRPay verify amount mismatch', ['tran' => $row->transaction_id, 'got' => $data['amount'], 'expected' => $row->amount]);

            return false;
        }

        if (isset($data['currency']) && strtoupper((string) $data['currency']) !== strtoupper((string) $row->currency)) {
            Log::warning('KHQRPay verify currency mismatch', ['tran' => $row->transaction_id, 'got' => $data['currency'], 'expected' => $row->currency]);

            return false;
        }

        return true;
    }

    /**
     * Authenticate an inbound webhook payload against a SPECIFIC payment row:
     * the signature must verify with the secret of whoever the money settles to
     * (platform vs merchant), and the paid amount/currency must match the row —
     * a valid signature on a 0.01 payment must not finalize a $500 row.
     */
    public function isValidCallbackFor(KhqrPayment $row, array $payload): bool
    {
        $provided = $payload['hash'] ?? null;
        if (! $provided) {
            return false;
        }

        $creds = $this->credentialsFor($row);
        if ($creds === null || $creds->secret === '') {
            return false;
        }

        if (strtoupper((string) ($payload['status'] ?? '')) !== 'SUCCESS') {
            return false;
        }

        if (! hash_equals($this->signCallback($payload, $creds->secret), (string) $provided)) {
            return false;
        }

        if (isset($payload['transaction_id']) && (string) $payload['transaction_id'] !== (string) $row->transaction_id) {
            return false;
        }

        if (isset($payload['amount']) && abs((float) $payload['amount'] - (float) $row->amount) > 0.01) {
            Log::warning('KHQRPay callback amount mismatch', ['tran' => $row->transaction_id, 'got' => $payload['amount'], 'expected' => $row->amount]);

            return false;
        }

        if (isset($payload['currency']) && strtoupper((string) $payload['currency']) !== strtoupper((string) $row->currency)) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the signing credentials for a row from its settlement target.
     */
    public function credentialsFor(KhqrPayment $row): ?KhqrCredentials
    {
        if ($row->settlement_target !== 'merchant') {
            return KhqrCredentials::platform();
        }

        $rental = Rentals::withoutAccountScope()->find($row->rental_id);
        $settings = $rental ? MerchantPaymentSetting::forAccount($rental->account_id) : null;

        return ($settings && $settings->canUseApi()) ? KhqrCredentials::forMerchant($settings) : null;
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
     * Advance a row from a status poll: register the payer as waiting, confirm +
     * finalize if the money has arrived, else lazily expire a dead QR. One place
     * for the three checkout poll endpoints to call. Returns the fresh row.
     */
    public function pollAndAdvance(KhqrPayment $row): KhqrPayment
    {
        $this->markWaiting($row);

        // Verify FIRST so a payment that lands right at the deadline still wins.
        if (! $row->isPaid() && $this->verify($row)) {
            $this->finalize($row);

            return $row->refresh();
        }

        if ($this->expireIfElapsed($row)) {
            return $row->refresh();
        }

        return $row;
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

    /**
     * The KHQRPay headless QR API (JSON) endpoint for a profile:
     *   {baseUrl}/api/{profileId}/payment-gateway/v1/payments/qr-api-khqrcc
     * Returns the hosted QR image URL + provider ref (vs. the /purchase hosted
     * checkout page). Pinned to the row's own credentials by the caller.
     */
    private function qrApiEndpoint(KhqrCredentials $creds): string
    {
        return rtrim($creds->baseUrl, '/')
            .'/api/'.$creds->profileId
            .'/payment-gateway/v1/payments/qr-api-khqrcc';
    }

    private function qrTtlMinutes(): int
    {
        return max(1, (int) config('services.khqrpay.qr_ttl', 30));
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
            // Re-load under a lock so concurrent poll + webhook can't double-book.
            $locked = KhqrPayment::whereKey($row->getKey())->lockForUpdate()->first();
            if (! $locked || ! $locked->isOpen()) {
                return;
            }

            $period = FiscalPeriods::find($locked->fiscal_period_id);
            $rental = Rentals::with(['apartment', 'tenant'])->find($locked->rental_id);
            if (! $period || ! $rental) {
                Log::warning('KHQRPay finalize skipped: missing period/rental', ['tran' => $locked->transaction_id]);

                return;
            }

            $payload = $locked->checkout_payload;
            $payload['payment_method'] = 'khqr';
            $payload['transaction_reference'] = $locked->transaction_id;

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
                Log::warning('KHQRPay finalizeSubscription skipped: missing subscription', ['tran' => $locked->transaction_id]);

                return;
            }

            $days = $subscription->billing_cycle === 'yearly'
                ? 365
                : ($subscription->plan?->billing_period_days ?? 30);

            // Early renewals EXTEND the remaining time instead of resetting it.
            $base = ($subscription->expires_at !== null && $subscription->expires_at->isFuture() && $subscription->status !== 'trialing')
                ? $subscription->expires_at->copy()
                : now();

            $subscription->forceFill([
                'status' => 'active',
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

            // Actor is null here — activation runs from a webhook / poll / cron.
            app(\App\Services\Audit\AuditLogger::class)->record('subscription.activated', $subscription, [
                'transaction_id' => $locked->transaction_id,
                'plan' => $subscription->plan?->slug,
                'amount' => (float) $locked->amount,
                'currency' => $locked->currency,
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ]);
        });
    }

    /**
     * SHA1-sign the QR API request with the given secret.
     *
     * KHQRPay request signature is:
     *   sha1(secret . transaction_id . amount . success_url . remark)
     */
    private function sign(array $params, string $secret): string
    {
        $base = $secret
            .($params['transaction_id'] ?? '')
            .($params['amount'] ?? '')
            .($params['success_url'] ?? '')
            .($params['remark'] ?? '');

        return sha1($base);
    }

    /**
     * SHA256-sign the callback payload with the given secret.
     *
     * KHQRPay callback signature is:
     *   sha256(secret + req_time + transaction_id + amount + status)
     */
    private function signCallback(array $payload, string $secret): string
    {
        return hash('sha256',
            $secret
            .($payload['req_time'] ?? '')
            .($payload['transaction_id'] ?? '')
            .($payload['amount'] ?? '')
            .strtoupper((string) ($payload['status'] ?? ''))
        );
    }

    /**
     * Demo mode: render a local example KHQR instead of calling the live API.
     */
    private function fillDemo(KhqrPayment $row, float $amount): KhqrPayment
    {
        $row->forceFill([
            'qr_url' => $this->demoQrImageUrl($row->transaction_id, $amount),
            'provider_ref' => 'DEMO-'.$row->transaction_id,
        ]);
        $row->transitionTo(PaymentStatus::QrGenerated);
        $row->save();

        return $row;
    }

    /**
     * Build a QR image URL for an example KHQR (demo mode). Encodes a proper
     * EMVCo/Bakong KHQR payload (with CRC) and renders it through a public QR
     * image endpoint so no extra PHP dependency is needed.
     */
    private function demoQrImageUrl(string $transactionId, float $amount): string
    {
        $payload = $this->buildKhqrPayload($transactionId, $amount);

        return 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&ecc=M&data='.rawurlencode($payload);
    }

    /**
     * Compose an EMVCo-compliant Bakong KHQR string (individual, dynamic).
     * With a real Bakong account ID this is scannable + payable directly —
     * used for the merchant manual channel and for demo mode.
     */
    private function buildKhqrPayload(string $transactionId, float $amount, ?string $bakongId = null, ?string $merchantName = null, ?string $currency = null): string
    {
        $tlv = fn (string $id, string $val): string => $id.str_pad((string) strlen($val), 2, '0', STR_PAD_LEFT).$val;

        // Platform defaults: superadmin panel settings first, then .env config.
        $platform = \App\Models\PlatformPaymentSetting::current();
        $bakongId = (string) ($bakongId ?: $platform?->bakong_account_id ?: config('services.khqrpay.bakong_id') ?: 'demo@aclb');
        $merchant = substr((string) ($merchantName ?: $platform?->merchant_name ?: config('services.khqrpay.merchant_name') ?: 'AMS'), 0, 25);
        $currency = strtoupper((string) ($currency ?: $platform?->currency ?: config('services.khqrpay.currency', 'USD'))) === 'KHR' ? '116' : '840';

        // Tag 29: merchant account information (Bakong) — sub-tag 00 = Bakong account ID.
        $merchantAccount = $tlv('00', $bakongId);

        $payload = $tlv('00', '01')                       // payload format indicator
            .$tlv('01', '12')                             // dynamic QR
            .$tlv('29', $merchantAccount)                 // Bakong account info
            .$tlv('52', '5999')                           // merchant category code
            .$tlv('53', $currency)                        // transaction currency
            .$tlv('54', number_format($amount, 2, '.', '')) // amount
            .$tlv('58', 'KH')                             // country code
            .$tlv('59', $merchant)                        // merchant name
            .$tlv('60', 'Phnom Penh')                     // merchant city
            .$tlv('99', $tlv('00', substr($transactionId, 0, 25))); // additional data (bill no.)

        // Tag 63: CRC over everything including the "6304" prefix.
        $payload .= '6304';

        return $payload.strtoupper($this->crc16($payload));
    }

    private function buildQrRemark(KhqrPayment $row): string
    {
        return sprintf('KHQR rent payment %s', $row->transaction_id);
    }

    /** CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF) — the KHQR checksum. */
    private function crc16(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return str_pad(dechex($crc), 4, '0', STR_PAD_LEFT);
    }
}
