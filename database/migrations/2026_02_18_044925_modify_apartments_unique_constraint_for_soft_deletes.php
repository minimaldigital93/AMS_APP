<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            // Drop the existing unique constraint on apartment_number
            $table->dropUnique(['apartment_number']);

            // Add a composite unique constraint that includes deleted_at
            // This allows the same apartment_number to exist multiple times
            // as long as only one record has deleted_at = NULL (active record)
            $table->unique(['apartment_number', 'deleted_at'], 'apartments_apartment_number_deleted_at_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('apartments_apartment_number_deleted_at_unique');

            // Restore the original unique constraint on apartment_number only
            $table->unique('apartment_number');
        });
    }
};
