<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad-hoc owner withdrawals against a fiscal period's carried-forward cash. The
 * superadmin can take money out at any time while the period is open — separate
 * from the month-end close decision (see platform_monthly_closes).
 *
 * Each row reduces the period's carried-forward balance and adds to the total
 * withdrawn (see PlatformFinanceService::forPeriod). Intentionally NOT
 * account-scoped — this is the platform operator's own ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_fiscal_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->date('withdrawn_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_withdrawals');
    }
};
