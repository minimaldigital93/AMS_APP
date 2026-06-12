<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * settlement_target: whose credentials/bank the payment settles to —
 *   'platform' = Flow A subscription payment (super admin's KHQRPay account)
 *   'merchant' = Flow B rent payment (landlord's own bank / KHQRPay account)
 * channel: 'api' = KHQRPay dynamic QR (auto-verified) | 'manual' = static KHQR
 *   image, confirmed by the landlord after checking their banking app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->string('settlement_target', 16)->default('platform')->after('status');
            $table->string('channel', 16)->default('api')->after('settlement_target');
            $table->index(['status', 'channel']);
        });

        // Every pre-existing rental-linked row was minted for a rent payment.
        DB::table('khqr_payments')->whereNotNull('rental_id')->update(['settlement_target' => 'merchant']);
    }

    public function down(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'channel']);
            $table->dropColumn(['settlement_target', 'channel']);
        });
    }
};
