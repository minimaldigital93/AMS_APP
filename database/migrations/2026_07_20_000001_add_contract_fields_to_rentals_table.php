<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `rentals` row IS the lease. This adds the contract bookkeeping and the
 * lease-term detail the printed rental contract needs.
 *
 * Fields the assignment spec lists that already exist under app names are NOT
 * duplicated: room_id -> apartment_id, lease_start/end_date -> start_date/end_date,
 * deposit_amount -> deposit, monthly_rent -> rent_amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Contract bookkeeping.
            $table->string('contract_number')->nullable()->unique()->after('id');
            $table->string('contract_path')->nullable()->after('deposit');
            $table->timestamp('contract_generated_at')->nullable()->after('contract_path');

            // Lease-term detail shown on the contract. Nullable/0 by default —
            // the assign form does not collect these yet, so they render as
            // fill-in lines on the printed contract until edited.
            $table->decimal('electricity_price', 10, 2)->default(0)->after('rent_amount');
            $table->decimal('water_price', 10, 2)->default(0)->after('electricity_price');
            $table->decimal('parking_fee', 10, 2)->default(0)->after('water_price');
            $table->decimal('internet_fee', 10, 2)->default(0)->after('parking_fee');
            $table->decimal('garbage_fee', 10, 2)->default(0)->after('internet_fee');
            $table->decimal('late_fee', 10, 2)->default(0)->after('garbage_fee');
            $table->unsignedTinyInteger('payment_due_day')->nullable()->after('late_fee');

            // Who created the lease (audit trail on the contract footer).
            $table->foreignId('created_by')->nullable()->after('payment_due_day')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['contract_number']);
            $table->dropColumn([
                'contract_number',
                'contract_path',
                'contract_generated_at',
                'electricity_price',
                'water_price',
                'parking_fee',
                'internet_fee',
                'garbage_fee',
                'late_fee',
                'payment_due_day',
            ]);
        });
    }
};
