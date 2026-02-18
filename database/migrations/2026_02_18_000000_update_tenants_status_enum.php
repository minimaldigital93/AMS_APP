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
        Schema::table('tenants', function (Blueprint $table) {
            // Change the status column enum to include 'pending' and 'inactive'
            // This allows for better tracking of tenant lifecycle
            $table->enum('status', ['active', 'pending', 'inactive'])->default('active')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Revert back to original enum
            $table->enum('status', ['active', 'moved_out'])->default('active')->change();
        });
    }
};
