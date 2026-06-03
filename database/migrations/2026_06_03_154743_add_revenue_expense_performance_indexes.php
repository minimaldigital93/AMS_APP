<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the revenue/expense dashboard hot paths.
 *
 * The dashboard (admin + supervisor) filters these tables on every load and
 * none of the multi-column filters were indexed — only the single-column
 * foreign keys were. These cover the exact predicates the controllers and
 * RevenueExpense services issue (Accounts ledger reads, per-rental utility
 * and payment lookups, and active-rental window checks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // forUser()->forPeriod()->income()/expense() ordered by transaction_date
            $table->index(['user_id', 'fiscal_period_id', 'account_type', 'transaction_date'], 'accounts_user_period_type_date_idx');
            // loadOtherTenantChargesByRental(): reference_number LIKE 'tenant_charge:%'
            $table->index('reference_number', 'accounts_reference_number_idx');
        });

        Schema::table('utilities', function (Blueprint $table) {
            // eager-loaded per rental, filtered by billing month/year
            $table->index(['rental_id', 'billing_month', 'billing_year'], 'utilities_rental_billing_idx');
            // income breakdown + getUtilityAnalysis() filter on paid_at / billing window
            $table->index(['billing_month', 'billing_year'], 'utilities_billing_idx');
            $table->index('paid_at', 'utilities_paid_at_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            // eager-loaded per rental, filtered by payment_status + paid_at
            $table->index(['rental_id', 'payment_status', 'paid_at'], 'payments_rental_status_paid_idx');
        });

        Schema::table('rentals', function (Blueprint $table) {
            // active-rental window checks: apartment_id + start_date (+ end_date null/range)
            $table->index(['apartment_id', 'start_date', 'end_date'], 'rentals_apartment_window_idx');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('accounts_user_period_type_date_idx');
            $table->dropIndex('accounts_reference_number_idx');
        });

        Schema::table('utilities', function (Blueprint $table) {
            $table->dropIndex('utilities_rental_billing_idx');
            $table->dropIndex('utilities_billing_idx');
            $table->dropIndex('utilities_paid_at_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_rental_status_paid_idx');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropIndex('rentals_apartment_window_idx');
        });
    }
};
