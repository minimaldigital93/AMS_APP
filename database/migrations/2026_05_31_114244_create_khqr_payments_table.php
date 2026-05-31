<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracking table for KHQRPay (khqr.cc) dynamic QR payments.
     *
     * One row per generated QR. The full checkout context is stored in
     * `checkout_payload` so the payment can be recorded server-side (via
     * IncomeRecordingService::checkout) once Bakong confirms it — either
     * from the status poll or the webhook callback. This keeps recording
     * idempotent and independent of the browser.
     */
    public function up(): void
    {
        Schema::create('khqr_payments', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $table->unsignedBigInteger('fiscal_period_id');
            $table->unsignedBigInteger('user_id'); // ledger owner (admin id on both roles)
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('USD');
            $table->enum('status', ['pending', 'paid', 'expired'])->default('pending');
            $table->json('checkout_payload');
            $table->string('qr_url')->nullable();
            $table->string('provider_ref')->nullable(); // KHQRPay tran / md5
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['rental_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('khqr_payments');
    }
};
