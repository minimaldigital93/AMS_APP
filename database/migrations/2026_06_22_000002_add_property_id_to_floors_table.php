<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Floors now belong to a Property. Nullable + indexed (no FK constraint, matching
 * account_id) — backfilled by a later migration for existing accounts; legacy
 * unowned floors (null account_id) keep a null property_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floors', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->nullable()->after('account_id');
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::table('floors', function (Blueprint $table) {
            $table->dropIndex(['property_id']);
            $table->dropColumn('property_id');
        });
    }
};
