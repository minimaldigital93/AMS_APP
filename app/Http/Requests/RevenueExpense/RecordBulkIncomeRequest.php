<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class RecordBulkIncomeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'payment_date'           => 'required|date',
            'payment_method'         => 'required|in:cash,bank',
            'apartments'             => 'required|array|min:1',
            'apartments.*.rental_id' => 'required|exists:rentals,id',
            'apartments.*.amount'    => 'required|numeric|min:0.01',
            'apartments.*.late_fee'  => 'nullable|numeric|min:0',
            'apartments.*.selected'  => 'nullable|boolean',
        ];
    }
}
