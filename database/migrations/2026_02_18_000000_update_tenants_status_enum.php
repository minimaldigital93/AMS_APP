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
        // SQLite's column-change shim would re-emit a CHECK constraint with
        // only these three values, which would then block later expansions
        // (`moved_out`). Skip on non-MySQL — the initial migration uses a
        // plain string column there.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['active', 'pending', 'inactive'])->default('active')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['active', 'moved_out'])->default('active')->change();
        });
    }
};
