<?php

namespace App\Http\Requests\Tenants;

use App\Models\TenantVehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantVehicleRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['monthly_fee'];
    }

    public function rules(): array
    {
        return [
            'vehicle_type' => ['required', Rule::in(TenantVehicle::TYPES)],
            // Description only ("Honda Dream") — optional, and it takes no part
            // in the plate uniqueness below or in any billing figure.
            'vehicle_model' => 'nullable|string|max:50',
            // Plates are entered in mixed case and with stray spaces; they are
            // normalised in prepareForValidation() so the unique rule below
            // actually catches a re-registration of the same vehicle.
            'plate_number' => [
                'required', 'string', 'max:30',
                // On an edit the row being corrected is itself the match, so it
                // is ignored — a resubmit that leaves the plate alone must not
                // collide with itself.
                Rule::unique('tenant_vehicles', 'plate_number')
                    ->where('account_id', current_account_id())
                    ->ignore($this->route('vehicle')),
            ],
            // Blank price = a vehicle that is recorded but not billed (parking
            // included in the rent). Only a fee above zero becomes a charge.
            'monthly_fee' => 'nullable|numeric|min:0|max:99999999.99',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('plate_number')) {
            $this->merge([
                'plate_number' => strtoupper(trim(preg_replace('/\s+/', ' ', (string) $this->input('plate_number')))),
            ]);
        }

        if ($this->has('vehicle_model')) {
            $model = trim(preg_replace('/\s+/', ' ', (string) $this->input('vehicle_model')));
            $this->merge(['vehicle_model' => $model === '' ? null : $model]);
        }
    }

    public function messages(): array
    {
        return [
            'plate_number.unique' => __('messages.validation_plate_taken'),
        ];
    }
}
