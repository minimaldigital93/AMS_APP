<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            // A KHQR payment is either a rental checkout (rental_id + fiscal_period_id)
            // OR a plan subscription payment (subscription_id). The rental columns are
            // therefore nullable for subscription payments.
            $table->unsignedBigInteger('subscription_id')->nullable()->after('rental_id');
            $table->unsignedBigInteger('fiscal_period_id')->nullable()->change();
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->index('subscription_id');
        });

        // rental_id carries a FK + NOT NULL; drop the constraint, make it nullable,
        // then re-add the FK so subscription payments can leave it null.
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->dropForeign(['rental_id']);
        });
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('rental_id')->nullable()->change();
            $table->foreign('rental_id')->references('id')->on('rentals')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->dropIndex(['subscription_id']);
            $table->dropColumn('subscription_id');
        });
    }
};
