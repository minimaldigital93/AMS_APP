<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-account expense category vocabulary for the record-expense page.
     *
     * `key` is the stable identifier written into `business_expenses.category`
     * (and read back by the income statement's category mapping); `name` is the
     * label the owner can rename freely without restating booked history.
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            // Plain indexed column, like every other owned table — see
            // 2026_06_01_000004_add_account_id_for_multitenancy.
            $table->unsignedBigInteger('account_id')->nullable();
            $table->index('account_id');
            $table->string('key');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // One vocabulary per account — the key is what expense rows store.
            $table->unique(['account_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
