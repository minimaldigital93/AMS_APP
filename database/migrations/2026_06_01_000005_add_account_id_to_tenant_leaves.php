<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `tenant_leaves` was missed by the original multi-tenancy backfill
 * (2026_06_01_000004), so its rows had no account_id and the BelongsToAccount
 * scope could not isolate them — leaking move-out records across accounts in the
 * notification feed. Add the column and backfill each leave from its apartment's
 * account (every leave has apartment_id, and apartments are already stamped).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_leaves', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('id');
            $table->index('account_id');
        });

        // Derive ownership from the apartment each leave belongs to.
        DB::table('tenant_leaves')
            ->whereNull('account_id')
            ->update([
                'account_id' => DB::raw(
                    '(SELECT apartments.account_id FROM apartments WHERE apartments.id = tenant_leaves.apartment_id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('tenant_leaves', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
