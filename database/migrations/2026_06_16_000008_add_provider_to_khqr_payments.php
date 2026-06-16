<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which payment provider minted this charge. Single provider today (khqrpay),
 * but carrying it on the row is what lets PaymentManager resolve the right
 * gateway driver per transaction when a second provider is added later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->string('provider', 32)->default('khqrpay')->after('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
