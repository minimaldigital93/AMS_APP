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
        Schema::create('business_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('fiscal_period_id')->constrained('fiscal_periods')->onDelete('cascade');
            $table->string('expense_name');
            $table->enum('cost_type', ['fixed', 'variable']); // fixed = recurring, variable = one-time/fluctuating
            $table->string('category')->default('general'); // e.g. maintenance, insurance, management, supplies, marketing
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->unsignedTinyInteger('billing_month');
            $table->unsignedSmallInteger('billing_year');
            $table->boolean('is_recurring')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_expenses');
    }
};
