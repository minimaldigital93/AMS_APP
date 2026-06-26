<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\FiltersByProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApartmentFixedExpense extends Model
{
    use BelongsToAccount, FiltersByProperty;

    /** Fixed expenses reach a property through apartment → floor. */
    protected function propertyPath(): ?string
    {
        return 'apartment.floor';
    }

    protected $fillable = [
        'apartment_id',
        'expense_name',
        'expense_type',
        'amount',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartments::class, 'apartment_id');
    }
}
