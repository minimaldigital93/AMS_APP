<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give every existing account one default property and re-parent its floors under
 * it, so the new Property → Floor → Room tree is consistent for upgraded installs.
 *
 * Legacy unowned floors (null account_id) are left with a null property_id — they
 * stay globally visible like the rest of the pre-multitenancy fixtures.
 */
return new class extends Migration
{
    public function up(): void
    {
        $accountIds = DB::table('floors')
            ->whereNotNull('account_id')
            ->whereNull('property_id')
            ->distinct()
            ->pluck('account_id');

        foreach ($accountIds as $accountId) {
            $propertyId = DB::table('properties')->insertGetId([
                'account_id' => $accountId,
                'supervisor_id' => null,
                'name' => 'My Property',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('floors')
                ->where('account_id', $accountId)
                ->whereNull('property_id')
                ->update(['property_id' => $propertyId]);
        }
    }

    public function down(): void
    {
        // Detach floors from the backfilled properties, then drop those properties.
        DB::table('floors')->update(['property_id' => null]);
        DB::table('properties')->where('name', 'My Property')->delete();
    }
};
