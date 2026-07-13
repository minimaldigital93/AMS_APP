<?php

namespace App\Http\Requests\FiscalPeriod;

use Illuminate\Foundation\Http\FormRequest;

class CloseFiscalPeriodRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['closing_balance'];
    }

    public function rules(): array
    {
        return [
            // Advisory only — the close computes the balance server-side from
            // the monthly carry-forward chain (2026-07 audit F6).
            'closing_balance' => 'nullable|numeric|max:99999999.99',
        ];
    }
}
