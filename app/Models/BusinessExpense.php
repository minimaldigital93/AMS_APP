<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BusinessExpense extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'user_id',
        'fiscal_period_id',
        'property_id',
        'expense_name',
        'category',
        'amount',
        'expense_date',
        'billing_month',
        'billing_year',
        'is_recurring',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'expense_date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    // Relationships

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('sort_order');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriods::class, 'fiscal_period_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    // Scopes

    public function scopeForMonth($query, $month, $year)
    {
        return $query->where('billing_month', $month)->where('billing_year', $year);
    }

    /**
     * Limit to one property's overhead. Mirrors Accounts::scopeForProperty:
     * account-wide rows (null property_id, recorded under "All properties")
     * stay visible under every property. A null id is a no-op.
     */
    public function scopeForProperty($query, ?int $propertyId)
    {
        if ($propertyId === null) {
            return $query;
        }

        return $query->where(function ($q) use ($propertyId) {
            $q->where('property_id', $propertyId)->orWhereNull('property_id');
        });
    }

    /**
     * Limit to a set of properties' overhead — the consolidated view for a
     * supervisor across their assigned buildings. Account-wide rows stay
     * visible; an empty set is a no-op. Mirrors Accounts::scopeForProperties.
     *
     * @param  array<int>  $propertyIds
     */
    public function scopeForProperties($query, array $propertyIds)
    {
        if ($propertyIds === []) {
            return $query;
        }

        return $query->where(function ($q) use ($propertyIds) {
            $q->whereIn('property_id', $propertyIds)->orWhereNull('property_id');
        });
    }
}
