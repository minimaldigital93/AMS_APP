<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger entries (accounts) now belong to a Property, so each property keeps its
 * own income/expense books while still sharing the account's fiscal period.
 *
 * Nullable + indexed (no FK constraint, matching account_id / floors.property_id).
 * Backfill: rows tied to a payment derive their property through
 * payment → rental → apartment → floor; remaining (standalone) expense rows fall
 * back to the owner account's first property so nothing disappears from the
 * per-property view after upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->nullable()->after('fiscal_period_id');
            $table->index('property_id');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['property_id']);
            $table->dropColumn('property_id');
        });
    }

    /**
     * Resolve each ledger row's property without leaning on any specific SQL
     * dialect (tests run on SQLite, production on MySQL).
     */
    private function backfill(): void
    {
        // 1) Rows linked to a payment → derive the property from the rental's room.
        DB::table('accounts')
            ->whereNull('property_id')
            ->whereNotNull('payment_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $propertyId = DB::table('payments')
                        ->join('rentals', 'rentals.id', '=', 'payments.rental_id')
                        ->join('apartments', 'apartments.id', '=', 'rentals.apartment_id')
                        ->join('floors', 'floors.id', '=', 'apartments.floor_id')
                        ->where('payments.id', $row->payment_id)
                        ->value('floors.property_id');

                    if ($propertyId !== null) {
                        DB::table('accounts')->where('id', $row->id)->update(['property_id' => $propertyId]);
                    }
                }
            });

        // 2) Remaining rows (standalone expenses, late fees on deleted payments,
        //    etc.) → the owning account's first property, so they stay visible
        //    once a property is selected.
        $firstPropertyByAccount = [];

        DB::table('accounts')
            ->whereNull('property_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$firstPropertyByAccount) {
                foreach ($rows as $row) {
                    if ($row->account_id === null) {
                        continue; // legacy unowned row — leave null (visible to all)
                    }

                    if (! array_key_exists($row->account_id, $firstPropertyByAccount)) {
                        $firstPropertyByAccount[$row->account_id] = DB::table('properties')
                            ->where('account_id', $row->account_id)
                            ->whereNull('deleted_at')
                            ->orderBy('id')
                            ->value('id');
                    }

                    $propertyId = $firstPropertyByAccount[$row->account_id];
                    if ($propertyId !== null) {
                        DB::table('accounts')->where('id', $row->id)->update(['property_id' => $propertyId]);
                    }
                }
            });
    }
};
