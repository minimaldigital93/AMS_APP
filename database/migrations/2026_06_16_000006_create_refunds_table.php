<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A refund recorded against a paid payment. KHQR/Bakong dynamic QR has no
 * programmatic refund, so the actual money movement is an out-of-band bank
 * transfer by the super admin — this table makes that reversal auditable and
 * reconcilable in the platform P&L, and flips the payment to REFUNDED.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('khqr_payment_id')->constrained('khqr_payments')->cascadeOnDelete();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('USD');
            $table->string('reason')->nullable();
            // requested | processing | completed | failed
            $table->string('status', 20)->default('completed');
            $table->unsignedBigInteger('initiated_by')->nullable(); // super admin user id
            $table->string('provider_ref')->nullable();             // bank transfer reference
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['khqr_payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
