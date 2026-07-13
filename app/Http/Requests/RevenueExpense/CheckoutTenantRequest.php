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
            'payment_date' => ['required', 'date', new \App\Rules\NotInClosedMonth, new \App\Rules\WithinActivePeriod],
            // The bill month being settled (from the record-income month
            // navigation). Defaults to the payment date's month in the service.
            'billing_month' => 'nullable|integer|between:1,12',
            'billing_year' => 'nullable|integer|between:2000,2100',
            'rent_amount' => 'required|numeric|min:0|max:99999999.99',
            'late_fee' => 'nullable|numeric|min:0|max:99999999.99',
            'pay_rent' => 'nullable|boolean',
            'pay_utilities' => 'nullable|boolean',
            'transaction_reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
