<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display the settings page
     */
    public function index(): View
    {
        $settings = Settings::orderBy('key')->get()->groupBy(function($setting) {
            // Group settings by category (prefix before first underscore)
            $parts = explode('_', $setting->key);
            return $parts[0] ?? 'general';
        });

        // Define default settings structure
        $defaultSettings = [
            'app' => [
                'app_name' => 'Apartment Management System',
                'app_timezone' => 'UTC',
                'app_locale' => 'en',
            ],
            'company' => [
                'company_name' => '',
                'company_address' => '',
                'company_phone' => '',
                'company_email' => '',
            ],
            'email' => [
                'email_from_name' => '',
                'email_from_address' => '',
            ],
            'system' => [
                'system_currency' => 'USD',
                'system_date_format' => 'Y-m-d',
                'system_time_format' => 'H:i:s',
            ],
            'fiscal' => [
                'fiscal_year_start' => '01-01',
                'fiscal_auto_close' => 'no',
            ],
            'notification' => [
                'notification_payment_reminder' => 'yes',
                'notification_lease_expiry' => 'yes',
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
        ]);

        foreach ($request->settings as $key => $value) {
            Settings::set($key, $value);
        }

        return redirect()->route('admin.settings.index')
            ->with('success', __('messages.settings_updated'));
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

        return redirect()->route('admin.settings.index')
            ->with('success', __('messages.setting_deleted'));
    }

    /**
     * Reset settings to default
     */
    public function reset(): RedirectResponse
    {
        Settings::truncate();

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
