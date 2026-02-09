<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FloorResource extends JsonResource
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
            'floor_name' => $this->floor_name,
            'description' => $this->description,
            'apartments_count' => $this->whenLoaded('apartments', fn() => $this->apartments->count()),
            'apartments' => ApartmentResource::collection($this->whenLoaded('apartments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
