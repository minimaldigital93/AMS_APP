<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SUPER ADMIN's payment destination for subscription payments (Flow A) —
 * a single row, editable from the superadmin panel, so the platform operator
 * can configure their bank + KHQRPay credentials without touching .env.
 * Values here take precedence over config/services.khqrpay; blank fields fall
 * back to the env-based config (see KhqrCredentials::platform()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payment_settings', function (Blueprint $table) {
            $table->id();

            // Display / manual info
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('khqr_image_path')->nullable();

            // KHQRPay API credentials (subscription dynamic QRs)
            $table->string('khqrpay_profile_id')->nullable();
            $table->text('khqrpay_secret')->nullable(); // encrypted cast on the model

            // Used by the locally generated Bakong KHQR + QR display name
            $table->string('bakong_account_id')->nullable();
            $table->string('merchant_name')->nullable();
            $table->string('currency', 3)->default('USD');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payment_settings');
    }
};
