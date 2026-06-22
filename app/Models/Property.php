<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A property (building) — the top of an account's property tree. Holds floors,
 * and (through them) rooms. May be assigned to one supervisor, who then only
 * sees this property's floors/rooms/tenants.
 */
class Property extends Model
{
    use BelongsToAccount, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'description',
        'supervisor_id',
    ];

    public function floors(): HasMany
    {
        return $this->hasMany(Floors::class, 'property_id');
    }

    public function apartments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Apartments::class,
            Floors::class,
            'property_id', // FK on floors
            'floor_id',    // FK on apartments
            'id',
            'id'
        );
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
