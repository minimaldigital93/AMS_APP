<?php

namespace App\Http\Requests\Concerns;

/**
 * Converts money fields a user typed in the active display currency back into
 * the stored USD base currency before the controller consumes them.
 *
 * USD is the base every amount is persisted in. When the account displays KHR,
 * the user enters riel; this trait divides those fields by the exchange rate so
 * the controller always receives USD. No-op in USD mode.
 *
 * Requests using this trait list their money fields in moneyInputKeys(); paths
 * support `*` wildcards (e.g. 'apartments.*.amount'). List ONLY money fields —
 * never meter readings, counts, or quantities.
 */
trait ConvertsCurrencyInput
{
    public function validated($key = null, $default = null)
    {
        $data = convert_money_input((array) parent::validated(), $this->moneyInputKeys());

        if (is_null($key)) {
            return $data;
        }

        return data_get($data, $key, $default);
    }

    /**
     * Money field paths to convert from display currency to USD.
     *
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return [];
    }
}
