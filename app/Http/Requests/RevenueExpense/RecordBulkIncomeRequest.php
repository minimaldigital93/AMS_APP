<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class RecordBulkIncomeRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['apartments.*.amount', 'apartments.*.late_fee'];
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date', new \App\Rules\NotInClosedMonth],
            'payment_method' => 'required|in:cash,bank',
            'apartments' => 'required|array|min:1',
            'apartments.*.rental_id' => 'required|exists:rentals,id',
            'apartments.*.amount' => 'required|numeric|min:0.01',
            'apartments.*.late_fee' => 'nullable|numeric|min:0',
            'apartments.*.selected' => 'nullable|boolean',
        ];
    }
}
