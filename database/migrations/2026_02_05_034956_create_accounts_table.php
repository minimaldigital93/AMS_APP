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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_period_id')->constrained('fiscal_periods')->onDelete('cascade');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // On SQLite (tests) ENUMs synthesize CHECK constraints with the
            // values listed here only — later ALTER migrations can't grow the
            // set on SQLite. Use plain strings on non-MySQL so application
            // constants (which are the real source of truth) aren't blocked.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->enum('account_type', ['income', 'expense']);
                $table->enum('category', [
                    'rent_income', 'utility_income', 'deposit_income', 'other_income',
                    'maintenance', 'repairs', 'utilities_expense', 'salaries', 'taxes', 'insurance', 'other_expense'
                ]);
            } else {
                $table->string('account_type');
                $table->string('category');
            }

            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');

            $table->string('reference_number')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
