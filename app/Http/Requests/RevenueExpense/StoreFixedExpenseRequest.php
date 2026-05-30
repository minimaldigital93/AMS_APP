<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class StoreFixedExpenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'apartment_id' => 'required|exists:apartments,id',
            'expense_name' => 'required|string|max:255',
            'expense_type' => 'required|in:parking,internet,trash,other',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
