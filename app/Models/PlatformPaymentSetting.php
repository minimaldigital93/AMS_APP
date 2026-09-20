<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row: the platform operator's (super admin's) payment destination
 * for subscription payments. NOT account-scoped — this is the SaaS layer.
 *
 * Field precedence: a non-blank value here overrides config/bakong.php; blank
 * falls back to .env, so a fresh install, a CI run and a local demo all work
 * before anyone has opened the settings page (see BakongPlatformIdentity).
 *
 * NOTHING HERE IS A CREDENTIAL any more. The khqr.cc profile id and secret were
 * dropped with the provider in 2026-09; what remains — a Bakong account id, a
 * merchant name and city, bank details — is printed inside the QR or shown in
 * the payer's banking app, so it is identity rather than secrecy. The Bakong
 * ACCESS TOKEN lives in its own encrypted table (BakongToken) and is never
 * rendered anywhere.
 */
class PlatformPaymentSetting extends Model
{
    protected $fillable = [
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'khqr_image_path',
        'bakong_account_id',
        'merchant_name',
        'merchant_city',
        'currency',
    ];

    /** The singleton row, or null when the operator has never saved one. */
    public static function current(): ?self
    {
        return static::query()->first();
    }
}
