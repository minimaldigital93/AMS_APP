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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();

            // See accounts migration for the rationale: MySQL keeps the
            // strict ENUM; SQLite (tests) uses plain strings so later
            // expand-ENUM migrations don't leave it stuck.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->enum('payment_method', ['cash', 'bank'])->default('cash');
                $table->enum('payment_status', ['pending', 'paid', 'overdue'])->default('pending');
                $table->enum('payment_type', ['rent', 'utilities', 'deposit', 'other'])->default('rent');
            } else {
                $table->string('payment_method')->default('cash');
                $table->string('payment_status')->default('pending');
                $table->string('payment_type')->default('rent');
            }

            $table->string('transaction_reference')->nullable();
            $table->decimal('late_fee', 10, 2)->default(0);
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
