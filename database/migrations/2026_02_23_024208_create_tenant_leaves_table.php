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
        Schema::create('tenant_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('rental_id')->constrained('rentals')->onDelete('cascade');
            $table->foreignId('apartment_id')->constrained('apartments')->onDelete('cascade');
            
            // Leave information
            $table->date('leave_date');
            $table->date('original_move_out_date')->nullable();
            $table->integer('stay_days');
            $table->decimal('pro_rata_rent', 10, 2)->default(0);
            
            // Utility readings at checkout
            $table->decimal('electricity_reading', 10, 2)->nullable();
            $table->decimal('electricity_charge', 10, 2)->default(0);
            $table->decimal('water_reading', 10, 2)->nullable();
            $table->decimal('water_charge', 10, 2)->default(0);
            $table->decimal('internet_charge', 10, 2)->default(0);
            $table->decimal('parking_charge', 10, 2)->default(0);
            
            // Summary
            $table->decimal('total_amount_due', 10, 2);
            $table->decimal('deposit_applied', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2);
            $table->decimal('refund_amount', 10, 2)->default(0);
            
            $table->enum('status', ['pending', 'completed', 'archived'])->default('pending');
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_leaves');
    }
};
