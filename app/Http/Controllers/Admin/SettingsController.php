<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return view('admin.settings.index', compact('settings', 'defaultSettings'));
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
}
