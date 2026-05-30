<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        // Expand the category enum to include all categories used in the application
        DB::statement("ALTER TABLE `accounts` MODIFY COLUMN `category` ENUM(
            'rent_income', 'utility_income', 'deposit_income', 'other_income',
            'maintenance', 'repairs', 'utilities_expense', 'salaries', 'taxes', 'insurance', 'other_expense',
            'property_tax', 'security', 'cleaning', 'landscaping', 'supplies', 'marketing',
            'legal', 'miscellaneous', 'management',
            'business_fixed', 'business_variable'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE `accounts` MODIFY COLUMN `category` ENUM(
            'rent_income', 'utility_income', 'deposit_income', 'other_income',
            'maintenance', 'repairs', 'utilities_expense', 'salaries', 'taxes', 'insurance', 'other_expense'
        ) NOT NULL");
    }
};
