<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenants;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the tenant move-out form (both admin and supervisor).
 *
 * The leave_date must be on or after the tenant's move_in_date — the rule is
 * built dynamically from the route-bound {tenant} so the error message matches
 * the actual tenant being processed.
 */
class ProcessTenantLeaveRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['extra_charges.*.amount'];
    }

    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $moveInBoundary = $tenant instanceof Tenants
            ? $tenant->move_in_date->format('Y-m-d')
            : '1970-01-01';

        return [
            // NotInClosedMonth: the settlement books ledger rows dated on the
            // leave date — never let those land inside a closed (frozen) month.
            'leave_date' => ['required', 'date', 'after_or_equal:'.$moveInBoundary, new \App\Rules\NotInClosedMonth, new \App\Rules\WithinActivePeriod],
            'charge_full_month' => 'nullable|boolean',
            'charge_ids' => 'nullable|array',
            'charge_ids.*' => 'string',
            'extra_charges' => 'nullable|array',
            'extra_charges.*.description' => 'required_with:extra_charges.*.amount|string|max:255',
            'extra_charges.*.amount' => 'required_with:extra_charges.*.description|numeric|min:0.01|max:99999999.99',
            'notes' => 'nullable|string|max:65535',
            'deposit_action' => 'nullable|in:return_deposit,last_payment',
        ];
    }
}
