<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relax apartment_number uniqueness from per-property to per-floor.
 *
 * A unit number now only has to be unique within its own floor, so one building
 * can have a "101" on every floor (floor 1 unit 101, floor 2 unit 101, …) while
 * the same floor still can't list "101" twice.
 *
 * Per-floor is a relaxation of the previous per-property index (a floor's
 * apartments are a subset of its property's), so every row that satisfied the
 * old index also satisfies this one — no data conflicts. property_id stays as a
 * denormalized convenience column (read by ledger rows); it just no longer
 * carries a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropUnique('apartments_property_apartment_number_deleted_at_unique');

            $table->unique(
                ['floor_id', 'apartment_number', 'deleted_at'],
                'apartments_floor_apartment_number_deleted_at_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropUnique('apartments_floor_apartment_number_deleted_at_unique');

            $table->unique(
                ['property_id', 'apartment_number', 'deleted_at'],
                'apartments_property_apartment_number_deleted_at_unique'
            );
        });
    }
};
