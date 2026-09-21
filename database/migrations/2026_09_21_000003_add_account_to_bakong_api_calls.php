<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHOSE allowance a call was charged to.
 *
 * `target` already separates 'platform' from 'merchant', but every landlord
 * would share the single 'merchant' budget — which is the same shared-allowance
 * failure this whole design exists to avoid, just one level down: one building's
 * busy rent day would exhaust the budget for every other landlord.
 *
 * Nullable, and null means the platform's own spend (subscriptions, token
 * lifecycle) — so every historical row keeps its meaning without a back-fill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakong_api_calls', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('target')
                ->constrained('users')
                ->nullOnDelete();

            // The question this table is asked on every single gate check:
            // "how much has THIS budget spent TODAY?"
            $table->index(['called_on', 'target', 'account_id'], 'bakong_calls_budget_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bakong_api_calls', function (Blueprint $table) {
            $table->dropIndex('bakong_calls_budget_idx');
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
