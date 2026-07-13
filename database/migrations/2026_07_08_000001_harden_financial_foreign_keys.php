<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB-audit hardening (2026-07 Phase 2, D3+D5):
 *
 * 1. Financial-history tables (payments, utilities, khqr_payments,
 *    tenant_leaves) switch from ON DELETE CASCADE to RESTRICT. The app only
 *    ever soft-deletes rentals/apartments/tenants, so these cascades could
 *    only fire on a manual/console hard delete — exactly the case where
 *    silently erasing payment history is least acceptable. The account purge
 *    (AccountPurgeService) deletes children explicitly, so RESTRICT never
 *    blocks legitimate flows.
 *
 * 2. Platform payment history must survive customer-account deletion:
 *    khqr_payments gains SET NULL FKs for subscription_id / fiscal_period_id /
 *    user_id so purging an account never deletes subscription-revenue rows.
 *
 * 3. properties.supervisor_id and floors.property_id finally get FKs
 *    (ghost-supervisor / orphan-floor guards).
 *
 * FK surgery is MySQL-only: SQLite (tests) cannot alter constraints, and the
 * protections here are defense-in-depth for production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('activity_logs'); // dead table — superseded by audit_logs

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // 0. Null orphaned pointers left over from the pre-FK era so the
        //    constraint adds below cannot fail on legacy data. For the
        //    SET NULL columns this is exactly what the FK would have done;
        //    for floors.property_id it keeps the row instead of deleting it.
        $nullOrphans = function (string $table, string $column, string $refTable): void {
            DB::table($table)
                ->whereNotNull($column)
                ->whereNotIn($column, DB::table($refTable)->select('id'))
                ->update([$column => null]);
        };
        $nullOrphans('khqr_payments', 'subscription_id', 'subscriptions');
        $nullOrphans('khqr_payments', 'fiscal_period_id', 'fiscal_periods');
        $nullOrphans('khqr_payments', 'user_id', 'users');
        $nullOrphans('properties', 'supervisor_id', 'users');
        $nullOrphans('floors', 'property_id', 'properties');

        // 1. History tables: CASCADE → RESTRICT
        Schema::table('payments', function ($table) {
            $table->dropForeign('payments_rental_id_foreign');
            $table->foreign('rental_id')->references('id')->on('rentals')->restrictOnDelete();
        });
        Schema::table('utilities', function ($table) {
            $table->dropForeign('utilities_rental_id_foreign');
            $table->dropForeign('utilities_tenant_id_foreign');
            $table->foreign('rental_id')->references('id')->on('rentals')->restrictOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
        Schema::table('tenant_leaves', function ($table) {
            $table->dropForeign('tenant_leaves_tenant_id_foreign');
            $table->dropForeign('tenant_leaves_rental_id_foreign');
            $table->dropForeign('tenant_leaves_apartment_id_foreign');
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('rental_id')->references('id')->on('rentals')->restrictOnDelete();
            $table->foreign('apartment_id')->references('id')->on('apartments')->restrictOnDelete();
        });

        // 2. khqr_payments: tenant-payment rows RESTRICT on rental; platform
        //    pointers become SET NULL so subscription history survives purges.
        Schema::table('khqr_payments', function ($table) {
            $table->dropForeign('khqr_payments_rental_id_foreign');
            $table->foreign('rental_id')->references('id')->on('rentals')->restrictOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->foreign('fiscal_period_id')->references('id')->on('fiscal_periods')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // 3. Structural pointers that had no referential integrity at all.
        Schema::table('properties', function ($table) {
            $table->foreign('supervisor_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('floors', function ($table) {
            $table->foreign('property_id')->references('id')->on('properties')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('floors', fn ($table) => $table->dropForeign(['property_id']));
        Schema::table('properties', fn ($table) => $table->dropForeign(['supervisor_id']));
        Schema::table('khqr_payments', function ($table) {
            $table->dropForeign(['rental_id']);
            $table->dropForeign(['subscription_id']);
            $table->dropForeign(['fiscal_period_id']);
            $table->dropForeign(['user_id']);
            $table->foreign('rental_id')->references('id')->on('rentals')->cascadeOnDelete();
        });
        Schema::table('tenant_leaves', function ($table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['rental_id']);
            $table->dropForeign(['apartment_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('rental_id')->references('id')->on('rentals')->cascadeOnDelete();
            $table->foreign('apartment_id')->references('id')->on('apartments')->cascadeOnDelete();
        });
        Schema::table('utilities', function ($table) {
            $table->dropForeign(['rental_id']);
            $table->dropForeign(['tenant_id']);
            $table->foreign('rental_id')->references('id')->on('rentals')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
        Schema::table('payments', function ($table) {
            $table->dropForeign(['rental_id']);
            $table->foreign('rental_id')->references('id')->on('rentals')->cascadeOnDelete();
        });
    }
};
