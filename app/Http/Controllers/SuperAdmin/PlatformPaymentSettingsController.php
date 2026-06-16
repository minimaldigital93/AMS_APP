<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformPaymentSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The platform operator's KHQRPay credentials for SUBSCRIPTION payments
 * (Flow A): just the Profile ID + Secret from the khqr.cc dashboard, plus the
 * settlement currency — self-service, no .env edit needed. Non-blank values
 * override config/services.khqrpay (see KhqrCredentials::platform()); the stored
 * secret is never rendered back — leaving the field blank keeps the existing one.
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
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'khqrpay_profile_id' => ['nullable', 'string', 'max:255'],
            'khqrpay_secret' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'in:USD,KHR'],
        ]);

        $settings = PlatformPaymentSetting::current() ?? new PlatformPaymentSetting;

        $settings->fill([
            'khqrpay_profile_id' => $validated['khqrpay_profile_id'] ?? null,
            'currency' => $validated['currency'],
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
