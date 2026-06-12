<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

/**
 * A landlord's payment destination for tenant rent payments (one row per
 * account). Rent money settles directly in the landlord's bank — the platform
 * never holds it. Two channels:
 *
 *  - manual: static KHQR image + bank details shown at checkout; the landlord
 *    confirms receipt by hand after checking their banking app.
 *  - api:    the landlord's own KHQRPay credentials mint dynamic QRs that are
 *    auto-verified (see KhqrPaymentService / KhqrCredentials).
 *
 * The KHQRPay secret is encrypted at rest and must never be rendered back to
 * any UI (superadmin included) — only a "configured" indicator.
 */
class MerchantPaymentSetting extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'khqr_image_path',
        'khqrpay_enabled',
        'khqrpay_profile_id',
        'khqrpay_secret',
        'bakong_account_id',
        'currency',
    ];

    protected $hidden = ['khqrpay_secret'];

    protected function casts(): array
    {
        return [
            'khqrpay_secret' => 'encrypted',
            'khqrpay_enabled' => 'boolean',
        ];
    }

    /** Resolve (or start) the settings row for an account, bypassing the scope. */
    public static function forAccount(?int $accountId): ?self
    {
        if ($accountId === null) {
            return null;
        }

        return static::withoutAccountScope()->where('account_id', $accountId)->first();
    }

    /** Dynamic-QR auto-verification is available. */
    public function canUseApi(): bool
    {
        return $this->khqrpay_enabled
            && filled($this->khqrpay_profile_id)
            && filled($this->khqrpay_secret);
    }

    /** The static-image manual channel is available. */
    public function canUseManual(): bool
    {
        return filled($this->khqr_image_path) || filled($this->bank_account_number);
    }
}
