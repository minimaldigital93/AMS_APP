<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformPaymentSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The platform operator's payment destination for SUBSCRIPTION payments
 * (Flow A): bank details, KHQR image, Bakong ID and KHQRPay API credentials —
 * self-service, no .env edit needed. Non-blank values override
 * config/services.khqrpay (see KhqrCredentials::platform()); the stored secret
 * is never rendered back — leaving the field blank keeps the existing one.
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
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'khqr_image' => ['nullable', 'image', 'max:2048'],
            'remove_khqr_image' => ['nullable', 'boolean'],
            'khqrpay_profile_id' => ['nullable', 'string', 'max:255'],
            'khqrpay_secret' => ['nullable', 'string', 'max:255'],
            'bakong_account_id' => ['nullable', 'string', 'max:255'],
            'merchant_name' => ['nullable', 'string', 'max:25'],
            'currency' => ['required', 'in:USD,KHR'],
        ]);

        $settings = PlatformPaymentSetting::current() ?? new PlatformPaymentSetting;

        $settings->fill([
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_name' => $validated['bank_account_name'] ?? null,
            'bank_account_number' => $validated['bank_account_number'] ?? null,
            'khqrpay_profile_id' => $validated['khqrpay_profile_id'] ?? null,
            'bakong_account_id' => $validated['bakong_account_id'] ?? null,
            'merchant_name' => $validated['merchant_name'] ?? null,
            'currency' => $validated['currency'],
        ]);

        // Blank secret = keep the existing one (it is never echoed to the form).
        if (filled($validated['khqrpay_secret'] ?? null)) {
            $settings->khqrpay_secret = $validated['khqrpay_secret'];
        }

        if ($request->boolean('remove_khqr_image') && $settings->khqr_image_path) {
            Storage::disk('public')->delete($settings->khqr_image_path);
            $settings->khqr_image_path = null;
        }

        if ($request->hasFile('khqr_image')) {
            if ($settings->khqr_image_path) {
                Storage::disk('public')->delete($settings->khqr_image_path);
            }
            $settings->khqr_image_path = $request->file('khqr_image')->store('khqr/platform', 'public');
        }

        $settings->save();

        return redirect()->route('superadmin.settings.payment')
            ->with('success', __('messages.payment_settings_saved'));
    }
}
