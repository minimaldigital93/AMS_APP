<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
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
            ],
            'system' => [
                'system_currency' => 'USD',
            ],
        ];

        return view('supervisor.settings.index', compact('settings', 'defaultSettings'));
    }

    public function updateBatch(Request $request): RedirectResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string',
        ]);

        foreach ($request->settings as $key => $value) {
            Settings::set($key, $value);
        }

        return redirect()->route('supervisor.settings.index')
            ->with('success', __('messages.settings_updated'));
    }

    public function reset(): RedirectResponse
    {
        // Scoped delete (not truncate) so only this account's settings reset.
        Settings::query()->delete();
        Cache::flush();

        return redirect()->route('supervisor.settings.index')
            ->with('success', __('messages.settings_reset'));
    }
}
