<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which price an account bought — 'monthly' (bills plan.billing_period_days, default
 * 30) or 'yearly' (bills 365 days at price_yearly_usd). Drives renewal length and
 * the price snapshot. Plain string (not a DB enum) per the MySQL-strict-enum note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_cycle')->default('monthly')->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });
    }
};
