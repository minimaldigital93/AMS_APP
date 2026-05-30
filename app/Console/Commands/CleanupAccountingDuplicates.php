<?php

namespace App\Console\Commands;

use App\Models\Accounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cleans up two classes of bad rows in the Accounts ledger created by the
 * pre-fix double-counting bugs:
 *
 *   1. Tenant-charge accrual rows — created at charge time AND again at payment
 *      time, doubling utility/other income.
 *      Marker: reference_number LIKE 'tenant_charge:%' AND payment_id IS NULL
 *
 *   2. Utility expense offset rows — phantom expenses written to mirror tenant
 *      utility income 1:1, suppressing real net profit.
 *      Marker: note LIKE 'Utility expense offset%'
 */
class CleanupAccountingDuplicates extends Command
{
    protected $signature = 'accounting:cleanup-duplicates {--force : Actually delete rows (default is dry-run)}';

    protected $description = 'Remove duplicate tenant-charge accrual rows and phantom utility-expense-offset rows from the Accounts ledger.';

    public function handle(): int
    {
        $isDryRun = ! $this->option('force');

        $accrualQuery = Accounts::where('reference_number', 'LIKE', 'tenant_charge:%')
            ->whereNull('payment_id')
            ->where('account_type', Accounts::TYPE_INCOME);

        $offsetQuery = Accounts::where('note', 'LIKE', 'Utility expense offset%')
            ->where('account_type', Accounts::TYPE_EXPENSE)
            ->where('category', Accounts::CAT_UTILITIES_EXPENSE);

        $accrualCount = (clone $accrualQuery)->count();
        $accrualSum = (clone $accrualQuery)->sum('amount');
        $offsetCount = (clone $offsetQuery)->count();
        $offsetSum = (clone $offsetQuery)->sum('amount');

        $this->info($isDryRun ? 'DRY-RUN — no rows will be deleted. Re-run with --force to apply.' : 'APPLYING — deleting matched rows.');
        $this->newLine();

        $this->table(
            ['Class', 'Rows', 'Total $'],
            [
                ['Tenant-charge accrual (income)', $accrualCount, number_format($accrualSum, 2)],
                ['Utility expense offset',         $offsetCount,  number_format($offsetSum, 2)],
                ['TOTAL',                          $accrualCount + $offsetCount, number_format($accrualSum + $offsetSum, 2)],
            ]
        );

        if ($accrualCount === 0 && $offsetCount === 0) {
            $this->info('Nothing to clean up.');

            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->newLine();
            $this->line('Re-run with <info>--force</info> to delete these rows.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($accrualQuery, $offsetQuery, $accrualCount, $offsetCount) {
            $accrualDeleted = $accrualQuery->delete();
            $offsetDeleted = $offsetQuery->delete();

            $this->info("Deleted {$accrualDeleted} accrual rows and {$offsetDeleted} offset rows.");

            if ($accrualDeleted !== $accrualCount || $offsetDeleted !== $offsetCount) {
                $this->warn('Deleted counts differ from preview counts — rows may have been written between the count and the delete.');
            }
        });

        return self::SUCCESS;
    }
}
