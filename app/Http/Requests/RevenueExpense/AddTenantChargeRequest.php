<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class AddTenantChargeRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['charge_amount'];
    }

    public function rules(): array
    {
        return [
            'rental_id' => 'required|exists:rentals,id',
            'charge_type' => 'required|in:electricity,water,internet,parking,trash,other',
            'charge_amount' => 'required|numeric|min:0.01|max:99999999.99',
            'meter_reading_in' => 'nullable|numeric|min:0|max:99999999.99',
            'meter_reading_out' => 'nullable|numeric|min:0|max:99999999.99',
            'billing_month' => 'nullable|integer|min:1|max:12',
            'billing_year' => 'nullable|integer|min:2000|max:2100',
            'note' => 'nullable|string|max:500',
        ];
    }
}
