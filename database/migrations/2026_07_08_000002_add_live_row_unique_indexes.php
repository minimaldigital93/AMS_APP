<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DB-audit hardening (2026-07 Phase 2, D4): the soft-delete unique keys
 * (…, deleted_at) do NOT enforce anything for live rows in MySQL — a NULL in
 * any unique-key column exempts the row. Only request validation stood between
 * us and duplicate room numbers / tenant phones (racy on double-submit).
 *
 * MySQL 8 functional key parts close the hole: IFNULL(deleted_at, epoch)
 * makes live rows collide. MySQL-only — SQLite has the same NULL semantics but
 * is only used for tests, where validation is what's under test.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE apartments ADD UNIQUE KEY apartments_live_room_unique (floor_id, apartment_number, (IFNULL(deleted_at, '1970-01-01 00:00:00')))");
        DB::statement('ALTER TABLE apartments DROP KEY apartments_floor_apartment_number_deleted_at_unique');

        DB::statement("ALTER TABLE tenants ADD UNIQUE KEY tenants_live_phone_unique ((IFNULL(account_id, 0)), phone, (IFNULL(deleted_at, '1970-01-01 00:00:00')))");
        DB::statement('ALTER TABLE tenants DROP KEY tenants_account_phone_deleted_at_unique');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE tenants ADD UNIQUE KEY tenants_account_phone_deleted_at_unique (account_id, phone, deleted_at)');
        DB::statement('ALTER TABLE tenants DROP KEY tenants_live_phone_unique');

        DB::statement('ALTER TABLE apartments ADD UNIQUE KEY apartments_floor_apartment_number_deleted_at_unique (floor_id, apartment_number, deleted_at)');
        DB::statement('ALTER TABLE apartments DROP KEY apartments_live_room_unique');
    }
};
