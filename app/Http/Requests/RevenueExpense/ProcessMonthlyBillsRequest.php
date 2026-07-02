<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class ProcessMonthlyBillsRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['bills.*.expenses.*.amount'];
    }

    public function rules(): array
    {
        return [
            'billing_date' => ['required', 'date', new \App\Rules\NotInClosedMonth],
            'bills' => 'required|array|min:1',
            'bills.*.rental_id' => 'required|exists:rentals,id',
            'bills.*.selected' => 'nullable|boolean',
            'bills.*.expenses' => 'nullable|array',
            'bills.*.expenses.*.expense_id' => 'required|exists:apartment_fixed_expenses,id',
            'bills.*.expenses.*.amount' => 'required|numeric|min:0',
            'bills.*.expenses.*.selected' => 'nullable|boolean',
        ];
    }
}
