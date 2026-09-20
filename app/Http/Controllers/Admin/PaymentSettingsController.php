<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantPaymentSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where a landlord's RENT money lands — their own Bakong account and bank
 * details. Rent settles directly with them; the platform never holds it and
 * never sees it arrive, which is why the landlord is also the one who confirms
 * each payment.
 *
 * The KHQRPay (khqr.cc) fields that used to be this page — enable, profile id,
 * secret — went with the provider in 2026-09. Nothing signs anything here any
 * more and nothing leaves the server: the QR is built from the account id below
 * and shown at checkout.
 *
 * NONE OF THIS IS A SECRET. A Bakong account id and a bank account number are
 * printed on the QR and read out to tenants; they are payment instructions, not
 * credentials, which is why they are rendered straight back into the form.
 */
class PaymentSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.payment', [
            'settings' => MerchantPaymentSetting::forAccount(current_account_id()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bakong_account_id' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'in:USD,KHR'],
        ]);

        $accountId = current_account_id();
        $settings = MerchantPaymentSetting::forAccount($accountId)
            ?? new MerchantPaymentSetting(['account_id' => $accountId]);
        $settings->account_id = $accountId;

        $settings->fill($validated)->save();

        return redirect()->route('admin.settings.payment')
            ->with('success', __('messages.payment_settings_saved'));
    }
}
