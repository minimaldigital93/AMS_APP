<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'rental_id' => $this->rental_id,
            'amount' => $this->amount,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'paid_at' => $this->paid_at?->format('Y-m-d H:i:s'),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'payment_type' => $this->payment_type,
            'transaction_reference' => $this->transaction_reference,
            'late_fee' => $this->late_fee,
            'note' => $this->note,
            'is_overdue' => $this->due_date < now() && $this->paid_at === null,
            'rental' => new RentalResource($this->whenLoaded('rental')),
            'accounts' => AccountResource::collection($this->whenLoaded('accounts')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
