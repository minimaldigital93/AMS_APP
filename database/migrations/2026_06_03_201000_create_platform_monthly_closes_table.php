<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the month-end close decision for the platform (SaaS) P&L. When the
 * superadmin closes a month they choose to either withdraw that month's profit
 * or carry it forward — this table snapshots that decision so the running
 * carried-forward balance stays stable across page loads and recomputes.
 *
 * A row exists only for CLOSED months; an open month simply has no row.
 * Intentionally NOT account-scoped — this is the platform operator's own ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_monthly_closes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->decimal('net_income', 12, 2)->default(0);     // profit snapshot at close
            $table->decimal('owner_withdrawal', 12, 2)->default(0); // 0 = carry forward
            $table->string('withdrawal_note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_monthly_closes');
    }
};
