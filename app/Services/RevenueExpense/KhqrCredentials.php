<?php

namespace App\Services\RevenueExpense;

use App\Models\MerchantPaymentSetting;
use App\Models\PlatformPaymentSetting;

/**
 * KHQRPay signing context for one payment. Resolves WHOSE account the money
 * settles to:
 *
 *  - platform():    the super admin's credentials — subscription payments only
 *                   (Flow A). Read SOLELY from platform_payment_settings (the
 *                   superadmin Payment Settings panel). The profile id, secret
 *                   and currency are NOT taken from .env — only base_url (the
 *                   fixed khqr.cc endpoint) still comes from config.
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
            profileId: (string) $db?->khqrpay_profile_id,
            secret: (string) $db?->khqrpay_secret,
            baseUrl: (string) config('services.khqrpay.base_url'),
            currency: (string) ($db?->currency ?: 'USD'),
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
