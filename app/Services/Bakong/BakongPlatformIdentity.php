<?php

namespace App\Services\Bakong;

use App\Models\PlatformPaymentSetting;

/**
 * WHO the platform is, and WHERE subscription money lands.
 *
 * Read in this order, and the order is the point:
 *
 *   1. platform_payment_settings — the superadmin's own Payment Settings page
 *   2. config/bakong.php (.env)  — the fallback
 *
 * The order is inherited from the retired KHQRPay credential resolver, for the
 * reason that outlived it: **the person who needs to change a payout account is
 * not the person with shell access.** A payout account that can only be changed
 * by editing .env and running config:cache is a payout account that gets changed
 * late, under pressure, by whoever happens to have SSH — which is exactly the
 * moment not to be editing production files.
 *
 * The .env fallback is not dead weight. It is what makes a fresh install, a CI
 * run and a local demo work before anyone has opened the settings page, and it
 * is how this integration was configured before the page learned these fields.
 *
 * NOTHING HERE IS A SECRET. The Bakong account id is printed inside every QR
 * the payer scans, and the merchant name and city are shown in their banking
 * app. They are identity, not credentials — which is why they belong in a form
 * the operator can read back, while the ACCESS TOKEN deliberately does not (see
 * BakongToken, encrypted and never rendered).
 */
final readonly class BakongPlatformIdentity
{
    public function __construct(
        public string $accountId,
        public string $merchantName,
        public string $merchantCity,
        public string $currency,
    ) {}

    public static function current(): self
    {
        $db = PlatformPaymentSetting::current();

        return new self(
            // A blank column falls through to .env rather than overriding it
            // with emptiness — the saved row may predate these fields entirely.
            accountId: self::pick($db?->bakong_account_id, config('bakong.account_id')),
            merchantName: self::pick($db?->merchant_name, config('bakong.merchant_name')) ?: 'AMS',
            merchantCity: self::pick($db?->merchant_city, config('bakong.merchant_city')) ?: 'Phnom Penh',
            currency: strtoupper(self::pick($db?->currency, config('bakong.currency')) ?: 'USD'),
        );
    }

    /**
     * Can a QR actually be built from this?
     *
     * The account id is the only field without a sensible default: a name and a
     * city are cosmetic, but an absent account id would silently produce a QR
     * that collects nothing — or, with a placeholder, someone else's money.
     */
    public function isConfigured(): bool
    {
        return $this->accountId !== '';
    }

    /** Where the operator should go to fix it — the page, not the server. */
    public function source(): string
    {
        return filled(PlatformPaymentSetting::current()?->bakong_account_id)
            ? 'Payment Settings'
            : '.env (BAKONG_ACCOUNT_ID)';
    }

    private static function pick(?string $preferred, mixed $fallback): string
    {
        return trim((string) ($preferred ?: $fallback ?: ''));
    }
}
