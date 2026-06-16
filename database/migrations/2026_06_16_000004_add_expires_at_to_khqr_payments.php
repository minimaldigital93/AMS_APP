<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit QR lifetime. Previously expiry was implied by the reconcile cron
 * (created_at + 30min); storing it lets the checkout page show a countdown and
 * lets the status poll lazily expire a dead QR the moment it's hit, instead of
 * waiting up to five minutes for the cron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('paid_at');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
