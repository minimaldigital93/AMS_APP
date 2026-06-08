<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The 'maintenance' apartment status has been removed from the app. Remap any
     * existing maintenance rows back to 'available' before narrowing the enum, so
     * the new constraint can't be violated by legacy data.
     */
    public function up(): void
    {
        DB::table('apartments')->where('status', 'maintenance')->update(['status' => 'available']);

        Schema::table('apartments', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied'])->default('available')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied', 'maintenance'])->default('available')->change();
        });
    }
};
