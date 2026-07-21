<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
     * Display the settings page
     */
    public function index(): View
    {
        $settings = Settings::orderBy('key')->get()->groupBy(function ($setting) {
            // Group settings by category (prefix before first underscore)
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
            // Party "ក" on the printed rental contract. The company block above is
            // branding; the owner is the natural person who signs, so the contract
            // needs their gender and ID-card number too. Blank owner fields fall
            // back to the company ones — see ContractGenerator::viewData().
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
            ],
            // Late-payment penalty: percent of the monthly rent charged per day
            // overdue. Auto-fills the late-fee field on the rent-collection page.
            'late' => [
                'late_fee_percent' => '',
            ],
            'system' => [
                'system_currency' => 'USD',
                'khr_exchange_rate' => '4100',
            ],
        ];

        return view('admin.settings.index', compact('settings', 'defaultSettings'));
    }

    /**
     * Update a specific setting
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
     * Update multiple settings at once
     */
    public function updateBatch(Request $request): RedirectResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string',
            'settings.khr_exchange_rate' => 'nullable|numeric|min:1',
            'settings.owner_gender' => 'nullable|in:male,female,other',
            // These land verbatim on a legal document, so keep them numeric.
            'settings.utility_electricity_price' => 'nullable|numeric|min:0',
            'settings.utility_water_price' => 'nullable|numeric|min:0',
            'settings.utility_parking_fee' => 'nullable|numeric|min:0',
            'settings.utility_internet_fee' => 'nullable|numeric|min:0',
            'settings.utility_garbage_fee' => 'nullable|numeric|min:0',
            // Percent of rent charged per overdue day — not a money field.
            'settings.late_fee_percent' => 'nullable|numeric|min:0|max:100',
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
     * Upload, replace, or remove the account's company logo.
     *
     * The stored value is the path on the `public` disk (e.g. company/abc.png),
     * mirroring how tenant photos are handled so it renders via asset('storage/…').
     */
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
     * Delete a setting
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
     * Reset settings to default
     */
    public function reset(): RedirectResponse
    {
        // Scoped delete (not truncate) so only this account's settings reset.
        // Per-key cache eviction — Cache::flush() would drop every OTHER
        // account's cached settings (and everything else in the shared store).
        $accountId = current_account_id();
        $keys = Settings::query()->pluck('key');

        Settings::query()->delete();
        foreach ($keys as $key) {
            Settings::forgetCached($key, $accountId);
        }

        return redirect()->route('admin.settings.index')
            ->with('success', __('messages.settings_reset'));
    }

    /**
     * Get a specific setting value (API endpoint)
     */
    public function get(string $key)
    {
        return response()->json([
            'key' => $key,
            'value' => Settings::get($key),
        ]);
    }
}
