<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class RecordIncomeRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['amount', 'late_fee'];
    }

    public function rules(): array
    {
        return [
            'rental_id' => 'required|exists:rentals,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank',
            'payment_type' => 'required|in:rent,utilities,deposit,other',
            'transaction_date' => ['required', 'date', new \App\Rules\NotInClosedMonth],
            'transaction_reference' => 'nullable|string|max:255',
            'late_fee' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
