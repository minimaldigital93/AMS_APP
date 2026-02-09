<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalResource extends JsonResource
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
            'apartment_id' => $this->apartment_id,
            'tenant_id' => $this->tenant_id,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'rent_amount' => $this->rent_amount,
            'deposit' => $this->deposit,
            'is_active' => $this->start_date <= now() && ($this->end_date === null || $this->end_date >= now()),
            'apartment' => new ApartmentResource($this->whenLoaded('apartment')),
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'utilities' => UtilityResource::collection($this->whenLoaded('utilities')),
            'payments_count' => $this->whenLoaded('payments', fn() => $this->payments->count()),
            'total_paid' => $this->whenLoaded('payments', fn() => $this->payments->where('payment_status', 'paid')->sum('amount')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
