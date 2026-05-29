<?php

namespace App\Http\Requests\FiscalPeriod;

use Illuminate\Foundation\Http\FormRequest;

class CloseMonthlyPeriodRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Owner's profit withdrawal taken when closing the month. Optional —
            // defaults to 0 (no draw). The per-month "available cash" cap is
            // enforced in the controller, where the live net income is known.
            'owner_withdrawal' => 'nullable|numeric|min:0',
            'withdrawal_note' => 'nullable|string|max:1000',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_withdrawal' => $this->input('owner_withdrawal', 0) ?: 0,
        ]);
    }
}
