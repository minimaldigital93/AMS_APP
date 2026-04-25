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
        DB::statement("ALTER TABLE utilities MODIFY COLUMN utility_type ENUM('electricity', 'water', 'internet', 'trash', 'parking', 'other') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE utilities MODIFY COLUMN utility_type ENUM('electricity', 'water', 'internet', 'trash', 'parking') NOT NULL");
    }
};
