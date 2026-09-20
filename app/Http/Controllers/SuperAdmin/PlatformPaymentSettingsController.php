<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BakongToken;
use App\Models\PlatformPaymentSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Bakong\BakongPlatformIdentity;
use App\Services\Bakong\BakongProviderClient;
use App\Services\Bakong\BakongRuntimeConfig;
use App\Services\Bakong\BakongTokenService;
use App\Services\Bakong\BakongUsageReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The platform operator's payment identity for SUBSCRIPTION payments (Flow A),
 * and the meter for the token that takes them.
 *
 * ONE PROVIDER since 2026-09. The KHQRPay (khqr.cc) profile id and secret were
 * removed from this page along with the provider: leaving fields that configure
 * nothing is how an operator ends up carefully filling in a credential that
 * cannot be used, and a retired credential left sitting in the database is a
 * credential still worth stealing.
 *
 * WHAT IS HERE IS IDENTITY, NOT CREDENTIALS. The Bakong account id is printed
 * inside every QR the payer scans and the merchant name and city are shown in
 * their banking app — so they are safe to render back into the form, and they
 * belong somewhere the operator can read them. The ACCESS TOKEN deliberately is
 * not here: it is issued from a terminal, stored encrypted and never rendered
 * anywhere, because a token that passes through a browser, a form field or a
 * flash message is a token in somebody's scrollback.
 */
class PlatformPaymentSettingsController extends Controller
{
    public function edit(BakongUsageReport $usage): View
    {
        return view('superadmin.settings.payment', [
            'settings' => PlatformPaymentSetting::current(),
            // Shown so the operator can see what a blank field will fall back
            // to, rather than having to guess whether a server variable still
            // holds a value.
            'bakong' => BakongPlatformIdentity::current(),
            'bakongEnabled' => BakongProviderClient::featureEnabled(),
            'baseUrl' => BakongProviderClient::baseUrlForDisplay(),
            // Free: the ledger, the cache and the token's own JWT. Opening this
            // page costs no Bakong request, which is the point — see
            // BakongUsageReport.
            'usage' => $usage->build(),
            // What each blank field falls back to, so "empty" reads as
            // "inherits X" rather than as "off".
            'envDefaults' => BakongRuntimeConfig::envDefaults(),
        ]);
    }

    /**
     * The same report as JSON, so the meter can refresh itself without a page
     * reload. Still entirely offline: this endpoint cannot spend anything, so
     * unlike the KHQRPay diagnostics route it replaces there is no live/offline
     * distinction to get wrong and nothing to throttle it against.
     */
    public function usage(BakongUsageReport $usage): JsonResponse
    {
        return response()->json($usage->build());
    }

    public function update(Request $request, BakongTokenService $tokens, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'in:USD,KHR'],
            // The direct-Bakong payout identity. Not credentials — the account
            // id is printed inside every QR the payer scans — so unlike a
            // secret these are rendered back into the form.
            'bakong_account_id' => ['nullable', 'string', 'max:255'],
            // EMV caps these, and exceeding them makes the QR malformed rather
            // than merely long. Rejected here so the operator is told, instead
            // of silently truncated at build time.
            'merchant_name' => ['nullable', 'string', 'max:25'],
            'merchant_city' => ['nullable', 'string', 'max:15'],

            // ── the switch ──
            'bakong_enabled' => ['nullable', 'boolean'],

            // ── integrator identity ──
            // The email is renew_token's ENTIRE payload, so a typo here is
            // invisible for ninety days and then stops payments. It is
            // editable precisely because the person who read the verification
            // code out of the inbox is not the person with shell access.
            'bakong_email' => ['nullable', 'email', 'max:255'],
            'bakong_organization' => ['nullable', 'string', 'max:255'],
            'bakong_project' => ['nullable', 'string', 'max:255'],

