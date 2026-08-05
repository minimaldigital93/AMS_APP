<?php

namespace App\Http\Requests\RevenueExpense;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        // The account's own vocabulary, managed in Settings → Expense Categories
        // (ExpenseCategoryController). It was a hard-coded list that had drifted
        // out of sync with the dropdown the form actually rendered, so "Legal
        // Fee" and "Salary" were unsubmittable; there is now one source.
        ExpenseCategory::ensureDefaults();

        return [
            'expense_name' => 'required|string|max:255',
            'category' => ['required', 'string', Rule::in(array_keys(ExpenseCategory::options()))],
            'amount' => 'required|numeric|min:0.01|max:99999999.99',
            'expense_date' => ['required', 'date', new \App\Rules\NotInClosedMonth, new \App\Rules\WithinActivePeriod],
            'is_recurring' => 'nullable|boolean',
            'note' => 'nullable|string|max:1000',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,heic,heif|max:10240',
        ];
    }
}
