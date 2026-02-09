<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
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
            'fiscal_period_id' => $this->fiscal_period_id,
            'payment_id' => $this->payment_id,
            'user_id' => $this->user_id,
            'account_type' => $this->account_type,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amount,
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'reference_number' => $this->reference_number,
            'note' => $this->note,
            'fiscal_period' => new FiscalPeriodResource($this->whenLoaded('fiscalPeriod')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
