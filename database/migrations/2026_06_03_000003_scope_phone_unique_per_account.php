<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make phone numbers unique per account instead of globally.
 *
 * Phone was globally unique on both `users` and `tenants`, so once one admin's
 * tenant used a number, no other admin could reuse it — they hit a duplicate-key
 * crash. Scoping uniqueness to account_id lets each admin manage their own
 * tenants/supervisors independently.
 *
 * Account owners (admins) are still kept globally unique at the application layer
 * during registration (SubscriptionController), so login-by-phone stays
 * unambiguous for account owners.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
            $table->unique(['account_id', 'phone'], 'users_account_phone_unique');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique('tenants_phone_unique');
            // Include deleted_at so a soft-deleted tenant doesn't block reusing the
            // same phone within the same account.
            $table->unique(['account_id', 'phone', 'deleted_at'], 'tenants_account_phone_deleted_at_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_account_phone_unique');
            $table->unique('phone', 'users_phone_unique');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique('tenants_account_phone_deleted_at_unique');
            $table->unique('phone', 'tenants_phone_unique');
        });
    }
};
