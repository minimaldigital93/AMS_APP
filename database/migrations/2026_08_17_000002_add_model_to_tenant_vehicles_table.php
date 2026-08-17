<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The vehicle's model ("Honda Dream", "Toyota Camry") — description only.
     *
     * Nullable: the plate is the identity (it carries the per-account unique),
     * the model is what the guard on the gate actually recognises. Nothing
     * about billing reads it.
     */
    public function up(): void
    {
        Schema::table('tenant_vehicles', function (Blueprint $table) {
            $table->string('vehicle_model', 50)->nullable()->after('vehicle_type');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_vehicles', function (Blueprint $table) {
            $table->dropColumn('vehicle_model');
        });
    }
};
