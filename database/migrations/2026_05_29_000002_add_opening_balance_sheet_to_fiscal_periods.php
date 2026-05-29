<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opening balance sheet captured once when a fiscal period is opened.
 *
 * The owner enters starting Assets / Liabilities / Equity (which must balance:
 * Assets = Liabilities + Equity). From there the balance sheet is rolled
 * forward automatically each month from the Accounts ledger — no more manual
 * line-item entry. opening_balance (the cash carry-forward seed) is set equal
 * to opening_assets so the monthly closing balance tracks total assets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->decimal('opening_assets', 15, 2)->default(0)->after('opening_balance');
            $table->decimal('opening_liabilities', 15, 2)->default(0)->after('opening_assets');
            $table->decimal('opening_equity', 15, 2)->default(0)->after('opening_liabilities');
        });

        // Backfill existing periods: treat the historical opening cash balance as
        // the opening assets, funded entirely by owner's equity (no liabilities).
        DB::table('fiscal_periods')->update([
            'opening_assets' => DB::raw('opening_balance'),
            'opening_equity' => DB::raw('opening_balance'),
        ]);
    }

    public function down(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->dropColumn(['opening_assets', 'opening_liabilities', 'opening_equity']);
        });
    }
};
