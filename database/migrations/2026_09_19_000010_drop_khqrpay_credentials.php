<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the khqr.cc credentials from both payment-settings tables.
 *
 * The provider was retired in 2026-09 and its client deleted, so nothing can
 * read these columns any more. That is reason enough to drop them, but not the
 * main one: `khqrpay_secret` IS A LIVE CREDENTIAL for a third-party account,
 * and a credential nothing uses is a credential nobody rotates, nobody notices
 * in a backup, and nobody misses when it leaks. Decommissioning the integration
 * without removing the key would be leaving the lock on a door we no longer own.
 *
 * WHAT THIS DOES NOT TOUCH, deliberately:
 *
 *  - khqr_payments. Every row there is a money record, including the ones
 *    minted at khqr.cc. `provider` still says 'khqrpay' on them and still
 *    resolves (RetiredKhqrPayGateway), so a settled payment from last quarter
 *    keeps reading correctly on the payments console and in platform finance.
 *  - payment_webhooks. The deliveries khqr.cc made are an audit trail of money
 *    that actually arrived; the endpoint is gone, the history stays.
 *  - bank details, khqr_image_path, bakong_account_id, merchant_name/city.
 *    Those are the channels that survived, not the one that was removed.
 *
 * down() restores the columns but NOT the values — an encrypted secret cannot
 * be un-dropped. Rolling back gives you the shape, not the credential, and the
 * credential should be re-issued at khqr.cc rather than recovered. It is also
 * worth doing there regardless: a secret that has been in a database this app
 * no longer protects should be revoked at the provider, not merely deleted here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->dropColumn(['khqrpay_profile_id', 'khqrpay_secret']);
        });

        Schema::table('merchant_payment_settings', function (Blueprint $table) {
            $table->dropColumn(['khqrpay_enabled', 'khqrpay_profile_id', 'khqrpay_secret']);
        });
    }

    public function down(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->string('khqrpay_profile_id')->nullable()->after('khqr_image_path');
            $table->text('khqrpay_secret')->nullable()->after('khqrpay_profile_id');
        });

        Schema::table('merchant_payment_settings', function (Blueprint $table) {
            $table->boolean('khqrpay_enabled')->default(false)->after('khqr_image_path');
            $table->string('khqrpay_profile_id')->nullable()->after('khqrpay_enabled');
            $table->text('khqrpay_secret')->nullable()->after('khqrpay_profile_id');
        });
    }
};
