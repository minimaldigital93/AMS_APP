<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Floors extends Model
{
    use BelongsToAccount, SoftDeletes;

    protected $fillable = [
        'floor_name',
        'description',
    ];

    // Relationships

    public function apartments(): HasMany
    {
        return $this->hasMany(Apartments::class, 'floor_id');
    }
}
