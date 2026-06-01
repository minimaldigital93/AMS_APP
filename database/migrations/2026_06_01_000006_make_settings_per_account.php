<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Settings were global (unique on `key` alone), so every customer account shared
 * one row per key — one admin changing their business name, currency, or language
 * changed it for everyone (and on all PDF receipts). Make settings per-account:
 * add account_id, key uniqueness on (account_id, key), and assign existing rows
 * to the first admin account so the current install keeps its values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('id');
            $table->dropUnique(['key']);
            $table->unique(['account_id', 'key']);
            $table->index('account_id');
        });

        // Existing global settings belong to the original (first) admin account.
        $adminId = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'admin')
            ->value('model_has_roles.model_id');

        if ($adminId) {
            DB::table('settings')->whereNull('account_id')->update(['account_id' => $adminId]);
        }
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropUnique(['account_id', 'key']);
            $table->dropColumn('account_id');
            $table->unique('key');
        });
    }
};
