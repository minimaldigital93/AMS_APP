<?php

use App\Http\Requests\RevenueExpense\RecordBulkIncomeRequest;
use App\Http\Requests\RevenueExpense\RecordIncomeRequest;

/**
 * USD is the base currency every amount is stored in. When an account selects
 * KHR with an exchange rate, amounts must convert for display (× rate) and any
 * riel the user types must convert back to USD on save (÷ rate).
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
});

it('formats USD with the dollar symbol and 2 decimals by default', function () {
    settings(['system_currency' => 'USD']);

    expect(money(100))->toBe('$100.00');
    expect(money_number(100))->toBe('100.00');
    expect(money_input(100))->toBe('100.00');
    expect(to_base_amount(100))->toBe(100.0);
    expect(to_display_amount(100))->toBe(100.0);
});

it('converts and formats KHR using the exchange rate', function () {
    settings(['system_currency' => 'KHR', 'khr_exchange_rate' => '4000']);

    // Display: 100 USD × 4000 = 400,000 riel, whole-riel, riel symbol.
    expect(money(100))->toBe('៛400,000');
    expect(money_number(100))->toBe('400,000');
    // Input prefill: converted, but no thousand separators (number input).
    expect(money_input(100))->toBe('400000');
    expect(to_display_amount(100))->toBe(400000.0);
});

it('converts riel input back to the USD base on save', function () {
    settings(['system_currency' => 'KHR', 'khr_exchange_rate' => '4000']);

    // User typed 400,000 riel → stored as 100 USD.
    expect(to_base_amount(400000))->toBe(100.0);

    $converted = convert_money_input(
        ['amount' => '400000', 'meter_reading_in' => '500'],
        ['amount'],
    );

    expect($converted['amount'])->toBe(100.0);
    // Non-money fields (meter readings) are never touched.
    expect($converted['meter_reading_in'])->toBe('500');
});

it('convert_money_input is a no-op in USD mode', function () {
    settings(['system_currency' => 'USD']);

    $data = convert_money_input(['amount' => '100'], ['amount']);

    expect($data['amount'])->toBe('100');
});

it('converts nested wildcard money paths', function () {
    settings(['system_currency' => 'KHR', 'khr_exchange_rate' => '4000']);

    $converted = convert_money_input([
        'apartments' => [
            ['amount' => '400000', 'late_fee' => '8000'],
            ['amount' => '200000', 'late_fee' => '0'],
        ],
    ], ['apartments.*.amount', 'apartments.*.late_fee']);

    expect($converted['apartments'][0]['amount'])->toBe(100.0);
    expect($converted['apartments'][0]['late_fee'])->toBe(2.0);
    expect($converted['apartments'][1]['amount'])->toBe(50.0);
});

it('falls back to a sane rate when the setting is missing or invalid', function () {
    settings(['system_currency' => 'KHR', 'khr_exchange_rate' => '0']);

    expect(exchange_rate())->toBe(4100.0);
});

it('a money FormRequest converts its riel fields to USD via validated()', function () {
    settings(['system_currency' => 'KHR', 'khr_exchange_rate' => '4000']);

    $request = RecordIncomeRequest::create('/x', 'POST', [
        'rental_id' => 1,
        'amount' => '400000',
        'late_fee' => '8000',
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->setValidator(validator($request->all(), ['amount' => 'numeric', 'late_fee' => 'numeric']));

    $validated = $request->validated();

    expect($validated['amount'])->toBe(100.0);
    expect($validated['late_fee'])->toBe(2.0);
})->skip(fn () => ! class_exists(RecordBulkIncomeRequest::class), 'request class missing');
