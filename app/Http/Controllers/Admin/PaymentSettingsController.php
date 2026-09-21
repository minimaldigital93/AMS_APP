<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantPaymentSetting;
use App\Services\Bakong\MerchantBakongCredentials;
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
 * MOST OF THIS IS NOT A SECRET. A Bakong account id and a bank account number
 * are printed on the QR and read out to tenants; they are payment instructions,
 * not credentials, which is why they are rendered straight back into the form.
 *
 * THE ACCESS TOKEN IS THE ONE EXCEPTION, and it is handled the way the
 * superadmin's platform token is: type=password, never pre-filled, blank means
 * keep the stored one, encrypted at rest, added to dontFlash so a validation
 * error elsewhere on the form cannot carry a bearer credential into the session
 * via old(), and reported back only as a fingerprint plus an expiry.
 */
class PaymentSettingsController extends Controller
{
    public function __construct(private readonly MerchantBakongCredentials $credentials) {}

    public function edit(): View
    {
        $accountId = current_account_id();

        return view('admin.settings.payment', [
            'settings' => MerchantPaymentSetting::forAccount($accountId),
            'bakongToken' => $this->credentials->statusFor($accountId),
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
            'bakong_enabled' => ['nullable', 'boolean'],
            'bakong_token' => ['nullable', 'string', 'max:4096'],
        ]);

        $accountId = current_account_id();
        $settings = MerchantPaymentSetting::forAccountOrNew($accountId);
        $settings->account_id = $accountId;

        // fill() with the validated set only, so a field the form did not
        // submit is LEFT ALONE rather than blanked. The bank details are also
        // written from System Settings, and spelling each key out here wiped
        // whatever was typed there whenever this form was saved without them.
        $settings->fill($validated)->save();

        // Import LAST, and only when something was actually pasted: a blank
        // field means "keep the stored token", never "erase it". Removing a
        // credential is its own deliberate action, not a side effect of saving
        // a bank name.
        if (filled($token = (string) $request->input('bakong_token'))) {
            $result = $this->credentials->import($accountId, $token);

            if (! $result['ok']) {
                return redirect()->route('admin.settings.payment')
                    ->withErrors(['bakong_token' => $result['message']]);
            }

            return redirect()->route('admin.settings.payment')
                ->with('success', $result['message']);
        }

        return redirect()->route('admin.settings.payment')
            ->with('success', __('messages.payment_settings_saved'));
    }

    /** Remove the credential without disturbing the payout identity. */
    public function forgetToken(): RedirectResponse
    {
        $this->credentials->forget(current_account_id());

        return redirect()->route('admin.settings.payment')
            ->with('success', __('messages.bakong_token_removed'));
    }
}
