<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingsController extends Controller
{
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
            ],
            'system' => [
                'system_currency' => 'USD',
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
        // Scoped delete (not truncate) so only this account's settings reset.
        Settings::query()->delete();
        Cache::flush();

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
