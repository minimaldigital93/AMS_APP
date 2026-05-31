<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE `payments` MODIFY COLUMN `payment_method` ENUM('cash', 'bank', 'bank_transfer', 'mobile_payment', 'khqr') NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE `payments` MODIFY COLUMN `payment_method` ENUM('cash', 'bank', 'bank_transfer', 'mobile_payment') NOT NULL DEFAULT 'cash'");
    }
};
