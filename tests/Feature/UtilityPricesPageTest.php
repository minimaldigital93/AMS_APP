<?php

use App\Http\Controllers\Admin\UtilityPriceController;

/**
 * The default utility prices moved off the main settings form onto their own
 * page, the way Expense Categories already sits on one. Two things had to
 * survive the move: every field still reaches the page, and a price typed in
 * riel is still stored in USD — the conversion used to live in
 * SettingsController::updateBatch(), which this form no longer posts to.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    // Settings::get() matches account_id exactly, so a fixture written while
    // unauthenticated is invisible to the request that reads it.
    $this->actingAs($this->admin);
});

it('links to the page from the settings index', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee(route('admin.settings.utility_prices'), false)
        ->assertSee(__('messages.default_utility_prices'));
});

it('ships every utility field exactly once', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('admin.settings.utility_prices'))
        ->assertOk()
        ->getContent();

    foreach (array_keys(UtilityPriceController::DEFAULTS) as $key) {
        expect(substr_count($html, 'id="'.$key.'"'))->toBe(1, "field rendered twice or not at all: {$key}");
    }
});

it('shows what is stored', function () {
    settings(['utility_electricity_price' => '0.25', 'utility_meter_auto_calc' => '1']);

    $html = $this->actingAs($this->admin)
        ->get(route('admin.settings.utility_prices'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('value="0.25"')
        ->and(preg_match('/id="utility_meter_auto_calc"[^>]*checked/s', $html))->toBe(1);
});

it('saves the prices and the meter toggle', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.utility_prices.update'), [
            'settings' => [
                'utility_electricity_price' => '0.25',
                'utility_water_price' => '0.50',
                'utility_parking_fee' => '10',
                'utility_internet_fee' => '5',
                'utility_garbage_fee' => '2',
                'utility_meter_auto_calc' => '1',
            ],
        ])
        ->assertRedirect(route('admin.settings.utility_prices'));

    auth()->login($this->admin);
    expect(settings('utility_electricity_price'))->toBe('0.25')
        ->and(settings('utility_garbage_fee'))->toBe('2')
        ->and(settings('utility_meter_auto_calc'))->toBe('1');
});

it('stores a riel price in the USD base', function () {
    settings(['system_currency' => 'KHR', 'khr_exchange_rate' => '4000']);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.utility_prices.update'), [
            'settings' => ['utility_parking_fee' => '40000'],
        ])
        ->assertRedirect();

    // 40,000 riel at 4,000/$ is $10 — the same base every money column stores.
    auth()->login($this->admin);
    expect((float) settings('utility_parking_fee'))->toBe(10.0);
});

it('rejects a negative price', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.utility_prices.update'), [
            'settings' => ['utility_water_price' => '-1'],
        ])
        ->assertSessionHasErrors('settings.utility_water_price');

    auth()->login($this->admin);
    expect(settings('utility_water_price'))->toBeNull();
});

it('writes only the keys it owns', function () {
    settings(['company_name' => 'Keep Me']);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.utility_prices.update'), [
            'settings' => [
                'utility_water_price' => '0.50',
                'company_name' => 'Hijacked',
            ],
        ])
        ->assertRedirect();

    auth()->login($this->admin);
    expect(settings('company_name'))->toBe('Keep Me')
        ->and(settings('utility_water_price'))->toBe('0.50');
});

it('is no longer writable through the main settings form', function () {
    settings(['utility_water_price' => '0.50']);

    // A tab opened before the move still posts the price fields; the form
    // writes only the keys it renders, so the stored price is left alone.
    $this->actingAs($this->admin)
        ->put(route('admin.settings.updateBatch'), [
            'settings' => [
                'company_name' => 'Still Saved',
                'utility_water_price' => '99',
            ],
        ])
        ->assertRedirect(route('admin.settings.index'));

    auth()->login($this->admin);
    expect(settings('company_name'))->toBe('Still Saved')
        ->and(settings('utility_water_price'))->toBe('0.50');
});
