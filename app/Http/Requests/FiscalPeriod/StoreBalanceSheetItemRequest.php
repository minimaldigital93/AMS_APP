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
    public function rules(): array
    {
        $period = $this->route('fiscalperiod');
        $opening = $period instanceof FiscalPeriods
            ? $period->opening_date->format('Y-m-d')
            : '1970-01-01';
        $closing = $period instanceof FiscalPeriods
            ? $period->closing_date->format('Y-m-d')
            : '2999-12-31';

        return [
            'item_type'        => 'required|in:asset,liability,equity',
            'sub_type'         => 'required|string',
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'amount'           => 'required|numeric|min:0',
            'as_of_date'       => 'required|date|after_or_equal:' . $opening . '|before_or_equal:' . $closing,
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
        ];
    }
}
