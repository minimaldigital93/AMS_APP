<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApartmentResource extends JsonResource
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
            'floor_id' => $this->floor_id,
            'supervisor_id' => $this->supervisor_id,
            'apartment_number' => $this->apartment_number,
            'monthly_rent' => $this->monthly_rent,
            'status' => $this->status,
            'description' => $this->description,
            'floor' => new FloorResource($this->whenLoaded('floor')),
            'supervisor' => new UserResource($this->whenLoaded('supervisor')),
            'tenants' => TenantResource::collection($this->whenLoaded('tenants')),
            'rentals' => RentalResource::collection($this->whenLoaded('rentals')),
            'tenants_count' => $this->whenLoaded('tenants', fn() => $this->tenants->count()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
