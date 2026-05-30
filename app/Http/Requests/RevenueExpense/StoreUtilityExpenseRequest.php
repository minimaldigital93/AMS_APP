<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class StoreUtilityExpenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rental_id' => 'required|exists:rentals,id',
            'utility_type' => 'required|in:electricity,water,internet,parking,trash,other',
            'charge_amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'meter_reading_in' => 'nullable|numeric|min:0',
            'meter_reading_out' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
