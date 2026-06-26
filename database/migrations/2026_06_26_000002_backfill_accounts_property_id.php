<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfill accounts.property_id for legacy ledger rows recorded before the
 * per-property books existed (or by write paths that forgot to stamp it).
 *
 * A null property_id makes a row visible under *every* property (see
 * Accounts::scopeForProperty's orWhereNull), which is why apartment-specific
 * income/expense was leaking across buildings. We attribute each row to its
 * apartment's property, derived from either:
 *   - the linked payment  → rental → apartment → floor → property, or
 *   - the rental encoded in a "deposit:rental:{id}" reference (deposit rows
 *     carry no payment).
 *
 * Rows with no derivable property (genuinely account-wide entries) are left
 * null on purpose. Written with PHP loops rather than a multi-table UPDATE so
 * it runs identically on MySQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Payment-linked rows: derive from the payment's apartment.
        DB::table('accounts')
            ->whereNull('property_id')
            ->whereNotNull('payment_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
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

        // 2) Deposit rows (no payment): derive from the rental in the reference.
        DB::table('accounts')
            ->whereNull('property_id')
            ->where('reference_number', 'like', 'deposit:rental:%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $rentalId = (int) Str::afterLast($row->reference_number, ':');

                    if ($rentalId <= 0) {
                        continue;
                    }

                    $propertyId = DB::table('rentals')
                        ->join('apartments', 'apartments.id', '=', 'rentals.apartment_id')
                        ->join('floors', 'floors.id', '=', 'apartments.floor_id')
                        ->where('rentals.id', $rentalId)
                        ->value('floors.property_id');

                    if ($propertyId !== null) {
                        DB::table('accounts')->where('id', $row->id)->update(['property_id' => $propertyId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Irreversible data backfill — we can't tell which rows were null before.
    }
};
