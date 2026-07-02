<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutTenantRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['rent_amount', 'late_fee'];
    }

    public function rules(): array
    {
        return [
            'rental_id' => 'required|exists:rentals,id',
            'payment_method' => 'required|in:cash,bank,khqr',
            'payment_date' => ['required', 'date', new \App\Rules\NotInClosedMonth],
            'rent_amount' => 'required|numeric|min:0',
            'late_fee' => 'nullable|numeric|min:0',
            'pay_rent' => 'nullable|boolean',
            'pay_utilities' => 'nullable|boolean',
            'transaction_reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
