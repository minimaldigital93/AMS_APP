<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = Settings::orderBy('key')->get()->groupBy(function ($setting) {
            $parts = explode('_', $setting->key);
            return $parts[0] ?? 'general';
        });

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
        Settings::truncate();

        return redirect()->route('supervisor.settings.index')
            ->with('success', __('messages.settings_reset'));
    }
}
