<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddTenantChargeRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;

    /** Types billed off a continuous meter (usage = out − in). */
    private const METERED_TYPES = ['electricity', 'water'];

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['charge_amount'];
    }

    public function rules(): array
    {
        // Metered types (electricity/water) may be saved with only an opening
        // reading (meter_in, no amount yet), so charge_amount is optional there.
        // Every other type needs a real amount.
        $isMetered = in_array($this->input('charge_type'), self::METERED_TYPES, true);

        return [
            'rental_id' => 'required|exists:rentals,id',
            'charge_type' => 'required|in:electricity,water,internet,parking,trash,other',
            'charge_amount' => $isMetered
                ? 'nullable|numeric|min:0|max:99999999.99'
                : 'required|numeric|min:0.01|max:99999999.99',
            // A closing reading has no meaning without an opening one.
            'meter_reading_in' => 'nullable|numeric|min:0|max:99999999.99|required_with:meter_reading_out',
            'meter_reading_out' => 'nullable|numeric|min:0|max:99999999.99',
            'billing_month' => 'nullable|integer|min:1|max:12',
            'billing_year' => 'nullable|integer|min:2000|max:2100',
            'note' => 'nullable|string|max:500',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $in = $this->input('meter_reading_in');
            $out = $this->input('meter_reading_out');

            // Usage can't be negative — the closing reading must not be below the
            // opening one (mirrors the client-side meter_out_lt_in guard).
            if (is_numeric($in) && is_numeric($out) && (float) $out < (float) $in) {
                $validator->errors()->add('meter_reading_out', __('messages.meter_out_lt_in'));
            }
        });
    }
}
