<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed lease term (in months) chosen at assignment time: 3, 6 or 12.
 *
 * The lease stays a rolling monthly tenancy (end_date is left NULL so occupancy
 * and stay-progress are unaffected); this column only records the agreed term so
 * the printed contract can state a duration and the app can flag a contract as
 * overdue once start_date + term has passed without a renewal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->unsignedSmallInteger('contract_term_months')->nullable()->after('payment_due_day');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('contract_term_months');
        });
    }
};
