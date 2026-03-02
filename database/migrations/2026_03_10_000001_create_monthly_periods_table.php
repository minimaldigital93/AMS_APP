<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_period_id')->constrained('fiscal_periods')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "March 2026"
            $table->integer('month_number'); // 1-12
            $table->integer('year');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->default(0);
            $table->decimal('total_income', 15, 2)->default(0);
            $table->decimal('total_expenses', 15, 2)->default(0);
            $table->decimal('net_income', 15, 2)->default(0);
            $table->enum('status', ['open', 'closed', 'locked'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_period_id', 'month_number', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_periods');
    }
};
