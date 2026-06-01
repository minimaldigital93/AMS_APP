<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenancy backbone: every owned row is stamped with `account_id` — the id
 * of the owning admin user. The BelongsToAccount global scope filters on it.
 *
 * Existing single-business data is backfilled to the first admin account so the
 * app keeps working after the upgrade.
 */
return new class extends Migration
{
    /**
     * Owned tables that get a plain (indexed) account_id column.
     * `users` is handled separately (self-referencing, special backfill).
     */
    private array $tables = [
        'floors', 'apartments', 'rentals', 'payments', 'utilities', 'tenants',
        'apartment_fixed_expenses', 'fiscal_periods', 'accounts', 'balance_sheets',
        'business_expenses', 'monthly_periods',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('id');
            $table->index('account_id');
        });

        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('id');
                $table->index('account_id');
            });
        }

        $this->backfill();
    }

    /**
     * Assign all pre-existing rows to the first admin account.
     */
    private function backfill(): void
    {
        $adminId = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'admin')
            ->value('model_has_roles.model_id');

        // Fresh installs have no users yet — seeders set account_id explicitly.
        if (! $adminId) {
            return;
        }

        // Every existing user belongs to that admin's account; the admin owns itself.
        DB::table('users')->whereNull('account_id')->update(['account_id' => $adminId]);
        DB::table('users')->where('id', $adminId)->update(['account_id' => $adminId]);

        // Ledger-style tables already carry the owner in `user_id` — prefer it so a
        // multi-admin install keeps each admin's books separate; fall back to admin.
        foreach (['fiscal_periods', 'accounts', 'balance_sheets', 'business_expenses', 'monthly_periods'] as $t) {
            DB::table($t)->update(['account_id' => DB::raw('COALESCE(user_id, '.$adminId.')')]);
        }

        // Property tree + tenants have no owner column — all to the admin account.
        foreach (['floors', 'apartments', 'rentals', 'payments', 'utilities', 'tenants', 'apartment_fixed_expenses'] as $t) {
            DB::table($t)->whereNull('account_id')->update(['account_id' => $adminId]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['account_id']);
                $table->dropColumn('account_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
