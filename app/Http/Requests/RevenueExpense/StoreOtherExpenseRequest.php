<?php

namespace App\Http\Requests\RevenueExpense;

use Illuminate\Foundation\Http\FormRequest;

class StoreOtherExpenseRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['amount'];
    }

    /**
     * Categories allowed for "other" (non-utility, non-business) expenses.
     * Keep in sync with the Accounts category constants and the recordExpense view.
     */
    public const ALLOWED_CATEGORIES = [
        'maintenance', 'repairs', 'insurance', 'property_tax', 'management',
        'cleaning', 'security', 'landscaping', 'supplies', 'marketing',
        'legal', 'miscellaneous', 'salaries', 'taxes', 'other_expense',
    ];

    public function rules(): array
    {
        return [
            'category' => 'required|string|in:'.implode(',', self::ALLOWED_CATEGORIES),
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
