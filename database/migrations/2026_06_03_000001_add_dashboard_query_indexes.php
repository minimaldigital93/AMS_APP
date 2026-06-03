<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the revenue/expense dashboard hot paths.
 *
 * These tables only had indexes on PK / foreign keys / account_id. The
 * dashboard queries also filter on type/status/date columns that were
 * unindexed, which forces full-table scans once a tenant accumulates real
 * volume. Column order matches the query shapes: tenant/owner scope first,
 * then the equality filter, then the range column last.
 *
 * Each index is named and guarded so the migration is idempotent.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private array $indexes = [
        // Accounts::income()/expense()->forUser()->forPeriod()->betweenDates()
        'accounts_dash_type_date_index' => ['accounts', ['account_id', 'account_type', 'transaction_date']],
        // calculateIncome() utility breakdown: paid utilities within a date range
        'utilities_dash_paid_index' => ['utilities', ['account_id', 'paid_status', 'paid_at']],
        // Eager-load constraint: utilities for a rental in a billing month/year
        'utilities_dash_billing_index' => ['utilities', ['rental_id', 'billing_year', 'billing_month']],
        // Eager-load constraint: a rental's paid payments
        'payments_dash_status_index' => ['payments', ['rental_id', 'payment_status']],
        // Active-rental filter (end_date null or in the future)
        'rentals_dash_end_date_index' => ['rentals', ['account_id', 'end_date']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $name => [$tableName, $columns]) {
            if (Schema::hasIndex($tableName, $name)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($columns, $name) {
                $table->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $name => [$tableName, $columns]) {
            if (! Schema::hasIndex($tableName, $name)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($name) {
                $table->dropIndex($name);
            });
        }
    }
};
