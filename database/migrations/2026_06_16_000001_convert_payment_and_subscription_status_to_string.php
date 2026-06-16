<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Move the two payment-related status columns off DB `enum` and onto plain
 * VARCHAR, validated in PHP via App\Enums\PaymentStatus / SubscriptionStatus.
 *
 * The enum columns silently rejected legitimate values the code already writes
 * under MySQL strict mode:
 *   - khqr_payments.status   never included 'rejected' (manual reject → SQL error)
 *   - subscriptions.status   never included 'trialing' (trial signup → SQL error)
 * SQLite (tests) accepted them, so this only failed in production.
 *
 * Existing values are plain strings, so the conversion needs no data backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'paid', 'expired'])->default('pending')->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending')->change();
        });
    }
};
