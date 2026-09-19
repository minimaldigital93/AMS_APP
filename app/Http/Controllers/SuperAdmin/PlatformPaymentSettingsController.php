<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformPaymentSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The platform operator's payment identity for SUBSCRIPTION payments (Flow A),
 * for BOTH providers — self-service, no .env edit and no deploy needed.
 *
 * KHQRPay: Profile ID + Secret from the khqr.cc dashboard. Non-blank values
 * override config/services.khqrpay (see KhqrCredentials::platform()); the stored
 * secret is never rendered back — leaving the field blank keeps the existing one.
 *
 * Direct Bakong: the payout account id, merchant name and city that go INSIDE
 * every QR (see BakongPlatformIdentity). These are identity rather than
 * credentials — the payer reads them in their banking app — so they are shown
 * back in the form. The Bakong ACCESS TOKEN deliberately is not here: it is
 * issued from a terminal, stored encrypted and never rendered anywhere.
 */
class PlatformPaymentSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = PlatformPaymentSetting::current();

        return view('superadmin.settings.payment', [
            'settings' => $settings,
            'secretConfigured' => ($settings !== null && filled($settings->khqrpay_secret))
                || filled(config('services.khqrpay.secret')),
            // Shown so the operator can see what a blank field will fall back
            // to, rather than having to guess whether .env still holds a value.
            'bakong' => \App\Services\Bakong\BakongPlatformIdentity::current(),
            'bakongEnabled' => \App\Services\Bakong\BakongProviderClient::featureEnabled(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'khqrpay_profile_id' => ['nullable', 'string', 'max:255'],
            'khqrpay_secret' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'in:USD,KHR'],
            // The direct-Bakong payout identity. Not credentials — the account
            // id is printed inside every QR the payer scans — so unlike the
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
            'khqrpay_profile_id' => $validated['khqrpay_profile_id'] ?? null,
            'currency' => $validated['currency'],
            'bakong_account_id' => $validated['bakong_account_id'] ?? null,
            'merchant_name' => $validated['merchant_name'] ?? null,
            'merchant_city' => $validated['merchant_city'] ?? null,
        ]);

        // Blank secret = keep the existing one (it is never echoed to the form).
        if (filled($validated['khqrpay_secret'] ?? null)) {
            $settings->khqrpay_secret = $validated['khqrpay_secret'];
        }

        $settings->save();

        return redirect()->route('superadmin.settings.payment')
            ->with('success', __('messages.payment_settings_saved'));
    }
}
