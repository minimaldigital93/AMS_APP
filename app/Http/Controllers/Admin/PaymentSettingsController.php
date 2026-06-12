<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantPaymentSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The landlord's payment destination for tenant rent (Flow B): bank details +
 * static KHQR image for the manual channel, optional KHQRPay API credentials
 * for the auto-verified dynamic-QR channel. The stored KHQRPay secret is never
 * rendered back — leaving the field blank on update keeps the existing one.
 */
class PaymentSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = MerchantPaymentSetting::forAccount(current_account_id());

        return view('admin.settings.payment', [
            'settings' => $settings,
            'secretConfigured' => $settings !== null && filled($settings->khqrpay_secret),
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
            'khqrpay_enabled' => ['nullable', 'boolean'],
            'khqrpay_profile_id' => ['nullable', 'string', 'max:255'],
            'khqrpay_secret' => ['nullable', 'string', 'max:255'],
            'bakong_account_id' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'in:USD,KHR'],
        ]);

        $accountId = current_account_id();
        $settings = MerchantPaymentSetting::forAccount($accountId)
            ?? new MerchantPaymentSetting(['account_id' => $accountId]);
        $settings->account_id = $accountId;

        $settings->fill([
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_name' => $validated['bank_account_name'] ?? null,
            'bank_account_number' => $validated['bank_account_number'] ?? null,
            'khqrpay_enabled' => (bool) ($validated['khqrpay_enabled'] ?? false),
            'khqrpay_profile_id' => $validated['khqrpay_profile_id'] ?? null,
            'bakong_account_id' => $validated['bakong_account_id'] ?? null,
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
            $settings->khqr_image_path = $request->file('khqr_image')->store('khqr/'.$accountId, 'public');
        }

        $settings->save();

        return redirect()->route('admin.settings.payment')
            ->with('success', __('messages.payment_settings_saved'));
    }
}
