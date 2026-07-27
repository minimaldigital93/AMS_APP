<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maintenance mode is a flag, NOT a third `status` value — the enum was
     * deliberately narrowed back to available/occupied (see
     * 2026_06_08_141001_remove_maintenance_from_apartments_status). Keeping it
     * separate means `status` still answers "is someone living here?" while
     * `under_maintenance` answers "is this unit part of the rentable stock?",
     * so occupancy/break-even denominators can drop the unit without any
     * status-based query or badge changing meaning.
     */
    public function up(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->boolean('under_maintenance')->default(false)->after('status');
            // Every rentable-stock query filters on this column.
            $table->index('under_maintenance');
        });
    }

    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropIndex(['under_maintenance']);
            $table->dropColumn('under_maintenance');
        });
    }
};
