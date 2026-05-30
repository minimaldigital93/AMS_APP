<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ENUM ALTER is MySQL-only; SQLite stores ENUM as TEXT so no change needed.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('active', 'pending', 'inactive', 'moved_out') DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('active', 'pending', 'inactive') DEFAULT 'active'");
    }
};
