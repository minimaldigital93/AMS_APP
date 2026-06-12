<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row: the platform operator's (super admin's) payment destination
 * for subscription payments. NOT account-scoped — this is the SaaS layer.
 *
 * Field precedence: a non-blank value here overrides config/services.khqrpay;
 * blank falls back to .env so existing deployments keep working untouched.
 * The KHQRPay secret is encrypted at rest and never rendered back to the UI.
 */
class PlatformPaymentSetting extends Model
{
    protected $fillable = [
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'khqr_image_path',
        'khqrpay_profile_id',
        'khqrpay_secret',
        'bakong_account_id',
        'merchant_name',
        'currency',
    ];

    protected $hidden = ['khqrpay_secret'];

    protected function casts(): array
    {
        return [
            'khqrpay_secret' => 'encrypted',
        ];
    }

    /** The singleton row, or null when the operator has never saved one. */
    public static function current(): ?self
    {
        return static::query()->first();
    }
}
