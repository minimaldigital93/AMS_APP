<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantPaymentSetting;
use App\Models\Settings;
use App\Services\Billing\BillingCycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Money settings — stored in USD, typed in the display currency, and
     * printed on the rental contract.
     */
    public const PRICE_KEYS = [
        'utility_electricity_price',
        'utility_water_price',
        'utility_parking_fee',
        'utility_internet_fee',
        'utility_garbage_fee',
    ];

    /**
     * The settings page: stored values plus the defaults every form field needs.
     */
    public function index(): View
    {
        // Grouped by the prefix before the first underscore, which is how the
        // page splits settings into its cards.
        $settings = Settings::orderBy('key')->get()->groupBy(function ($setting) {
            $parts = explode('_', $setting->key);

            return $parts[0] ?? 'general';
        });

        // Minimal, user-facing settings only. Language is handled by its own
        // form (the /language/switch route + SetLocale middleware).
        $defaultSettings = [
            'company' => [
                'company_name' => '',
                'company_address' => '',
                'company_phone' => '',
                'company_email' => '',
                'company_website' => '',
            ],
            // Party "ក" on the rental contract — the person who signs, not the
            // company. Blank fields fall back to the company block above.
            'owner' => [
                'owner_name' => '',
                'owner_gender' => '',
                'owner_id_card' => '',
                'owner_phone' => '',
                'owner_address' => '',
            ],
            // Default monthly charges printed in ប្រការ១ of the contract. A lease
            // that carries its own price overrides these; see ContractGenerator.
            'utility' => [
                'utility_electricity_price' => '',
                'utility_water_price' => '',
                'utility_parking_fee' => '',
                'utility_internet_fee' => '',
                'utility_garbage_fee' => '',
                // On: the charge is computed from the meter readings and locked.
                // Off: the operator types it, meters still roll over. '1'/'0'.
                'utility_meter_auto_calc' => '0',
            ],
            // Late-payment penalty: percent of the monthly rent charged per day
            // overdue. Auto-fills the late-fee field on the rent-collection page.
            'late' => [
                'late_fee_percent' => '',
            ],
            // Blank = rent due on each tenant's own move-in day. Set = one day
            // for everyone, move-in month prorated. See BillingCycleService.
            'billing' => [
                'billing_cycle_day' => '',
                'billing_overdue_days' => (string) BillingCycleService::DEFAULT_OVERDUE_DAYS,
            ],
            'system' => [
                'system_currency' => 'USD',
                'khr_exchange_rate' => '4100',
            ],
        ];

        // The scan-to-pay QR rides the column the manual KHQR checkout channel
        // already reads (merchant_payment_settings.khqr_image_path) — there is
        // one static QR per account, not one per page that shows it.
        $merchant = MerchantPaymentSetting::forAccount(current_account_id());
        $khqrImageUrl = filled($merchant?->khqr_image_path)
            ? asset('storage/'.$merchant->khqr_image_path)
            : null;
        // Whose account the QR pays into — printed under it on the bill.
        $khqrAccountName = $merchant?->bank_account_name;

        return view('admin.settings.index', compact('settings', 'defaultSettings', 'khqrImageUrl', 'khqrAccountName'));
    }

    /**
     * Write one key/value pair.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'nullable|string',
        ]);

        Settings::set($request->key, $request->value);

        return redirect()->route('admin.settings.index')
            ->with('success', __('messages.setting_updated'));
    }

    /**
     * Save the whole settings form in one submit, logo included.
     */
    public function updateBatch(Request $request): RedirectResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string',
            'settings.khr_exchange_rate' => 'nullable|numeric|min:1',
            'settings.owner_gender' => 'nullable|in:male,female,other',
            'settings.utility_electricity_price' => 'nullable|numeric|min:0',
            'settings.utility_water_price' => 'nullable|numeric|min:0',
            'settings.utility_parking_fee' => 'nullable|numeric|min:0',
            'settings.utility_internet_fee' => 'nullable|numeric|min:0',
            'settings.utility_garbage_fee' => 'nullable|numeric|min:0',
            'settings.late_fee_percent' => 'nullable|numeric|min:0|max:100',
            'settings.utility_meter_auto_calc' => 'nullable|in:0,1',
            'settings.billing_cycle_day' => 'nullable|integer|min:1|max:'.BillingCycleService::MAX_COLLECTION_DAY,
            'settings.billing_overdue_days' => 'nullable|integer|min:0|max:31',
            'company_logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'khqr_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'khqr_account_name' => 'nullable|string|max:255',
        ], [
            'settings.khr_exchange_rate.numeric' => __('messages.exchange_rate_invalid'),
            'settings.khr_exchange_rate.min' => __('messages.exchange_rate_invalid'),
        ]);

        // Prices are typed in the display currency but stored in USD like every
        // other money column — see convert_money_input() / money_input().
        $settings = convert_money_input(
            ['settings' => $request->settings],
            array_map(fn ($k) => "settings.$k", self::PRICE_KEYS)
        )['settings'];

        foreach ($settings as $key => $value) {
            Settings::set($key, $value);
        }

        $this->handleCompanyLogo($request);
        $this->handleScanToPayQr($request);

        return redirect()->route('admin.settings.index')
            ->with('success', __('messages.settings_updated'));
    }

    /**
     * Remove a single setting key.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        Settings::where('key', $request->key)->delete();
        Settings::forgetCached($request->key);

        return redirect()->route('admin.settings.index')
            ->with('success', __('messages.setting_deleted'));
    }

    /**
     * Clear every setting for this account, falling back to the defaults.
     */
    public function reset(): RedirectResponse
    {
        // Scoped delete, not truncate, and per-key eviction — Cache::flush()
        // would drop every other account's settings from the shared store.
        $accountId = current_account_id();
        $keys = Settings::query()->pluck('key');

        Settings::query()->delete();
        foreach ($keys as $key) {
            Settings::forgetCached($key, $accountId);
        }

        return redirect()->route('admin.settings.index')
            ->with('success', __('messages.settings_reset'));
    }

    protected function handleCompanyLogo(Request $request): void
    {
        $currentPath = Settings::get('company_logo');

        // Explicit removal (checkbox) — delete the file and clear the setting.
        if ($request->boolean('remove_company_logo')) {
            if ($currentPath && Storage::disk('public')->exists($currentPath)) {
                Storage::disk('public')->delete($currentPath);
            }
            Settings::set('company_logo', null);

            return;
        }

        // New upload — store it and drop the previous file.
        if ($request->hasFile('company_logo') && $request->file('company_logo')->isValid()) {
            if ($currentPath && Storage::disk('public')->exists($currentPath)) {
                Storage::disk('public')->delete($currentPath);
            }
            $path = $request->file('company_logo')->store('company', 'public');
            Settings::set('company_logo', $path);
        }
    }

    /**
     * The scan-to-pay QR — image plus the account name printed under it: the
     * tenant has to see whose account they are paying into before they scan.
     *
     * Both live on the account's MerchantPaymentSetting row (the one place a
     * static QR lives), not as Settings keys — the manual KHQR checkout channel
     * and AccountPurgeService already read that column, and `bank_account_name`
     * is the merchant name that channel signs its payload with. Same shape as
     * the logo handler: a blank file input keeps the stored image, the checkbox
     * clears it.
     */
    protected function handleScanToPayQr(Request $request): void
    {
        $removing = $request->boolean('remove_khqr_image');
        $uploading = $request->hasFile('khqr_image') && $request->file('khqr_image')->isValid();
        // Only a form that actually carries the field may clear the name — a
        // caller that never showed it must not blank what someone else typed.
        $naming = $request->has('khqr_account_name');

        if (! $removing && ! $uploading && ! $naming) {
            return;
        }

        $accountId = current_account_id();
        $merchant = MerchantPaymentSetting::forAccount($accountId)
            ?? new MerchantPaymentSetting(['account_id' => $accountId]);
        $merchant->account_id = $accountId;

        if ($naming) {
            $merchant->bank_account_name = trim((string) $request->input('khqr_account_name')) ?: null;
        }

        if ($removing || $uploading) {
            $current = $merchant->khqr_image_path;
            if ($current && Storage::disk('public')->exists($current)) {
                Storage::disk('public')->delete($current);
            }

            $merchant->khqr_image_path = $removing
                ? null
                : $request->file('khqr_image')->store('khqr', 'public');
        }

        $merchant->save();
    }
}
