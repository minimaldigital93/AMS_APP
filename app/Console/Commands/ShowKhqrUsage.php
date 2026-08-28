<?php

namespace App\Console\Commands;

use App\Services\RevenueExpense\KhqrPaymentService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Report how much of the metered Bakong allowance this app has spent.
 *
 * KHQRPay's upstream token is rated per calendar day. A successful verify
 * writes no log line (only refusals are logged, and those are latched to one
 * line per transaction), so until KhqrPaymentService started counting them the
 * spend was invisible from this side entirely — the provider's dashboard was
 * the only record. This is the local answer.
 *
 * Counts live provider calls only: verifies served from the cooldown cache,
 * demo-mode confirmations and manual-channel rows never reach the gateway and
 * never appear here.
 */
class ShowKhqrUsage extends Command
{
    protected $signature = 'khqr:usage {--days=7 : How many days back to report}';

    protected $description = 'Show live KHQRPay/Bakong provider calls spent per day';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $rows = [];
        for ($i = 0; $i < $days; $i++) {
            $day = Carbon::now()->subDays($i);
            $rows[] = [
                $day->format('Y-m-d'),
                KhqrPaymentService::providerCallsOn(null, $day),
                KhqrPaymentService::providerCallsOn('platform', $day),
                KhqrPaymentService::providerCallsOn('merchant', $day),
            ];
        }

        $this->table(['Date', 'Total', 'Platform', 'Merchant'], $rows);

        // Counters live in the cache, so they are as durable as the cache store
        // and no further back than their own TTL. Say so rather than let a run of
        // zeroes read as "nothing was spent".
        $this->line('');
        $this->comment('Counters are cache-backed (retained ~3 days). Zeroes older than that, '
            .'or after a cache flush, mean "not recorded" — not "no calls".');

        if (config('cache.default') === 'array') {
            $this->warn('CACHE_STORE is "array": counters do not survive the request. Use database/redis.');
        }

        return self::SUCCESS;
    }
}
