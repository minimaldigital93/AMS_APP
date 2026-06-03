<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level operating expenses recorded by the superadmin (servers,
 * salaries, marketing, etc.). These are the cost side of the SaaS P&L —
 * subscription payments (khqr_payments) are the revenue side.
 *
 * Intentionally NOT account-scoped: this is the platform operator's own
 * ledger, not a customer account's books.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category')->default('other'); // hosting, salary, marketing, software, other
            $table->string('description');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->date('spent_at');
            $table->boolean('is_recurring')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('spent_at');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_expenses');
    }
};
