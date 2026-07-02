<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link each BusinessExpense to its mirror Accounts ledger row by id.
 *
 * deleteBusinessExpense() used to hunt for the mirror row by matching
 * description+amount+date — with two identical expenses it could delete the
 * wrong twin's ledger row. New rows store the FK at creation; legacy rows
 * (null) keep the old heuristic as a fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->foreignId('ledger_entry_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_entry_id');
        });
    }
};
