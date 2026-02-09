<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
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
            'user_id' => $this->user_id,
            'managed_by' => $this->managed_by,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'place_of_birth' => $this->place_of_birth,
            'move_in_date' => $this->move_in_date?->format('Y-m-d'),
            'move_out_date' => $this->move_out_date?->format('Y-m-d'),
            'status' => $this->status,
            'deposit' => $this->deposit,
            'photo_path' => $this->photo_path,
            'document_path' => $this->document_path,
            'notes' => $this->notes,
            'archived_at' => $this->archived_at,
            'apartment' => new ApartmentResource($this->whenLoaded('apartment')),
            'manager' => new UserResource($this->whenLoaded('manager')),
            'user' => new UserResource($this->whenLoaded('user')),
            'rentals' => RentalResource::collection($this->whenLoaded('rentals')),
            'utilities' => UtilityResource::collection($this->whenLoaded('utilities')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
