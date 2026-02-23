<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenants extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'apartment_id',
        'user_id',
        'managed_by',
        'name',
        'email',
        'phone',
        'address',
        'date_of_birth',
        'place_of_birth',
        'move_in_date',
        'move_out_date',
        'status',
        'deposit',
        'photo_path',
        'document_path',
        'notes',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'move_in_date' => 'date',
            'move_out_date' => 'date',
            'archived_at' => 'datetime',
            'deposit' => 'float',
        ];
    }

    // Relationships

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartments::class, 'apartment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rentals::class, 'tenant_id');
    }

    public function utilities(): HasMany
    {
        return $this->hasMany(Utilities::class, 'tenant_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(TenantLeave::class, 'tenant_id');
    }
}
