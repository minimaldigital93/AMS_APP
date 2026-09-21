<?php

namespace App\Services\Bakong;

use App\Models\BakongToken;
use App\Models\MerchantPaymentSetting;
use Carbon\Carbon;

/**
 * A LANDLORD's own Bakong access token — the credential that lets their
 * tenants' rent payments confirm themselves.
 *
 * Deliberately separate from `bakong_tokens`, which holds the ONE platform
 * credential keyed by the integrator email. The two are different money and
 * different parties: the platform token verifies subscriptions paid to the
 * platform operator, this one verifies rent paid into the landlord's own bank.
 * Keeping them in separate tables is what stops a lookup ever returning the
 * wrong party's credential, which on a payment path is unrecoverable.
 *
 * JWT reading is NOT reimplemented here — BakongToken owns that, and it stays
 * the single thing in this app that understands the shape of a Bakong token.
 *
 * The token is write-only from the operator's side: encrypted at rest, never
 * rendered back into the form, and reported only as a fingerprint plus an
 * expiry. Importing costs nothing and contacts nobody.
 */
class MerchantBakongCredentials
{
    /**
     * Store a pasted token against an account, after the same checks the
     * platform importer makes. Offline — no request is made.
     *
     * @return array{ok: bool, message: string}
     */
    public function import(int $accountId, string $token): array
    {
        $token = trim($token, " \t\n\r\0\x0B\"'");

        if ($token === '') {
            return $this->fail(__('messages.bakong_token_import_empty'));
        }

        if (substr_count($token, '.') !== 2) {
            return $this->fail(__('messages.bakong_token_import_malformed'));
        }

        $expiry = BakongToken::expiryFromJwt($token);

        // Storing a dead token leaves a row that claims to be configured while
        // every request refuses for no_token — the confusing half-state the
        // platform importer refuses for the same reason.
        if ($expiry !== null && $expiry->isPast()) {
            return $this->fail(__('messages.bakong_token_import_expired', [
                'date' => $expiry->toDayDateTimeString(),
            ]));
        }

        $settings = MerchantPaymentSetting::forAccountOrNew($accountId);

        $settings->forceFill([
            'bakong_token' => $token,
            'bakong_token_expires_at' => $expiry,
            'bakong_token_imported_at' => now(),
        ])->save();

        return [
            'ok' => true,
            'message' => $expiry === null
                ? __('messages.bakong_token_imported_no_expiry')
                : __('messages.bakong_token_imported', ['date' => $expiry->toDayDateTimeString()]),
        ];
    }

    /**
     * The token to put in an Authorization header for this account, or null.
     *
     * Null is returned for "switched off", "never set" and "expired" alike:
     * every one of them means there is no credential to spend, and the caller's
     * job is the same in all three cases — refuse BEFORE the request, because
     * a refused Bakong request is metered exactly like a successful one.
     */
    public function tokenFor(int $accountId): ?string
    {
        $settings = MerchantPaymentSetting::forAccount($accountId);

        if (! $settings || ! $this->enabledFor($accountId)) {
            return null;
        }

        $token = (string) ($settings->bakong_token ?? '');

        if ($token === '') {
            return null;
        }

        // A null expiry means "could not be decoded", which stays usable — the
        // same reading BakongToken::isUsable() applies to the platform token.
        if ($settings->bakong_token_expires_at instanceof Carbon
            && $settings->bakong_token_expires_at->isPast()) {
            return null;
        }

        return $token;
    }

    /** Has this landlord switched self-confirmation on? */
    public function enabledFor(int $accountId): bool
    {
        return (bool) (MerchantPaymentSetting::forAccount($accountId)?->bakong_enabled ?? false);
    }

    /**
     * Why isn't this landlord's auto-confirm live right now — if it isn't?
     *
     * `statusFor()` reports the raw settings; this reads them the way
     * `BakongProviderClient::call()`'s gates would, in the same order, so the
     * reason given here is the reason a real verification attempt would be
     * refused. A refusal with no visible cause is exactly what sent the
     * landlord's-own-token design its "why is this still manual" support
     * question in the first place — most of the causes ARE visible on this
     * page already (no token, unchecked box, expired badge), but the platform
     * master switch is not: a landlord can do everything right here and still
     * get nothing, with nothing on THIS page to say why.
     *
     * @return array{active: bool, reason: string}
     */
    public function diagnose(int $accountId): array
    {
        $status = $this->statusFor($accountId);

        if (! $status['configured']) {
            return ['active' => false, 'reason' => 'not_configured'];
        }

        if (! $status['enabled']) {
            return ['active' => false, 'reason' => 'not_enabled'];
        }

        if ($status['expired']) {
            return ['active' => false, 'reason' => 'expired'];
        }

        // Same order BakongProviderClient::call() checks them in: the master
        // switch before demo, because demo can only matter once the switch is
        // already on.
        if (! (bool) config('bakong.enabled')) {
            return ['active' => false, 'reason' => 'platform_disabled'];
        }

        if ((bool) config('bakong.demo')) {
            return ['active' => false, 'reason' => 'demo_mode'];
        }

        return ['active' => true, 'reason' => 'active'];
    }

    /**
     * What the settings page prints. Never the value — a fingerprint is enough
     * to tell two credentials apart without putting a live one on a screen or
     * in an audit row.
     *
     * @return array{configured: bool, enabled: bool, fingerprint: ?string, expires_at: ?Carbon, expired: bool, imported_at: ?Carbon}
     */
    public function statusFor(int $accountId): array
    {
        $settings = MerchantPaymentSetting::forAccount($accountId);
        $token = (string) ($settings->bakong_token ?? '');
        $expiry = $settings?->bakong_token_expires_at;

        return [
            'configured' => $token !== '',
            'enabled' => (bool) ($settings->bakong_enabled ?? false),
            'fingerprint' => $token === '' ? null : substr(hash('sha256', $token), 0, 12),
            'expires_at' => $expiry,
            'expired' => $expiry instanceof Carbon && $expiry->isPast(),
            'imported_at' => $settings?->bakong_token_imported_at,
        ];
    }

    /** Remove the credential without touching the landlord's payout identity. */
    public function forget(int $accountId): void
    {
        $settings = MerchantPaymentSetting::forAccount($accountId);

        $settings?->forceFill([
            'bakong_token' => null,
            'bakong_token_expires_at' => null,
            'bakong_token_imported_at' => null,
            'bakong_enabled' => false,
        ])->save();
    }

    /** @return array{ok: false, message: string} */
    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
