<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'moved_out' to the status ENUM while keeping existing values
        DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('active', 'pending', 'inactive', 'moved_out') DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the original ENUM values
        DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('active', 'pending', 'inactive') DEFAULT 'active'");
    }
};
