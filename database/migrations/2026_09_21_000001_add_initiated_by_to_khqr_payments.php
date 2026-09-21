<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHO started this payment.
 *
 * Until tenants could pay for themselves every rent QR was minted by the
 * person collecting, so the question never arose and the row never answered
 * it. It matters now for one concrete reason: a tenant-initiated session is
 * money the landlord has not seen yet and must be shown for confirmation,
 * while a landlord-initiated one is already on the screen that made it.
 *
 * Nullable, and null means "minted before this existed" — every historical row
 * keeps reading correctly rather than being back-filled with a guess about who
 * was at the keyboard. onDelete('set null') for the same reason the tenants
 * table uses it: deleting a login must never take a money record with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->foreignId('initiated_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->dropForeign(['initiated_by_user_id']);
            $table->dropColumn('initiated_by_user_id');
        });
    }
};
