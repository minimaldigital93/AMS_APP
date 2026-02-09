<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UtilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'rental_id' => $this->rental_id,
            'utility_type' => $this->utility_type,
            'meter_number' => $this->meter_number,
            'meter_reading_in' => $this->meter_reading_in,
            'meter_reading_out' => $this->meter_reading_out,
            'consumption' => $this->meter_reading_out && $this->meter_reading_in 
                ? $this->meter_reading_out - $this->meter_reading_in 
                : null,
            'charge_amount' => $this->charge_amount,
            'billing_month' => $this->billing_month,
            'billing_year' => $this->billing_year,
            'billing_period' => sprintf('%04d-%02d', $this->billing_year, $this->billing_month),
            'paid_status' => $this->paid_status,
            'paid_at' => $this->paid_at?->format('Y-m-d H:i:s'),
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'rental' => new RentalResource($this->whenLoaded('rental')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
