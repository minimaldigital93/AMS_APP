<?php

namespace App\Http\Requests\FiscalPeriod;

use Illuminate\Foundation\Http\FormRequest;

class StoreFiscalPeriodRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'opening_date' => 'required|date|before:closing_date',
            'closing_date' => 'required|date|after:opening_date',
        ];
    }

    /**
     * A calendar day must belong to at most ONE of the admin's fiscal periods —
     * overlapping ranges would put the same transaction date in two periods'
     * books, and the per-period reports would disagree with each other.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $overlap = \App\Models\FiscalPeriods::where('user_id', $this->user()->id)
                ->where('opening_date', '<=', $this->input('closing_date'))
                ->where('closing_date', '>=', $this->input('opening_date'))
                ->first();

            if ($overlap !== null) {
                $validator->errors()->add(
                    'opening_date',
                    __('messages.validation_fp_overlap', ['name' => $overlap->name])
                );
            }
        });
    }
}
