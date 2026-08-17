<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vehicles a tenant keeps on the property.
     *
     * A vehicle with a `monthly_fee` above zero is the tenant's parking charge:
     * MonthlyBillingService sums the tenant's vehicles into the one
     * (rental, 'parking', month, year) Utilities row, so the money then flows
     * through the existing parking lane — bill row, checkout, income ledger,
     * receipt, move-out settlement. A zero fee records the vehicle without
     * billing for it (parking included in the rent).
     */
    public function up(): void
    {
        Schema::create('tenant_vehicles', function (Blueprint $table) {
            $table->id();
            // Plain indexed column, like every other owned table — see
            // 2026_06_01_000004_add_account_id_for_multitenancy.
            $table->unsignedBigInteger('account_id')->nullable();
            $table->index('account_id');
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('vehicle_type', 20); // car | tuktuk | motorbike
            $table->string('plate_number', 30);
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->timestamps();

            // One plate is one vehicle within an account — catches the same
            // motorbike being registered twice under two tenants, which would
            // otherwise bill parking on it twice.
            $table->unique(['account_id', 'plate_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_vehicles');
    }
};
