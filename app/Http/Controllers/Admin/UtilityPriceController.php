<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings → Default Utility Prices: its own page, like Expense Categories.
 *
 * The prices are the default monthly charges printed in ប្រការ១ of the rental
 * contract; a lease that carries its own price overrides them (see
 * ContractGenerator), and the metered types are read by IncomeRecordingService.
 */
class UtilityPriceController extends Controller
{
    /**
     * Money settings — stored in USD, typed in the display currency.
     */
    public const PRICE_KEYS = [
        'utility_electricity_price',
        'utility_water_price',
        'utility_parking_fee',
        'utility_internet_fee',
        'utility_garbage_fee',
    ];

    /**
     * Every key this page owns, with the default a blank setting falls back to.
     * It is both the form's field list and the allow-list `update()` writes
     * through, so a field can never be added to one without the other.
     */
    public const DEFAULTS = [
        'utility_electricity_price' => '',
        'utility_water_price' => '',
        'utility_parking_fee' => '',
        'utility_internet_fee' => '',
        'utility_garbage_fee' => '',
        // On: the charge is computed from the meter readings and locked.
        // Off: the operator types it, meters still roll over. '1'/'0'.
        'utility_meter_auto_calc' => '0',
    ];

    public function index(): View
    {
        $values = [];
        foreach (self::DEFAULTS as $key => $default) {
            $values[$key] = settings($key, $default);
        }

        return view('admin.settings.utility_prices', compact('values'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string',
            'settings.utility_electricity_price' => 'nullable|numeric|min:0',
            'settings.utility_water_price' => 'nullable|numeric|min:0',
            'settings.utility_parking_fee' => 'nullable|numeric|min:0',
            'settings.utility_internet_fee' => 'nullable|numeric|min:0',
            'settings.utility_garbage_fee' => 'nullable|numeric|min:0',
            'settings.utility_meter_auto_calc' => 'nullable|in:0,1',
        ]);

        // Prices are typed in the display currency but stored in USD like every
        // other money column — see convert_money_input() / money_input().
        $settings = convert_money_input(
            ['settings' => $request->settings],
            array_map(fn ($k) => "settings.$k", self::PRICE_KEYS)
        )['settings'];

        // Only the keys this page shows — a request must not be able to write
        // settings that belong to the main settings form.
        foreach (array_intersect_key($settings, self::DEFAULTS) as $key => $value) {
            Settings::set($key, $value);
        }

        return redirect()->route('admin.settings.utility_prices')
            ->with('success', __('messages.settings_updated'));
    }
}
