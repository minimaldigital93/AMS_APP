<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable log of every inbound payment webhook (KHQRPay today, any provider
 * later). Two jobs:
 *   1. Idempotency at the HTTP edge — `event_id` is unique, so a duplicate /
 *      replayed delivery is recognised and acked (200) before it can re-run
 *      finalize, independent of the row lock further down.
 *   2. Forensics — the raw payload + outcome of every delivery (incl. invalid
 *      signatures and unknown transactions) is retained for dispute/debug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->default('khqrpay');
            // Provider event id, or the payload signature hash as a stable fallback.
            $table->string('event_id')->unique();
            $table->string('transaction_id')->nullable()->index();
            $table->unsignedBigInteger('khqr_payment_id')->nullable()->index();
            // received | processed | duplicate | invalid | ignored
            $table->string('status', 20)->default('received');
            $table->boolean('signature_valid')->default(false);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('payload');
            $table->text('error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
