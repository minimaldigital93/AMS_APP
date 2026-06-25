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
            'company_logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        foreach ($request->settings as $key => $value) {
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
