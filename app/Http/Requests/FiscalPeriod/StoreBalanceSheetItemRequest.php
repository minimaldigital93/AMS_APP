<?php

namespace App\Http\Requests\FiscalPeriod;

use App\Models\FiscalPeriods;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new BalanceSheet item against the route-bound fiscal period —
 * the as_of_date must fall inside the period's opening/closing date range.
 */
class StoreBalanceSheetItemRequest extends FormRequest
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
     * The valid sub_types per item_type — these mirror the balance_sheets
     * `sub_type` DB enum exactly. Under STRICT_TRANS_TABLES an unlisted value
     * is a 500 (data truncated), so the sub_type is validated against the set
     * allowed for the chosen item_type (no more 'cash' under a 'liability').
     */
    private const SUB_TYPES = [
        'asset' => ['cash', 'accounts_receivable', 'property', 'equipment', 'other_asset'],
        'liability' => ['accounts_payable', 'loans', 'deposits_held', 'other_liability'],
        'equity' => ['retained_earnings', 'capital', 'other_equity'],
    ];

    public function rules(): array
    {
        $period = $this->route('fiscalperiod');
        $opening = $period instanceof FiscalPeriods
            ? $period->opening_date->format('Y-m-d')
            : '1970-01-01';
        $closing = $period instanceof FiscalPeriods
            ? $period->closing_date->format('Y-m-d')
            : '2999-12-31';

        // The sub_type must belong to the chosen item_type. Fall back to the
        // full enum when item_type is missing/invalid (its own rule reports that).
        $allowedSubTypes = self::SUB_TYPES[$this->input('item_type')]
            ?? array_merge(...array_values(self::SUB_TYPES));

        return [
            'item_type' => 'required|in:asset,liability,equity',
            'sub_type' => ['required', 'string', 'in:'.implode(',', $allowedSubTypes)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'amount' => 'required|numeric|min:0|max:99999999.99',
            'as_of_date' => 'required|date|after_or_equal:'.$opening.'|before_or_equal:'.$closing,
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:65535',
        ];
    }
}
