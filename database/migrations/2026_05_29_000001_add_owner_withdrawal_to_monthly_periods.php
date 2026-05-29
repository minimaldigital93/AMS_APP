<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_periods', function (Blueprint $table) {
            // Owner's profit withdrawal (a "draw"/distribution) captured when the
            // month is closed. This is NOT an expense — it does not touch
            // net_income or the Accounts ledger. It only reduces the cash that is
            // carried forward (closing_balance) and the owner's equity.
            $table->decimal('owner_withdrawal', 15, 2)->default(0)->after('net_income');
            $table->text('withdrawal_note')->nullable()->after('owner_withdrawal');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_periods', function (Blueprint $table) {
            $table->dropColumn(['owner_withdrawal', 'withdrawal_note']);
        });
    }
};
