<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

/**
 * A landlord's payment destination for tenant rent payments (one row per
 * account). Rent money settles directly in the landlord's bank — the platform
 * never holds it, and never sees it arrive.
 *
 * ONE CHANNEL since 2026-09. The 'api' channel — the landlord's own khqr.cc
 * credentials minting dynamic, auto-verified QRs — went with the provider, and
 * with it the only secret this table ever held. What is left is a KHQR built on
 * this server from bakong_account_id (or the uploaded static image, or plain
 * bank details), which the landlord confirms by hand.
 *
 * That is not a downgrade so much as an honest accounting: verifying rent
 * through the PLATFORM's Bakong token would put every landlord's tenants on one
 * ~100-request daily allowance, and route a rent payment's confirmation through
 * credentials belonging to an account the money never touches.
 *
 * NOTHING HERE IS A SECRET. A Bakong account id and a bank account number are
 * printed on the QR and read out to tenants.
 */
class MerchantPaymentSetting extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'khqr_image_path',
        'bakong_account_id',
        'bakong_enabled',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            // Encrypted at rest, the way the retired provider's secret column
            // was — this is a live bearer credential for the landlord's bank.
            'bakong_token' => 'encrypted',
            'bakong_token_expires_at' => 'datetime',
            'bakong_token_imported_at' => 'datetime',
            // Nullable on purpose: null means "not set", never false.
            'bakong_enabled' => 'boolean',
        ];
    }

    /**
     * The settings row for an account, created if this is the first time the
     * landlord has saved anything. Bypasses the account scope for the same
     * reason forAccount() does — it is addressed by explicit account id.
     */
    public static function forAccountOrNew(int $accountId): self
    {
        $existing = static::forAccount($accountId);

        if ($existing) {
            return $existing;
        }

        $row = new static;
        $row->forceFill(['account_id' => $accountId]);

        return $row;
    }

    /** Resolve (or start) the settings row for an account, bypassing the scope. */
    public static function forAccount(?int $accountId): ?self
    {
        if ($accountId === null) {
            return null;
        }

        return static::withoutAccountScope()->where('account_id', $accountId)->first();
    }

    /** There is something to show a tenant beyond a generated QR. */
    public function canUseManual(): bool
    {
        return filled($this->khqr_image_path) || filled($this->bank_account_number);
    }
}
