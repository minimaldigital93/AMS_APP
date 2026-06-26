<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers each user's last active property so the global property selector
 * restores their selection after a fresh login (the session alone is cleared on
 * logout). Nullable + indexed, no FK constraint — matching account_id /
 * floors.property_id conventions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('last_property_id')->nullable()->after('account_id');
            $table->index('last_property_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_property_id']);
            $table->dropColumn('last_property_id');
        });
    }
};
