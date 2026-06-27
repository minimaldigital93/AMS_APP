<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessExpenseRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['amount'];
    }

    public function rules(): array
    {
        return [
            'expense_name' => 'required|string|max:255',
            'category' => 'required|in:electricity,water,trash,internet,legal_fee,tax,loan_payment,salary,other',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'is_recurring' => 'nullable|boolean',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf|max:10240',
        ];
    }
}
