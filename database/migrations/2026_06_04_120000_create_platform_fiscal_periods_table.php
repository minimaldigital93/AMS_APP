<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscal periods for the platform (SaaS) P&L — a span from a start month to an
 * end month (e.g. Apr 2026 → Mar 2027). The superadmin creates these to name a
 * period, set its opening cash balance, and (once every month is closed) lock it.
 *
 * A period is a wrapper around a range of calendar months: the month-by-month
 * P&L is still computed live from payments/expenses (see PlatformFinanceService);
 * this row carries the name, date range, opening balance, and open/closed status.
 *
 * Intentionally NOT account-scoped — this is the platform operator's own ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');                              // first month of the period (any day in it)
            $table->date('end_date');                                // last month of the period (any day in it)
            $table->decimal('opening_balance', 12, 2)->default(0);   // starting cash carried into the first month
            $table->string('status')->default('open');               // open | closed
            $table->decimal('closing_balance', 12, 2)->default(0);   // carried-forward cash at period end (set on close)
            $table->decimal('withdrawn_total', 12, 2)->default(0);   // total taken by the owner over the period (set on close)
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fiscal_periods');
    }
};
