<?php

namespace App\Http\Requests\FiscalPeriod;

use Illuminate\Foundation\Http\FormRequest;

class CloseFiscalPeriodRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'closing_balance' => 'required|numeric',
        ];
    }
}
