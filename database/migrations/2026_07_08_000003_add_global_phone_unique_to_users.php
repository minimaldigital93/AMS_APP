<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-audit hardening (2026-07 Phase 2, D2): login is Auth::attempt() by phone
 * with the stock Eloquent provider — a single global lookup. Per-account
 * uniqueness therefore isn't enough: two users with the same phone in
 * different accounts means the second one can never log in. Enforce the login
 * namespace globally (validation was tightened to match; signup keeps its
 * deliberate takeover of reusable failed-signup owner rows, which updates the
 * existing row instead of inserting a duplicate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone', 'users_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
        });
    }
};
