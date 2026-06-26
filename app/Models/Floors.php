<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\FiltersByProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Floors extends Model
{
    use BelongsToAccount, FiltersByProperty, SoftDeletes;

    protected $fillable = [
        'property_id',
        'floor_name',
        'description',
    ];

    /** Floors own property_id directly. */
    protected function propertyPath(): ?string
    {
        return null;
    }

    // Relationships

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function apartments(): HasMany
    {
        return $this->hasMany(Apartments::class, 'floor_id');
    }
}
