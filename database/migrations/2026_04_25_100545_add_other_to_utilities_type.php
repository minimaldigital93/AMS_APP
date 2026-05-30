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
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE utilities MODIFY COLUMN utility_type ENUM('electricity', 'water', 'internet', 'trash', 'parking', 'other') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE utilities MODIFY COLUMN utility_type ENUM('electricity', 'water', 'internet', 'trash', 'parking') NOT NULL");
    }
};
