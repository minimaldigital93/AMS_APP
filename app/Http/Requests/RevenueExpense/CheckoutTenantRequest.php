<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rental_id' => 'required|exists:rentals,id',
            'payment_method' => 'required|in:cash,bank',
            'payment_date' => 'required|date',
            'rent_amount' => 'required|numeric|min:0',
            'late_fee' => 'nullable|numeric|min:0',
            'pay_rent' => 'nullable|boolean',
            'pay_utilities' => 'nullable|boolean',
            'transaction_reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
