<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business (overhead) expenses now belong to a Property, so each property keeps
 * its own P&L while still sharing the account's fiscal period. A null property_id
 * means an account-wide expense (recorded under "All properties") and stays
 * visible under every property — matching the accounts.property_id convention.
 *
 * Nullable + indexed (no FK constraint, matching account_id / accounts.property_id).
 * Backfill: each row already wrote a mirror ledger row (accounts, category
 * business_variable) carrying the active property at the time — reuse it. Rows
 * with no resolvable mirror fall back to the owning account's first property so
 * nothing disappears from the per-property view after upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->nullable()->after('fiscal_period_id');
            $table->index('property_id');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->dropIndex(['property_id']);
            $table->dropColumn('property_id');
        });
    }

    /**
     * Resolve each business expense's property without leaning on any specific
     * SQL dialect (tests run on SQLite, production on MySQL).
     */
    private function backfill(): void
    {
        $firstPropertyByAccount = [];

        DB::table('business_expenses')
            ->whereNull('property_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$firstPropertyByAccount) {
                foreach ($rows as $row) {
                    // 1) Reuse the property stamped on this expense's mirror ledger row.
                    $propertyId = DB::table('accounts')
                        ->where('user_id', $row->user_id)
                        ->where('fiscal_period_id', $row->fiscal_period_id)
                        ->where('account_type', 'expense')
                        ->where('category', 'business_variable')
                        ->where('amount', $row->amount)
                        ->where('transaction_date', $row->expense_date)
                        ->where('description', '[Business] '.$row->expense_name)
                        ->whereNotNull('property_id')
                        ->value('property_id');

                    // 2) Fall back to the owning account's first property (user_id is
                    //    the account-owning admin, which properties.account_id points to).
                    if ($propertyId === null) {
                        if (! array_key_exists($row->user_id, $firstPropertyByAccount)) {
                            $firstPropertyByAccount[$row->user_id] = DB::table('properties')
                                ->where('account_id', $row->user_id)
                                ->whereNull('deleted_at')
                                ->orderBy('id')
                                ->value('id');
                        }
                        $propertyId = $firstPropertyByAccount[$row->user_id];
                    }

                    if ($propertyId !== null) {
                        DB::table('business_expenses')->where('id', $row->id)->update(['property_id' => $propertyId]);
                    }
                }
            });
    }
};
