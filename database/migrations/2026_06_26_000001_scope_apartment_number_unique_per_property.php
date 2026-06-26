<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scope apartment_number uniqueness to the property instead of the whole account.
 *
 * Apartments reach a property through their floor, so we denormalize property_id
 * onto apartments and let a real DB unique index enforce "unit number unique
 * within a property" — while letting two different properties in the same account
 * each have a unit "101".
 *
 * The previous index was (account_id, apartment_number, deleted_at). Per-property
 * is a relaxation of per-account (a property's apartments are a subset of the
 * account's), so any data that satisfied the old index also satisfies the new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->nullable()->after('floor_id');
        });

        // Backfill from each apartment's floor — including soft-deleted rows so the
        // composite unique index (which includes deleted_at) stays consistent.
        DB::table('apartments')->update([
            'property_id' => DB::raw('(select property_id from floors where floors.id = apartments.floor_id)'),
        ]);

        Schema::table('apartments', function (Blueprint $table) {
            $table->dropUnique('apartments_account_apartment_number_deleted_at_unique');

            // property_id is the leftmost column, so this index also serves the
            // FiltersByProperty `where property_id = ?` lookups (no separate index).
            $table->unique(
                ['property_id', 'apartment_number', 'deleted_at'],
                'apartments_property_apartment_number_deleted_at_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropUnique('apartments_property_apartment_number_deleted_at_unique');

            $table->unique(
                ['account_id', 'apartment_number', 'deleted_at'],
                'apartments_account_apartment_number_deleted_at_unique'
            );

            $table->dropColumn('property_id');
        });
    }
};
