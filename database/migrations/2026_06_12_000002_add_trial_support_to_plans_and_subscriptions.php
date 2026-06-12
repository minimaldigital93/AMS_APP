<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('trial_days')->default(0)->after('billing_period_days');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Non-null once a trial has been started — one free trial per account, ever.
            $table->timestamp('trial_started_at')->nullable()->after('expires_at');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('trial_days');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn('trial_started_at');
        });
    }
};
