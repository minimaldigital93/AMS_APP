<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure 'trash' is included in the utilities.utility_type enum (standardized keyword)
        DB::statement("ALTER TABLE utilities MODIFY COLUMN utility_type ENUM('electricity', 'water', 'internet', 'trash', 'parking')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous set (same as up here)
        DB::statement("ALTER TABLE utilities MODIFY COLUMN utility_type ENUM('electricity', 'water', 'internet', 'trash', 'parking')");
    }
};
