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
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * verifyOutcome() results. PAID and UNPAID are answers from the gateway;
     * REFUSED means there is no answer yet and nothing may be decided on it.
     */
    public const VERIFY_PAID = 'paid';

    public const VERIFY_UNPAID = 'unpaid';

    public const VERIFY_REFUSED = 'refused';

    /** Set by pollAndAdvance(); read by the poll endpoints via lastPollRefused(). */
    private bool $lastPollRefused = false;

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
     *
     * $plan/$cycle are what the customer is BUYING. They are carried on the
     * payment rather than written to the subscription up front, because an
     * upgrade must not take effect until the money lands: the plan's caps are
     * read straight off subscriptions.plan_id (SubscriptionService::usage()),
     * so a customer who picked a bigger plan and abandoned checkout would
     * otherwise hold the bigger caps for free until their current term ran out.
     * finalizeSubscription() is the one place that applies them. Omit both to
     * re-mint for whatever the subscription already says (the signup path,
     * where the row is still `pending` and grants nothing either way).
     */
    public function createSubscriptionQr(Subscription $subscription, float $amount, ?Plan $plan = null, ?string $cycle = null): KhqrPayment
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
            'checkout_payload' => [
                'type' => 'subscription',
                'subscription_id' => $subscription->id,
                'plan_id' => $plan?->id ?? $subscription->plan_id,
                'billing_cycle' => $cycle ?? $subscription->billing_cycle,
            ],
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
     * Preflight the PLATFORM gateway before handing a customer's browser to the
     * hosted checkout URL, and say WHY it can't be used.
     *
     * redirect()->away() is a one-way door. Once the browser is on khqr.cc, a
     * profile that cannot transact answers the hosted-checkout request with a
     * raw JSON body — {"responseCode":1,"responseMessage":"Bakong Token Required…"}
     * — and the customer is left staring at a JSON file on someone else's
     * domain, with no way for this app to say what happened or offer a retry. So
     * ask first, and keep them on our own page with a warning when the answer is
     * bad.
     *
     * TWO probes, because they answer different questions and only the second
     * one catches the failure above:
     *
     *  - the read-only check-transaction call (probeCheckTransaction) says
     *    whether the profile ANSWERS at all — wrong secret, wrong profile id,
     *    gateway down;
     *  - the handoff probe (probeHandoff) asks the hosted-checkout endpoint the
     *    customer is about to be sent to whether it will render a payment form
     *    or a JSON refusal. A profile can pass the first and fail the second:
     *    checking a transaction needs no Bakong token, taking money does. That
     *    gap is why customers still landed on the JSON page while this guard
     *    reported healthy.
     *
     * The handoff probe uses a THROWAWAY transaction id, never the customer's.
     * khqr.cc checkout sessions are single-use, so probing the row's own URL
     * would burn the session the customer is about to open (see
     * createSubscriptionQr) — that, and not the request itself, is what must
     * never be done. Its verdict is cached for minutes rather than seconds so a
     * burst of checkouts costs one throwaway session, not one each.
     *
     * It FAILS OPEN by design: only an answer that positively shows the profile
     * cannot transact is a fault. A timeout, a network blip or a plain
     * "transaction not found" (the healthy answer for the throwaway id we probe
     * with) all return null — blocking a working checkout on a flaky probe
     * would cost real money.
     *
     * Call it BEFORE minting anything: a refusal then leaves no half-finished
     * signup or orphan QR behind, the same as the missing-credentials guard.
     *
     * @return string|null human-readable reason, or null when checkout may proceed
     */
    public function platformCheckoutFault(): ?string
    {
        if (config('services.khqrpay.demo')) {
            return null;
        }

        $creds = KhqrCredentials::platform();
        if (! $creds->isConfigured()) {
            return __('messages.khqr_payment_settings_missing');
        }

        $healthyKey = 'khqr:platform:reachable:'.$creds->profileId;
        if (Cache::get($healthyKey)) {
            return null;
        }

        // A profile that refused a moment ago will almost certainly refuse the
        // next click, and every click costs TWO metered calls. Only a HEALTHY
        // verdict used to be cached, on the reasoning that a fault must re-probe
        // so a fixed profile works immediately — but the profile this app is
        // actually pointed at has been faulting since June, so in practice that
        // meant every visit to the billing page bought the same discovery again
        // and the preflight became a bigger drain than the payments it guards.
        // Held for the same 60 seconds as the healthy verdict, so a profile
        // fixed at khqr.cc still starts working almost at once — and the
        // diagnostics popup clears it outright, because that is the page an
        // operator is standing on while doing the fixing.
        $cachedFault = Cache::get(self::faultVerdictKey($creds));
        if (is_string($cachedFault)) {
            return $cachedFault;
        }

        // Never spend the last of a metered allowance on a health check. The two
        // probes are rated exactly like a verify, and a customer turned away by
        // a preflight that could not run is turned away for no reason — so this
        // fails OPEN, the same as every other unknown verdict here.
        if ($this->dailyBudgetExhausted('platform')) {
            return null;
        }

        $profile = $this->probeCheckTransaction($creds);
        if ($profile['outcome'] === 'fault') {
            $this->rememberCheckoutFault($creds, $profile);

            return $this->cacheFaultVerdict($creds, __('messages.subscription_gateway_unavailable'));
        }

        $handoff = $this->probeHandoff($creds);
        if ($handoff['outcome'] === 'fault') {
            $this->rememberCheckoutFault($creds, $handoff);

            return $this->cacheFaultVerdict($creds, __('messages.subscription_gateway_unavailable'));
        }

        // Only cache "healthy" when BOTH probes actually said so — an unknown
        // (timeout, blip) is let through but must not silence the next check.
        if ($profile['outcome'] === 'ok' && $handoff['outcome'] === 'ok') {
            Cache::put($healthyKey, true, now()->addSeconds(60));
        }

        return null;
    }

    /**
     * Stash the gateway's own words about the last refusal, for the diagnostics
     * popup to show.
     *
     * The customer gets one sentence; whoever has to FIX the profile needs the
     * status code and the verbatim responseMessage, and by the time they open
     * the popup the probe may well answer differently (or be served from the
     * healthy cache). Logged too — this is the short-term copy the UI reads.
     * Never cached under the HEALTHY key. A fault does get its own short-lived
     * verdict (cacheFaultVerdict) so a permanently-broken profile stops charging
     * two metered probes to every visitor — but diagnostics clears that one, so
     * a profile fixed at khqr.cc still proves itself on the next click.
     */
    private function rememberCheckoutFault(KhqrCredentials $creds, array $probe): void
    {
        Log::warning('KHQRPay checkout preflight failed', [
            'probe' => $probe['probe'] ?? null,
            'status' => $probe['status'] ?? null,
            'message' => $probe['message'] ?? null,
        ]);

        try {
            Cache::put(self::lastFaultKey($creds), [
                'probe' => $probe['probe'] ?? null,
                'status' => $probe['status'] ?? null,
                'message' => $probe['message'] ?? null,
                'at' => Carbon::now()->toIso8601String(),
            ], now()->addHours(6));
        } catch (\Throwable $e) {
            // Instrumentation only — never fail a checkout guard over the cache.
        }
    }

    /**
     * The last refusal the preflight recorded, if it is still remembered.
     *
     * The diagnostics popup re-probes live, but a gateway is allowed to answer
     * differently a minute later (and a healthy verdict is cached for a minute
     * either way). Without this, the report could come back all-green to
     * someone who is standing in front of the failure — so show what actually
     * blocked the last checkout alongside the fresh run.
     *
     * @return array{probe: ?string, status: ?int, message: ?string, at: ?string}|null
     */
    public function lastPlatformCheckoutFault(): ?array
    {
        try {
            $fault = Cache::get(self::lastFaultKey(KhqrCredentials::platform()));
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($fault) ? $fault : null;
    }

    /**
     * The short-lived "this profile just refused" verdict that spares the next
     * caller two metered probes. Separate from lastFaultKey(): that one is the
     * six-hour diagnostic record of WHAT refused and is only ever displayed;
     * this one is a 60-second gate that actually suppresses calls.
     */
    private static function faultVerdictKey(KhqrCredentials $creds): string
    {
        return 'khqr:platform:fault-verdict:'.$creds->profileId;
    }

    /** Remember a refusal briefly, and hand the reason straight back to the caller. */
    private function cacheFaultVerdict(KhqrCredentials $creds, string $reason): string
    {
        try {
            Cache::put(self::faultVerdictKey($creds), $reason, now()->addSeconds(60));
        } catch (\Throwable $e) {
            // Instrumentation only — never fail a checkout guard over the cache.
        }

        return $reason;
    }

    /**
     * Drop both cached verdicts so the very next checkout re-probes for real.
     *
     * Called when diagnostics has just run live probes: whoever is looking at
     * that report is mid-fix, and making them wait out a cache to find out
     * whether the fix took is the behaviour the fault cache must not introduce.
     */
    private function forgetCachedVerdicts(KhqrCredentials $creds): void
    {
        try {
            Cache::forget(self::faultVerdictKey($creds));
            Cache::forget('khqr:platform:reachable:'.$creds->profileId);
        } catch (\Throwable $e) {
            // Instrumentation only.
        }
    }

    private static function lastFaultKey(KhqrCredentials $creds): string
    {
        return 'khqr:platform:last-fault:'.$creds->profileId;
    }

    /**
     * Everything the diagnostics popup and `php artisan khqr:diagnose` show.
     *
     * One live run of the same probes the preflight uses, reported check by
     * check instead of collapsed into a single yes/no, plus the settings that
     * can only be verified by a human (the webhook URL has to be pasted into
     * the khqr.cc profile — nothing here can read it back, and when it is
     * missing the payment succeeds and the checkout page spins forever waiting
     * for a callback that never arrives).
     *
     * `detail` is TECHNICAL and may quote the gateway verbatim, so only show it
     * to an admin — never on the public signup page. The secret is never
     * included, only whether one is set.
     *
     * Each check also carries what to DO about it:
     *  - `remedy` — the fix for THIS failure in plain words. A refusal names one
     *    of four different jobs (add a credential, wait out an allowance, re-copy
     *    a secret, get a token activated at khqr.cc) and they are done in
     *    different places by different people, so one generic paragraph under
     *    the list left the reader to work out which one they were in.
     *  - `copy`  — a value that has to leave this screen intact: the webhook URL
     *    that must be pasted into the khqr.cc profile, and the support sentence
     *    for a refusal only khqr.cc can clear.
     *
     * @return array{healthy: bool, checks: array<int, array{key: string, label: string, state: string, detail: ?string, remedy: ?string, copy: ?string}>, checked_at: string}
     */
    public function platformDiagnostics(): array
    {
        $creds = KhqrCredentials::platform();
        $checks = [];
        $webhook = [
            'key' => 'webhook',
            'label' => __('messages.khqr_diag_webhook'),
            'state' => 'info',
            'detail' => route('khqr.callback'),
            'remedy' => __('messages.khqr_fix_webhook'),
            // Nothing here can read back what is actually in the khqr.cc
            // profile, so the most this can do is hand over the exact string.
            'copy' => route('khqr.callback'),
        ];

        if (config('services.khqrpay.demo')) {
            return [
                'healthy' => true,
                'checks' => [[
                    'key' => 'demo',
                    'label' => __('messages.khqr_diag_demo'),
                    'state' => 'warn',
                    'detail' => __('messages.khqr_diag_demo_detail'),
                    'remedy' => __('messages.khqr_fix_demo'),
                    'copy' => null,
                ]],
                'checked_at' => Carbon::now()->toIso8601String(),
            ];
        }

        // 1. Credentials. Name the blank field — "not configured" alone has sent
        //    people looking in .env, where these have not lived since the
        //    platform settings moved into the DB (see KhqrCredentials).
        $missing = array_keys(array_filter([
            __('messages.khqrpay_profile_id') => blank($creds->profileId),
            __('messages.khqrpay_secret') => blank($creds->secret),
            'base_url' => blank($creds->baseUrl),
        ]));
        $checks[] = [
            'key' => 'credentials',
            'label' => __('messages.khqr_diag_credentials'),
            'state' => $missing === [] ? 'ok' : 'fail',
            'detail' => $missing === []
                ? $creds->baseUrl.' · '.__('messages.khqrpay_profile_id').' '.$creds->profileId
                : __('messages.khqr_diag_credentials_missing', ['fields' => implode(', ', $missing)]),
            'remedy' => $missing === [] ? null : __('messages.khqr_fix_settings'),
            'copy' => null,
        ];

        // The allowance, before the probes rather than after: a spent token and
        // an unconfigured one produce similar-looking refusals below, and this
        // is the line that tells them apart without a second guess.
        $checks[] = $this->usageCheck();

        if ($missing !== []) {
            $checks[] = $webhook;

            return ['healthy' => false, 'checks' => $checks, 'checked_at' => Carbon::now()->toIso8601String()];
        }

        // The probes are metered exactly like a verify, so a report cannot be
        // worth two calls the app has already decided it cannot afford. The
        // usage check directly above has said the ceiling is reached, and that
        // IS the finding — running the probes anyway would spend the reserve on
        // re-confirming it, in the one situation where the reserve matters most.
        if ($this->dailyBudgetExhausted('platform')) {
            foreach ([
                ['profile', __('messages.khqr_diag_profile')],
                ['handoff', __('messages.khqr_diag_handoff')],
            ] as [$key, $label]) {
                $checks[] = [
                    'key' => $key,
                    'label' => $label,
                    'state' => 'warn',
                    'detail' => __('messages.khqr_diag_probe_skipped'),
                    'remedy' => __('messages.khqr_fix_quota'),
                    'copy' => null,
                ];
            }

            $checks[] = $webhook;

            return ['healthy' => false, 'checks' => $checks, 'checked_at' => Carbon::now()->toIso8601String()];
        }

        // What follows is a live reading, so it supersedes both cached verdicts.
        // Whoever is looking at this report is mid-fix, and the fault cache must
        // never make them wait a minute to find out whether the fix took.
        $this->forgetCachedVerdicts($creds);

        // 2 + 3. The two live probes, reported separately: answering a
        //        transaction query and being able to open a checkout are
        //        different permissions at the gateway.
        foreach ([
            ['profile', __('messages.khqr_diag_profile'), $this->probeCheckTransaction($creds)],
            ['handoff', __('messages.khqr_diag_handoff'), $this->probeHandoff($creds)],
        ] as [$key, $label, $probe]) {
            $checks[] = [
                'key' => $key,
                'label' => $label,
                'state' => match ($probe['outcome']) {
                    'ok' => 'ok',
                    'fault' => 'fail',
                    default => 'warn',
                },
                'detail' => $this->probeDetail($probe),
                'remedy' => $this->probeRemedy($probe, $creds),
                'copy' => $probe['outcome'] === 'fault' && $this->needsGatewayOperator($probe)
                    ? __('messages.khqr_diag_support_line', [
                        'profile' => $creds->profileId,
                        'status' => $probe['status'] ?? '—',
                        'message' => $probe['message'] ?: __('messages.khqr_diag_no_detail'),
                    ])
                    : null,
            ];
        }

        $checks[] = $webhook;

        return [
            'healthy' => ! collect($checks)->contains(fn (array $c) => $c['state'] === 'fail'),
            'checks' => $checks,
            'checked_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Today's spend against the ceiling, as a check of its own.
     *
     * Reported even when it is healthy, because it is the number that decides
     * how to read everything below it: a gateway refusing because the day's
     * allowance is gone and one refusing because it was never given a token
     * look almost identical in the probe output, and the remedies are nothing
     * alike — one resolves itself at midnight, the other never does.
     *
     * @return array{key: string, label: string, state: string, detail: string, remedy: ?string, copy: ?string}
     */
    private function usageCheck(): array
    {
        $budget = (int) config('services.khqrpay.daily_budget', 0);
        $spent = self::providerCallsOn('platform');

        if ($budget <= 0) {
            return [
                'key' => 'usage',
                'label' => __('messages.khqr_diag_usage'),
                'state' => 'warn',
                'detail' => __('messages.khqr_diag_usage_unlimited', ['spent' => $spent]),
                'remedy' => __('messages.khqr_fix_no_budget'),
                'copy' => null,
            ];
        }

        // 'fail' only when the ceiling is actually reached — this app has then
        // stopped calling out, which IS the refusal the reader is looking at.
        $state = match (true) {
            $spent >= $budget => 'fail',
            $spent >= (int) ceil($budget * 0.8) => 'warn',
            default => 'ok',
        };

        return [
            'key' => 'usage',
            'label' => __('messages.khqr_diag_usage'),
            'state' => $state,
            'detail' => __('messages.khqr_diag_usage_detail', ['spent' => $spent, 'budget' => $budget]),
            'remedy' => $state === 'ok' ? null : __('messages.khqr_fix_quota'),
            'copy' => null,
        ];
    }

    /**
     * What to DO about this probe's answer.
     *
     * Four different jobs hide behind "the gateway refused", and they are done
     * by different people in different places — so name the one that applies
     * instead of listing all four and leaving the reader to guess.
     */
    private function probeRemedy(array $probe, KhqrCredentials $creds): ?string
    {
        if ($probe['outcome'] === 'ok') {
            return null;
        }

        if ($probe['outcome'] === 'unknown') {
            return __('messages.khqr_fix_inconclusive');
        }

        $message = (string) ($probe['message'] ?? '');
        $status = $probe['status'] ?? null;

        // The allowance is spent: nothing to fix, and it clears on its own.
        if ($status === 429 || $this->isQuotaRefusal($message)) {
            return __('messages.khqr_fix_quota');
        }

        // The secret does not match the profile — the one failure that IS
        // fixable on our own settings page.
        if (in_array($status, [401, 403], true) || str_contains(strtolower($message), 'hash')) {
            return __('messages.khqr_fix_credentials');
        }

        return __('messages.khqr_fix_token', ['profile' => $creds->profileId]);
    }

    /**
     * True when clearing this refusal needs someone at khqr.cc rather than
     * anything on this side — which is when a verbatim sentence to send them is
     * worth putting on a copy button.
     */
    private function needsGatewayOperator(array $probe): bool
    {
        $message = (string) ($probe['message'] ?? '');
        $status = $probe['status'] ?? null;

        if ($status === 429 || $this->isQuotaRefusal($message)) {
            return false;
        }

        return ! in_array($status, [401, 403], true)
            && ! str_contains(strtolower($message), 'hash');
    }

    /** One readable line about a probe's answer: HTTP status plus the gateway's own words. */
    private function probeDetail(array $probe): string
    {
        $parts = array_filter([
            $probe['status'] ? 'HTTP '.$probe['status'] : null,
            $probe['message'] ?: null,
        ]);

        return $parts === [] ? __('messages.khqr_diag_no_detail') : implode(' · ', $parts);
    }

    /**
     * Probe 1 — can the profile answer a read-only transaction query?
     *
     * Uses a transaction id that deliberately does not exist at the gateway: we
     * are asking whether the PROFILE can answer at all, not about a payment.
     *
     * @return array{outcome: string, probe: string, status: ?int, message: string}
     */
    private function probeCheckTransaction(KhqrCredentials $creds): array
    {
        $endpoint = rtrim($creds->baseUrl, '/')
            .'/api/'.$creds->profileId
            .'/payment-gateway/v1/payments/check-transv2-khqrcc';

        $probe = 'PREFLIGHT-'.now()->format('YmdHis').'-'.random_int(100000, 999999);

        // Bakong rates this exactly like a verify. It went uncounted until
        // 2026-08, so `khqr:usage` under-reported every checkout attempt by two
        // and the daily ceiling — the one thing that stops the app spending a
        // dead allowance all day — could be sailed straight past by the probes
        // meant to protect it.
        $this->recordProviderCall('platform');

        try {
            $response = Http::asForm()->acceptJson()
                ->connectTimeout(3)->timeout(6)
                ->post($endpoint, [
                    'transaction_id' => $probe,
                    'hash' => sha1($creds->secret.$probe),
                ]);
        } catch (\Throwable $e) {
            // Unreachable ≠ misconfigured. Let the customer through; the hosted
            // page may well load for them even if our server-side call blipped.
            Log::warning('KHQRPay preflight unreachable', ['msg' => $e->getMessage()]);

            return ['outcome' => 'unknown', 'probe' => 'profile', 'status' => null, 'message' => $e->getMessage()];
        }

        $message = (string) ($response->json('responseMessage') ?? '');
        $out = fn (string $outcome) => [
            'outcome' => $outcome,
            'probe' => 'profile',
            'status' => $response->status(),
            'message' => $message ?: Str::limit($response->body(), 200),
        ];

        // khqr.cc answers a profile that isn't provisioned for live payments
        // with a 5xx (the 502 seen on this integration), and a wrong secret with
        // 401/403 ("Invalid Security Hash"). Those are exactly the states where
        // the hosted checkout renders a JSON body instead of a payment form.
        // 429 joins 401/403/5xx as a positive showing that the handoff cannot
        // work: the allowance is spent, so the hosted checkout will refuse the
        // customer exactly as it refused us.
        if ($response->serverError() || in_array($response->status(), [401, 403, 429], true)) {
            return $out('fault');
        }

        // A 404 is the everyday HEALTHY answer, not a fault: the probe id
        // deliberately does not exist at the gateway, so a correctly configured
        // profile replies 404 "Transaction Not Found". A bad profile id answers
        // 404 too ("Merchant Profile Not Found"), so this one is decided on the
        // MESSAGE, never the status. Counting every 404 as a fault blocked
        // checkout on a perfectly healthy profile — the exact false alarm this
        // guard exists to avoid, and the reason it is written to fail open.
        if ($response->status() === 404) {
            return $out($this->isBlockingRefusal($message) ? 'fault' : 'ok');
        }

        if (! $response->successful()) {
            return $out('unknown'); // anything else is ambiguous — fail open
        }

        $code = (int) ($response->json('responseCode') ?? 0);

        return $out($code !== 0 && $this->isBlockingRefusal($message) ? 'fault' : 'ok');
    }

    /**
     * Probe 2 — will the hosted checkout page actually render a payment form?
     *
     * This is the one that catches "Bakong Token Required": a profile can pass
     * probe 1 and still refuse to take money, and until this existed the only
     * thing that discovered it was the customer, on khqr.cc, reading JSON.
     *
     * Signed exactly like the real handoff (subscriptionCheckoutUrl) but with a
     * throwaway transaction id and a token amount, so the single-use session it
     * opens is one nobody will ever be sent to. A payment form answers as HTML;
     * a refusal answers as JSON with a non-zero responseCode — telling those two
     * apart IS the check.
     *
     * @return array{outcome: string, probe: string, status: ?int, message: string}
     */
    private function probeHandoff(KhqrCredentials $creds): array
    {
        if (! config('services.khqrpay.handoff_preflight')) {
            return ['outcome' => 'unknown', 'probe' => 'handoff', 'status' => null, 'message' => __('messages.khqr_diag_handoff_disabled')];
        }

        $probe = 'PREFLIGHT-'.now()->format('YmdHis').'-'.random_int(100000, 999999);
        $params = [
            'transaction_id' => $probe,
            'amount' => '0.01',
            'success_url' => route('login'),
            'remark' => 'preflight',
        ];
        $params['hash'] = $this->sign($params, $creds->secret);

        $url = rtrim($creds->baseUrl, '/').'/api/payment/request/'.$creds->profileId;

        // Counted for the same reason as probe 1 — and this is the expensive
        // half: it opens a real (throwaway) checkout session at the gateway.
        $this->recordProviderCall('platform');

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)->timeout(6)
                ->get($url, $params);
        } catch (\Throwable $e) {
            Log::warning('KHQRPay handoff preflight unreachable', ['msg' => $e->getMessage()]);

            return ['outcome' => 'unknown', 'probe' => 'handoff', 'status' => null, 'message' => $e->getMessage()];
        }

        $body = $response->json();
        $message = is_array($body) ? (string) ($body['responseMessage'] ?? '') : '';
        $out = fn (string $outcome) => [
            'outcome' => $outcome,
            'probe' => 'handoff',
            'status' => $response->status(),
            'message' => $message ?: Str::limit(strip_tags($response->body()), 200),
        ];

        if ($response->serverError() || in_array($response->status(), [401, 403, 429], true)) {
            return $out('fault');
        }

        // A JSON body here is the failure mode itself: the endpoint that should
        // have rendered a payment form answered with a refusal envelope. That is
        // literally what the customer would have been shown.
        if (is_array($body) && (int) ($body['responseCode'] ?? 0) !== 0) {
            return $out('fault');
        }

        if (! $response->successful()) {
            return $out('unknown');
        }

        return $out('ok');
    }

    /**
     * Does this gateway refusal describe the PROFILE rather than the payment?
     *
     * The everyday non-zero answer is "transaction not found" — the transaction
     * genuinely doesn't exist at the gateway until the payer opens the checkout
     * page, so it must never read as a fault. Only messages that name a
     * credential, token or profile problem do, because those are the ones that
     * will still be true when the browser arrives.
     */
    private function isConfigurationRefusal(string $message): bool
    {
        return $this->messageMentions($message, [
            'token', 'configur', 'unauthor', 'hash', 'profile', 'permission', 'disabled', 'suspend', 'credential',
        ]);
    }

    /**
     * Does this refusal say the metered allowance is spent?
     *
     * A profile that is perfectly configured still cannot take money once the
     * upstream Bakong token is over its daily request limit, and that refusal
     * looks nothing like a credential problem — so isConfigurationRefusal()
     * misses it and the preflight used to fail open and hand the customer to a
     * JSON error page anyway.
     *
     * The needles are deliberately PHRASES, not the bare word "limit". The
     * handoff probe asks for a 0.01 amount, and a gateway answering "amount
     * below minimum limit" describes that probe's token amount, not the
     * profile — matching it would block every checkout on a healthy account,
     * which is exactly the false alarm this guard is written to avoid.
     */
    private function isQuotaRefusal(string $message): bool
    {
        return $this->messageMentions($message, [
            'rate limit', 'ratelimit', 'daily limit', 'request limit', 'limit exceeded',
            'exceeded limit', 'exceeds limit', 'out of limit', 'over limit', 'limit reached',
            'quota', 'too many request', 'throttl',
        ]);
    }

    /**
     * Will this refusal still be true when the customer's browser arrives?
     *
     * Both a credential fault and a spent allowance answer yes, and both are
     * grounds to keep the customer on our own page. A routine "transaction not
     * found" answers no.
     */
    private function isBlockingRefusal(string $message): bool
    {
        return $this->isConfigurationRefusal($message) || $this->isQuotaRefusal($message);
    }

    /** Case-insensitive substring match of any needle in the gateway's message. */
    private function messageMentions(string $message, array $needles): bool
    {
        $haystack = strtolower($message);

        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
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
        return $this->verifyOutcome($row) === self::VERIFY_PAID;
    }

    /**
     * The same question as verify(), with the answer the boolean cannot carry:
     * did the gateway actually ANSWER?
     *
     * verify() collapses everything that is not a confirmed payment into false,
     * and every caller reads that false as "the payer has not paid". For a 200
     * saying "transaction not found" that is correct. For a refusal — a spent
     * Bakong allowance, a 5xx, a timeout — it is a guess, and it is the
     * expensive kind: the money may well have landed, and a row expired on that
     * guess is a payment written out of the books. Over-limit makes it the
     * NORMAL answer rather than the rare one, which is how a quota problem
     * turns into a money problem.
     *
     * So there are three outcomes, and only one of them is evidence:
     *
     *  - VERIFY_PAID     the gateway confirmed the money, amount and currency;
     *  - VERIFY_UNPAID   the gateway answered, and the payment has not settled;
     *  - VERIFY_REFUSED  no verdict — never settle, never expire, try again.
     *
     * Callers that only settle on success can keep using verify(); anything
     * that would act on a NEGATIVE (expiring a row, giving up on it) must use
     * this and treat a refusal as "ask again later".
     */
    public function verifyOutcome(KhqrPayment $row): string
    {
        if ($row->isPaid()) {
            return self::VERIFY_PAID;
        }

        // Terminal rows (failed/expired/cancelled/refunded/rejected) never settle.
        if (! $row->isOpen()) {
            return self::VERIFY_UNPAID;
        }

        if ($row->channel === 'manual') {
            return self::VERIFY_UNPAID;
        }

        // Demo mode: auto-confirm a few seconds after the QR is generated so the
        // full scan → waiting → paid → record flow can be demonstrated end-to-end.
        if (config('services.khqrpay.demo')) {
            return $row->created_at !== null && $row->created_at->diffInSeconds(now()) >= 8
                ? self::VERIFY_PAID
                : self::VERIFY_UNPAID;
        }

        // Cooldown: a public status poll fires every few seconds — never make a
        // live provider call more than once per verify_cooldown window. The last
        // result is cached, so a confirmed payment is still seen promptly. A
        // refusal is cached too: when the allowance is spent, every poll would
        // otherwise spend another request discovering the same thing.
        //
        // Keyed apart from the old boolean cache ('khqr:verify:…') so a value
        // written by the previous release is never read back as an outcome.
        $cooldownKey = 'khqr:verify:outcome:'.$row->transaction_id;
        $cached = Cache::get($cooldownKey);
        if (is_string($cached)) {
            return $cached;
        }

        $outcome = $this->queryProviderOutcome($row);
        Cache::put($cooldownKey, $outcome, now()->addSeconds((int) config('services.khqrpay.verify_cooldown', 4)));

        return $outcome;
    }

    /**
     * Has this settlement target spent its allowance of live provider calls for
     * the day?
     *
     * Counted per target, because platform rows spend the SaaS operator's
     * Bakong token and merchant rows spend the individual landlord's — one
     * landlord's busy collection day must not lock out everyone else.
     *
     * Cache-backed like the counter it reads, and fails OPEN on a cache error:
     * a broken cache must not stop a real payment from being confirmed.
     */
    private function dailyBudgetExhausted(?string $target): bool
    {
        $budget = (int) config('services.khqrpay.daily_budget', 0);
        if ($budget <= 0) {
            return false;
        }

        return self::providerCallsOn($target ?? 'unknown') >= $budget;
    }

    /**
     * Live provider call: ask KHQRPay whether the transaction has settled. Pinned
     * to the row's own credentials, with the same amount/currency defence the
     * webhook applies. Any error / non-success / mismatch reads as "unpaid".
     */
    /**
     * Bakong meters this app's token per calendar day, so the only number that
     * matters operationally is "how many live provider calls have we spent
     * today". Nothing else records it: a successful verify writes no log line,
     * and only refusals are logged (latched to one line per transaction), so a
     * busy day can leave no trace at all in laravel.log.
     *
     * Counted per settlement target as well as in total, because the two draw on
     * different credentials — platform rows spend the SaaS operator's token,
     * merchant rows spend the individual landlord's — and a shared total cannot
     * say which one is running out.
     *
     * Best-effort by design: this is instrumentation wrapped around a payment
     * check, and a cache hiccup must never turn a working verify into a failed
     * one. Same rule AuditLogger follows.
     */
    private function recordProviderCall(?string $target = null): void
    {
        $target ??= 'unknown';

        foreach ([self::usageKey(), self::usageKey($target)] as $key) {
            try {
                // add() only writes when the key is absent, so it seeds the
                // counter without clobbering a concurrent increment. The database
                // cache store's increment() is a no-op on a missing key, which is
                // why seeding cannot be skipped.
                Cache::add($key, 0, now()->addDays(3));
                Cache::increment($key);
            } catch (\Throwable $e) {
                // Deliberately swallowed — see the docblock.
            }
        }
    }

    /**
     * Live provider calls spent on the given day (default today), optionally for
     * one settlement target. Surfaced by `php artisan khqr:usage`.
     */
    public static function providerCallsOn(?string $target = null, ?Carbon $day = null): int
    {
        try {
            return (int) Cache::get(self::usageKey($target, $day), 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function usageKey(?string $target = null, ?Carbon $day = null): string
    {
        $date = ($day ?? Carbon::now())->format('Y-m-d');

        return $target === null
            ? "khqr:calls:{$date}"
            : "khqr:calls:{$date}:{$target}";
    }

    private function queryProviderOutcome(KhqrPayment $row): string
    {
        $creds = $this->credentialsFor($row);
        if ($creds === null) {
            return self::VERIFY_REFUSED;
        }

        // Stop BEFORE the call, not after: a request refused for being over the
        // limit is charged to the allowance exactly like one that answers, so an
        // exhausted token spends the rest of the day discovering it is
        // exhausted. Refusing locally keeps whatever is left for a payment that
        // can still be confirmed, and reads as REFUSED — never as "unpaid" —
        // so nothing is settled or expired while we are flying blind.
        if ($this->dailyBudgetExhausted($row->settlement_target)) {
            $this->logBudgetExhausted($row);

            return self::VERIFY_REFUSED;
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

        // Retry ONLY a failed connection, never an HTTP error response. Laravel's
        // retry() treats any non-2xx as retryable by default (PendingRequest
        // rethrows the response when no `when` callback is given), and `throw:
        // false` suppresses the final exception WITHOUT suppressing that retry —
        // so a gateway answering 502 (the unprovisioned-profile signature here)
        // silently cost two Bakong requests per verify instead of one. This poll
        // runs every few seconds for a QR's whole lifetime against a token with a
        // hard daily request quota, so doubling it is the difference between a
        // working checkout and a quota that is empty by mid-morning. A refusal is
        // already read as "unpaid" below; only a connection blip is worth a second
        // attempt.
        // Count it BEFORE the call, not after: a request that times out or is
        // refused still spent the quota. The Bakong token is metered per day and
        // a successful verify logs nothing, so without this the only record that
        // a request happened is on the provider's side — which is why working out
        // where a day's quota went once took a code audit instead of a query.
        $this->recordProviderCall($row->settlement_target);

        try {
            $response = Http::asForm()->acceptJson()
                ->connectTimeout(3)->timeout(8)
                ->retry(2, 200, when: fn ($e) => $e instanceof ConnectionException, throw: false)
                ->post($endpoint, $params);
        } catch (\Throwable $e) {
            Log::warning('KHQRPay verify error', ['tran' => $row->transaction_id, 'msg' => $e->getMessage()]);

            // A transport failure is not a statement about the payment.
            return self::VERIFY_REFUSED;
        }

        // Only a 2xx is the gateway answering the question. 429 (allowance
        // spent), 401/403 (credentials) and 5xx (profile not provisioned — the
        // 502 this integration returns) all describe OUR access, not the
        // payer's money, and used to be indistinguishable from an honest
        // "not paid yet".
        if (! $response->successful()) {
            $this->logVerifyRefusal($row, $response->status(), (string) ($response->json('responseMessage') ?? ''));

            return self::VERIFY_REFUSED;
        }

        $body = $response->json() ?? [];

        // A non-zero responseCode is normally "transaction not found yet" — the
        // honest pre-payment answer, and genuinely unpaid. A refusal that names
        // a spent allowance or a credential problem is not: the gateway is
        // declining to look, so it says nothing about the money.
        if (isset($body['responseCode']) && (int) $body['responseCode'] !== 0) {
            $message = (string) ($body['responseMessage'] ?? '');
            $this->logVerifyRefusal($row, (int) $body['responseCode'], $message);

            return $this->isBlockingRefusal($message) ? self::VERIFY_REFUSED : self::VERIFY_UNPAID;
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
            return self::VERIFY_UNPAID;
        }

        return $paid ? self::VERIFY_PAID : self::VERIFY_UNPAID;
    }

    /**
     * Say once per target per day that the allowance ran out.
     *
     * Latched, because the poll would otherwise write a line every few seconds
     * for the rest of the day — and this is the one message that has to be
     * findable in the log, since from the outside a spent allowance looks
     * exactly like a building full of tenants who have not paid.
     */
    private function logBudgetExhausted(KhqrPayment $row): void
    {
        $target = $row->settlement_target ?? 'unknown';
        $latch = 'khqr:budget:logged:'.$target.':'.Carbon::now()->format('Y-m-d');

        try {
            if (! Cache::add($latch, true, now()->addDay())) {
                return;
            }
        } catch (\Throwable $e) {
            // Instrumentation only — never fail a verify over the cache.
        }

        Log::warning('KHQRPay daily request budget exhausted — not calling the gateway', [
            'target' => $target,
            'budget' => (int) config('services.khqrpay.daily_budget', 0),
            'spent' => self::providerCallsOn($target),
        ]);
    }

    /**
     * Record WHY the provider refused to confirm a transaction.
     *
     * The status poll runs every few seconds for the QR's whole lifetime, and
     * "transaction not found yet" is the normal pre-payment answer — logging
     * every refusal would write hundreds of lines per QR. So each distinct
     * (transaction, responseCode) pair is logged once and then latched for the
     * QR's lifetime. That is enough to see a configuration refusal (a lapsed
     * Bakong OpenAPI token answers every poll with the same non-zero code, so
     * without this the QR just expires with no trace) while a routine
     * not-found costs exactly one line.
     */
    private function logVerifyRefusal(KhqrPayment $row, int $code, string $message): void
    {
        $latch = 'khqr:verify:refused:'.$row->transaction_id.':'.$code;
        if (! Cache::add($latch, true, now()->addMinutes($this->qrTtlMinutes()))) {
            return;
        }

        Log::warning('KHQRPay verify refused', [
            'tran' => $row->transaction_id,
            'target' => $row->settlement_target,
            'code' => $code,
            'message' => $message,
        ]);
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
        $this->lastPollRefused = false;

        // Verify FIRST so a payment that lands right at the deadline still wins.
        $outcome = $row->isPaid() ? self::VERIFY_PAID : $this->verifyOutcome($row);

        if ($outcome === self::VERIFY_PAID) {
            $this->finalize($row);

            return $row->refresh();
        }

        // A refusal is not a verdict, so it must not close the row. Expiring on
        // it would end a QR the payer may already have paid, on the strength of
        // a gateway that declined to answer — and once the row is terminal,
        // verify() short-circuits and the safety net never looks again. Leave it
        // open, tell the page the gateway is unwell, and ask again next tick.
        if ($outcome === self::VERIFY_REFUSED) {
            $this->lastPollRefused = true;

            return $row;
        }

        if ($this->expireIfElapsed($row)) {
            return $row->refresh();
        }

        return $row;
    }

    /**
     * Did the last pollAndAdvance() fail to get a verdict out of the gateway?
     *
     * The three checkout pages already know how to say "we cannot reach the
     * gateway" — they warn beside the spinner after two consecutive bad polls
     * and keep polling, because the payment can still land. Until now only a
     * thrown exception could set that off, so a gateway refusing every request
     * looked identical to a payer who simply had not paid yet, and the customer
     * watched a silent spinner until the QR died.
     */
    public function lastPollRefused(): bool
    {
        return $this->lastPollRefused;
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

            // NotInClosedMonth was validated when the QR was generated — but a
            // webhook can land after the month has since been closed, and a
            // backdated ledger row would silently desync the frozen totals.
            // Re-date the booking to today instead (withoutAccountScope: this
            // runs from a webhook/cron with no authenticated user, and the
            // account scope must not leak another account's months in).
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
                Log::warning('KHQRPay finalizeSubscription skipped: missing subscription', ['tran' => $locked->transaction_id]);

                return;
            }

            // The plan/cycle the customer actually PAID for, carried on the
            // payment row (see createSubscriptionQr). An upgrade lands HERE and
            // nowhere else, so an abandoned checkout leaves the live plan alone.
            // Falls back to the subscription for rows minted before this field
            // existed, and for a plan deleted between minting and payment.
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

            // Actor is null here — activation runs from a webhook / poll / cron.
            app(\App\Services\Audit\AuditLogger::class)->record('subscription.activated', $subscription, [
                'transaction_id' => $locked->transaction_id,
                'plan' => $plan?->slug, // the purchased plan; ->plan is stale after the forceFill
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
