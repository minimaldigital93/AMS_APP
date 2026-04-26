<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `accounts` MODIFY COLUMN `category` ENUM(
            'rent_income', 'utility_income', 'deposit_income', 'late_fee_income', 'other_income',
            'maintenance', 'repairs', 'utilities_expense', 'salaries', 'taxes', 'insurance', 'other_expense',
            'property_tax', 'security', 'cleaning', 'landscaping', 'supplies', 'marketing',
            'legal', 'miscellaneous', 'management',
            'business_fixed', 'business_variable',
            'deposit_expense'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `accounts` MODIFY COLUMN `category` ENUM(
            'rent_income', 'utility_income', 'deposit_income', 'late_fee_income', 'other_income',
            'maintenance', 'repairs', 'utilities_expense', 'salaries', 'taxes', 'insurance', 'other_expense',
            'property_tax', 'security', 'cleaning', 'landscaping', 'supplies', 'marketing',
            'legal', 'miscellaneous', 'management',
            'business_fixed', 'business_variable'
        ) NOT NULL");
    }
};