            // ── quota guards ──
            // Bounded, because these are the only thing standing between a
            // busy day and errorCode 17. The upper bound on the daily limit is
            // NBC's own ~100: a local ceiling above it cannot bind, and an
            // operator who sets one has quietly disabled their own safety net.
            'bakong_daily_request_limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('bakong.upstream_daily_limit', 100)],
            // Must stay well above the browser poll interval (10s) or it
            // absorbs nothing and every poll becomes a metered request.
            'bakong_verify_cooldown' => ['nullable', 'integer', 'min:15', 'max:600'],
            'bakong_qr_ttl' => ['nullable', 'integer', 'min:1', 'max:60'],
            'bakong_max_verify_attempts' => ['nullable', 'integer', 'min:1', 'max:60'],
            'bakong_reconcile_enabled' => ['nullable', 'boolean'],
            // Renewal spends a request, so a window of days is a quota choice.
            // Floored at 1: renewing on the day of expiry leaves no room for a
            // failed attempt.
            'bakong_token_renew_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            // Display only — what the meter measures our ceiling against.
            'bakong_upstream_daily_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'bakong_failure_backoff' => ['nullable', 'integer', 'min:1', 'max:720'],
            'bakong_rate_limit_backoff' => ['nullable', 'integer', 'min:1', 'max:720'],
            'bakong_reconcile_grace' => ['nullable', 'integer', 'min:1', 'max:1440'],

            // ── the access token ──
            // The ONE credential on this page. Write-only: validated, handed
            // straight to the importer, and never rendered back. Blank means
            // "leave the stored token alone", so saving any other field does
            // not require re-pasting it.
            'bakong_token' => ['nullable', 'string'],
        ]);

        $settings = PlatformPaymentSetting::current() ?? new PlatformPaymentSetting;

        $settings->fill([
            'currency' => $validated['currency'],
            'bakong_account_id' => $validated['bakong_account_id'] ?? null,
            'merchant_name' => $validated['merchant_name'] ?? null,
            'merchant_city' => $validated['merchant_city'] ?? null,

            // A blank numeric or text field is stored as NULL, never 0 or '',
            // because null is what falls back to .env. Clearing a field here
            // means "stop overriding", not "set it to nothing" — see
            // BakongRuntimeConfig.
            'bakong_enabled' => $request->boolean('bakong_enabled'),
            'bakong_email' => $validated['bakong_email'] ?? null,
            'bakong_organization' => $validated['bakong_organization'] ?? null,
            'bakong_project' => $validated['bakong_project'] ?? null,
            'bakong_daily_request_limit' => $validated['bakong_daily_request_limit'] ?? null,
            'bakong_verify_cooldown' => $validated['bakong_verify_cooldown'] ?? null,
            'bakong_qr_ttl' => $validated['bakong_qr_ttl'] ?? null,
            'bakong_max_verify_attempts' => $validated['bakong_max_verify_attempts'] ?? null,
            'bakong_reconcile_enabled' => $request->boolean('bakong_reconcile_enabled'),
            'bakong_token_renew_days' => $validated['bakong_token_renew_days'] ?? null,
            'bakong_upstream_daily_limit' => $validated['bakong_upstream_daily_limit'] ?? null,
            'bakong_failure_backoff' => $validated['bakong_failure_backoff'] ?? null,
            'bakong_rate_limit_backoff' => $validated['bakong_rate_limit_backoff'] ?? null,
            'bakong_reconcile_grace' => $validated['bakong_reconcile_grace'] ?? null,
        ])->save();

        // AFTER the settings save, deliberately: importToken() stamps the row
        // with config('bakong.integrator.email'), and BakongRuntimeConfig has
        // just pushed the email saved above over .env. Importing first would
        // key the token to the OLD address and then look it up by the new one,
        // which is a token that exists and can never be found.
        if (filled($token = (string) $request->input('bakong_token'))) {
            BakongRuntimeConfig::apply();

            $result = $tokens->importToken($token);

            // Nothing about the token goes into the flash but its outcome. The
            // importer's own message is reused so the page and the command
            // cannot describe the same failure differently.
            if (! ($result['ok'] ?? false)) {
                return redirect()->route('superadmin.settings.payment')
                    ->withErrors(['bakong_token' => $result['message'] ?? __('messages.bakong_token_import_malformed')]);
            }

            // The VALUE is never logged — only that it changed, and the
            // fingerprint, which is what lets two reports be told apart
            // without putting a live credential in the audit table.
            $audit->record('bakong.token.imported', $settings, [
                'fingerprint' => BakongToken::current()?->fingerprint(),
                'source' => 'payment_settings',
            ]);
        }

        return redirect()->route('superadmin.settings.payment')
            ->with('success', __('messages.payment_settings_saved'));
    }
}
