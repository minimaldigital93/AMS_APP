<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformPaymentSetting;
use App\Services\Bakong\BakongPlatformIdentity;
use App\Services\Bakong\BakongProviderClient;
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

    public function update(Request $request): RedirectResponse
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
        ]);

        $settings = PlatformPaymentSetting::current() ?? new PlatformPaymentSetting;

        $settings->fill([
            'currency' => $validated['currency'],
            'bakong_account_id' => $validated['bakong_account_id'] ?? null,
            'merchant_name' => $validated['merchant_name'] ?? null,
            'merchant_city' => $validated['merchant_city'] ?? null,
        ])->save();

        return redirect()->route('superadmin.settings.payment')
            ->with('success', __('messages.payment_settings_saved'));
    }
}
