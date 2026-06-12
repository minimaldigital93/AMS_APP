<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * subscriptions.status gains 'trialing'; khqr_payments.status gains 'rejected'
 * (manual channel). Converting the enum columns to plain strings lifts the DB
 * CHECK/ENUM constraint on every driver (sqlite included) — the allowed values
 * are enforced in the app layer (Subscription / KhqrPaymentService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('status', 16)->default('pending')->change();
        });

        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->string('status', 16)->default('pending')->change();
        });
    }

    public function down(): void
    {
        // Strings accept the old enum values — nothing to restore.
    }
};
