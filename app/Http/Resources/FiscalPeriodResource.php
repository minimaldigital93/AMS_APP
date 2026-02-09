<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiscalPeriodResource extends JsonResource
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
            'user_id' => $this->user_id,
            'name' => $this->name,
            'opening_date' => $this->opening_date?->format('Y-m-d'),
            'closing_date' => $this->closing_date?->format('Y-m-d'),
            'opening_balance' => $this->opening_balance,
            'closing_balance' => $this->closing_balance,
            'status' => $this->status,
            'is_active' => $this->status === 'open',
            'user' => new UserResource($this->whenLoaded('user')),
            'accounts' => AccountResource::collection($this->whenLoaded('accounts')),
            'balance_sheets' => $this->whenLoaded('balanceSheets'),
            'accounts_count' => $this->whenLoaded('accounts', fn() => $this->accounts->count()),
            'total_income' => $this->whenLoaded('accounts', fn() => $this->accounts->where('account_type', 'income')->sum('amount')),
            'total_expense' => $this->whenLoaded('accounts', fn() => $this->accounts->where('account_type', 'expense')->sum('amount')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
