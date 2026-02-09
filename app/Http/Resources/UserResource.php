<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'roles' => $this->whenLoaded('roles', fn() => $this->getRoleNames()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
