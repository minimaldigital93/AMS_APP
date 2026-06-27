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
            'closing_balance' => 'required|numeric',
        ];
    }
}
