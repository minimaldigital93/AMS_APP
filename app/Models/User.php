<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @mixin \Spatie\Permission\Traits\HasRoles
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships

    public function supervisedApartments(): HasMany
    {
        return $this->hasMany(Apartments::class, 'supervisor_id');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenants::class);
    }

    public function managedTenants(): HasMany
    {
        return $this->hasMany(Tenants::class, 'managed_by');
    }

    public function fiscalPeriods(): HasMany
    {
        return $this->hasMany(FiscalPeriods::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Accounts::class);
    }

    public function balanceSheets(): HasMany
    {
        return $this->hasMany(BalanceSheet::class);
    }
}
