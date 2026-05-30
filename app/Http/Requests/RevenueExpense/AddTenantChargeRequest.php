<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class AddTenantChargeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rental_id' => 'required|exists:rentals,id',
            'charge_type' => 'required|in:electricity,water,internet,parking,trash,other',
            'charge_amount' => 'required|numeric|min:0.01',
            'meter_reading_in' => 'nullable|numeric|min:0',
            'meter_reading_out' => 'nullable|numeric|min:0',
            'billing_month' => 'nullable|integer|min:1|max:12',
            'billing_year' => 'nullable|integer|min:2000|max:2100',
            'note' => 'nullable|string|max:500',
        ];
    }
}
