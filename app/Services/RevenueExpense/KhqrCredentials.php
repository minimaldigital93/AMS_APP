<?php

namespace App\Services\RevenueExpense;

use App\Models\MerchantPaymentSetting;
use App\Models\PlatformPaymentSetting;

/**
 * KHQRPay signing context for one payment. Resolves WHOSE account the money
 * settles to:
 *
 *  - platform():    the super admin's credentials — subscription payments only
 *                   (Flow A). Read from platform_payment_settings (superadmin
 *                   panel) when set, falling back to config/services.php (.env).
 *  - forMerchant(): a landlord's own credentials (merchant_payment_settings) —
 *                   tenant rent payments (Flow B). Rent money must never be
 *                   minted against the platform profile.
 */
final readonly class KhqrCredentials
{
    public function __construct(
        public string $profileId,
        public string $secret,
        public string $baseUrl,
        public string $currency,
    ) {}

    public static function platform(): self
    {
        $db = PlatformPaymentSetting::current();

        return new self(
            profileId: (string) (($db?->khqrpay_profile_id) ?: config('services.khqrpay.profile_id')),
            secret: (string) (($db?->khqrpay_secret) ?: config('services.khqrpay.secret')),
            baseUrl: (string) config('services.khqrpay.base_url'),
            currency: (string) (($db?->currency) ?: config('services.khqrpay.currency', 'USD')),
        );
    }

    public static function forMerchant(MerchantPaymentSetting $settings): self
    {
        return new self(
            profileId: (string) $settings->khqrpay_profile_id,
            secret: (string) $settings->khqrpay_secret,
            baseUrl: (string) config('services.khqrpay.base_url'),
            currency: (string) ($settings->currency ?: 'USD'),
        );
    }
}
