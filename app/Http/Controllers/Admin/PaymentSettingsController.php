<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantPaymentSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentSettingsController extends Controller
{
    /**
     * Show the merchant credentials form.
     */
    public function edit(): View
    {
        $settings = MerchantPaymentSetting::forAccount(current_account_id());

        return view('admin.settings.payment', [
            'settings' => $settings,
            'secretConfigured' => $settings !== null && filled($settings->khqrpay_secret),
        ]);
    }

    /**
     * Save the credentials. A blank secret keeps the stored one.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'khqrpay_enabled' => ['nullable', 'boolean'],
            'khqrpay_profile_id' => ['nullable', 'string', 'max:255'],
            'khqrpay_secret' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'in:USD,KHR'],
        ]);

        $accountId = current_account_id();
        $settings = MerchantPaymentSetting::forAccount($accountId)
            ?? new MerchantPaymentSetting(['account_id' => $accountId]);
        $settings->account_id = $accountId;

        $settings->fill([
            'khqrpay_enabled' => (bool) ($validated['khqrpay_enabled'] ?? false),
            'khqrpay_profile_id' => $validated['khqrpay_profile_id'] ?? null,
            'currency' => $validated['currency'],
        ]);

        // Blank secret = keep the existing one (it is never echoed to the form).
        if (filled($validated['khqrpay_secret'] ?? null)) {
            $settings->khqrpay_secret = $validated['khqrpay_secret'];
        }

        $settings->save();

        return redirect()->route('admin.settings.payment')
            ->with('success', __('messages.payment_settings_saved'));
    }
}
