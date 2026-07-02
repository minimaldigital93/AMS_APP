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
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'password',
        'status',
        'last_login_at',
        'account_id',
        'last_property_id',
        'theme',
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
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships

    /**
     * The account (owning admin) this user belongs to. Admins/superadmins own
     * their own account, so account_id points back at themselves.
     */
    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    /**
     * Every user (admin + their supervisors/tenants) within this account.
     */
    public function accountMembers(): HasMany
    {
        return $this->hasMany(User::class, 'account_id');
    }

    /**
     * This account's subscription record (one per account).
     */
    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class, 'account_id');
    }

    public function supervisedApartments(): HasMany
    {
        return $this->hasMany(Apartments::class, 'supervisor_id');
    }

    public function supervisedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'supervisor_id');
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
