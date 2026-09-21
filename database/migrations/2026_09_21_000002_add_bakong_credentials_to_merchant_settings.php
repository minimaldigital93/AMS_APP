<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The LANDLORD's own Bakong credential — what makes a tenant's rent payment
 * confirm itself.
 *
 * This table shipped with `khqrpay_enabled` / `khqrpay_secret`, commented
 * "Optional API credentials — enables dynamic-QR auto-verification". Those were
 * dropped in 2026-09 with the khqr.cc provider. The capability died with the
 * provider, not with the idea; this restores it against Bakong.
 *
 * WHY PER-LANDLORD rather than reusing the platform token. Rent settles into
 * the landlord's own bank account, which the platform's credential has no
 * business asking about — and the platform allowance is ~80 requests a DAY for
 * the whole installation, shared with subscriptions. Every tenant of every
 * landlord verifying against it would let one busy rent day lock out everyone,
 * including signups. On the landlord's own token the money, the credential and
 * the quota all belong to the same party.
 *
 * `bakong_enabled` is a NULLABLE boolean on purpose, the same seam
 * platform_payment_settings uses: null means "not set here", never false. A
 * false default would be a decision this migration has no business making on
 * behalf of an existing account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_payment_settings', function (Blueprint $table) {
            $table->boolean('bakong_enabled')->nullable()->after('bakong_account_id');
            // Encrypted at rest via the model's `encrypted` cast, exactly as
            // khqrpay_secret was. Never rendered back to the form.
            $table->text('bakong_token')->nullable()->after('bakong_enabled');
            // Read out of the JWT at import. Null = undecodable, which leaves
            // the token usable ("unknown, try it") but never auto-renewed —
            // the same reading BakongToken::isUsable() already applies.
            $table->timestamp('bakong_token_expires_at')->nullable()->after('bakong_token');
            $table->timestamp('bakong_token_imported_at')->nullable()->after('bakong_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_payment_settings', function (Blueprint $table) {
            $table->dropColumn([
                'bakong_enabled',
                'bakong_token',
                'bakong_token_expires_at',
                'bakong_token_imported_at',
            ]);
        });
    }
};
