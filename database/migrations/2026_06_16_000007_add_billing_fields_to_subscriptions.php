<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing bookkeeping on the subscription itself:
 *  - price_paid   snapshot of the amount that last activated it (plan price can
 *                 change later; the historical record must not).
 *  - cancelled_at / cancel_reason  set when the account cancels; access then
 *                 continues until expires_at unless the cancel was immediate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('price_paid', 12, 2)->nullable()->after('plan_id');
            $table->timestamp('cancelled_at')->nullable()->after('expires_at');
            $table->string('cancel_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['price_paid', 'cancelled_at', 'cancel_reason']);
        });
    }
};
