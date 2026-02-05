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
        Schema::create('utilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('rental_id')->constrained('rentals')->onDelete('cascade');

            $table->enum('utility_type', ['electricity', 'water', 'internet', 'trash']);
            $table->string('meter_number')->nullable();
            $table->decimal('meter_reading_in', 10, 2)->nullable();
            $table->decimal('meter_reading_out', 10, 2)->nullable();
            $table->decimal('charge_amount', 10, 2);
            $table->integer('billing_month');
            $table->integer('billing_year');

            $table->boolean('paid_status')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utilities');
    }
};
