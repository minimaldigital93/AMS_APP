<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A move-out settles EVERYTHING the tenant owes, or says in writing what it
 * forgave.
 *
 * arrears_rent        — unpaid rent from months before the leave month, now
 *                       collected in the settlement (the final month is still
 *                       pro_rata_rent).
 * written_off_amount  — owed money the operator chose not to collect.
 * written_off_items   — what exactly was forgiven (rent months / charge ids +
 *                       amounts), so the record survives the rows it names.
 * write_off_reason    — required whenever written_off_amount > 0.
 *
 * Defaults of 0 / null keep every historical leave row reading as "nothing
 * forgiven", which is what the old flow recorded anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_leaves', function (Blueprint $table) {
            $table->decimal('arrears_rent', 12, 2)->default(0)->after('pro_rata_rent');
            $table->decimal('written_off_amount', 12, 2)->default(0)->after('refund_amount');
            $table->json('written_off_items')->nullable()->after('written_off_amount');
            $table->text('write_off_reason')->nullable()->after('written_off_items');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_leaves', function (Blueprint $table) {
            $table->dropColumn(['arrears_rent', 'written_off_amount', 'written_off_items', 'write_off_reason']);
        });
    }
};
