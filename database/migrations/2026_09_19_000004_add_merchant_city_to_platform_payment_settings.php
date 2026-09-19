<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The merchant city printed inside every KHQR.
 *
 * platform_payment_settings already carries bakong_account_id, merchant_name
 * and currency — every other field the QR needs — because the KHQRPay flow put
 * the platform's identity in the database rather than in .env, so the operator
 * could change it from their own settings page. City was the one piece still
 * hard-coded, and with the direct Bakong integration it is no longer incidental:
 * AMS builds the payload itself now, so what a payer sees in their banking app
 * comes from here.
 *
 * Nullable, with no backfill: a blank column falls through to the .env value
 * (and then to "Phnom Penh"), so every existing row keeps behaving exactly as
 * it did. That null is the backward-compatibility seam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->string('merchant_city')->nullable()->after('merchant_name');
        });
    }

    public function down(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->dropColumn('merchant_city');
        });
    }
};
