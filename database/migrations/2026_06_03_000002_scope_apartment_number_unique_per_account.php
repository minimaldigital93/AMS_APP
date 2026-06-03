<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make apartment_number unique per account instead of globally.
 *
 * The previous constraint (apartment_number, deleted_at) was global, so once one
 * admin created unit "101" no other admin/account could reuse that number — they
 * saw a misleading "already been taken" error. Including account_id makes each
 * account's unit numbers independent while still allowing soft-deleted rows to
 * coexist with active ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropUnique('apartments_apartment_number_deleted_at_unique');

            $table->unique(
                ['account_id', 'apartment_number', 'deleted_at'],
                'apartments_account_apartment_number_deleted_at_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropUnique('apartments_account_apartment_number_deleted_at_unique');

            $table->unique(
                ['apartment_number', 'deleted_at'],
                'apartments_apartment_number_deleted_at_unique'
            );
        });
    }
};
