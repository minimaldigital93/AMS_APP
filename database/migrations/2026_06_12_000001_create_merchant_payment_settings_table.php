<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-account (merchant) payment destination for tenant rent payments.
 *
 * Flow B money (tenant → landlord) must settle in the LANDLORD's bank account,
 * never the platform's. This table holds the landlord's bank details + static
 * KHQR image (manual channel) and optional KHQRPay API credentials (api channel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_payment_settings', function (Blueprint $table) {
            $table->id();
            // account = owning admin user id, same convention as BelongsToAccount
            $table->foreignId('account_id')->unique()->constrained('users')->cascadeOnDelete();

            // Manual / display info (shown on checkout + invoices)
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('khqr_image_path')->nullable();

            // Optional KHQRPay API credentials — enables dynamic-QR auto-verification
            $table->boolean('khqrpay_enabled')->default(false);
            $table->string('khqrpay_profile_id')->nullable();
            $table->text('khqrpay_secret')->nullable(); // encrypted cast on the model
            $table->string('bakong_account_id')->nullable();
            $table->string('currency', 3)->default('USD');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_payment_settings');
    }
};
