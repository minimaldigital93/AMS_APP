<?php

namespace App\Http\Requests\FiscalPeriod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateFiscalPeriodRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'opening_date' => 'required|date|before:closing_date',
            'closing_date' => 'required|date|after:opening_date',
            'opening_assets' => 'required|numeric|min:0',
            'opening_liabilities' => 'required|numeric|min:0',
            'opening_equity' => 'required|numeric|min:0',
        ];
    }

    /**
     * The opening balance sheet must balance: Assets = Liabilities + Equity.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $assets = (float) $this->input('opening_assets');
            $liabilities = (float) $this->input('opening_liabilities');
            $equity = (float) $this->input('opening_equity');

            if (abs($assets - ($liabilities + $equity)) > 0.01) {
                $validator->errors()->add(
                    'opening_assets',
                    'The opening balance sheet must balance: Assets must equal Liabilities + Equity.'
                );
            }
        });
    }
}
