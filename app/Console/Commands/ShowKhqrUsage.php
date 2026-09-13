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
 *
 * The two checkout preflight probes ARE counted (since 2026-08). They were not
 * until then, so this table under-reported every checkout attempt by two and
 * the daily ceiling could be sailed past by the very probes meant to protect
 * it. Old advice to "add 2 per checkout attempt" when reconciling against the
 * provider's dashboard no longer applies.
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

        // WHAT spent today's allowance — the first question of any quota
        // investigation, answered without reading the log. Counted per reason
        // by KhqrProviderClient at the moment a request is let through.
        $platform = \App\Services\Payment\KhqrProviderClient::callsByReasonOn('platform');
        $merchant = \App\Services\Payment\KhqrProviderClient::callsByReasonOn('merchant');
        $this->line('');
        $this->line('Today by reason:');
        $this->table(
            ['Reason', 'Platform', 'Merchant'],
            array_map(fn (string $reason) => [$reason, $platform[$reason], $merchant[$reason]], array_keys($platform)),
        );

        // The number that decides whether any of the above can grow. With the
        // feature off, every counter here is frozen by construction — say so
        // rather than let a row of zeroes read as "a quiet day".
        $this->line('');
        if (\App\Services\Payment\KhqrProviderClient::providerCallsPermitted()) {
            $this->line('KHQR feature: <info>ENABLED</info> (KHQR_PAY_ENABLED) — provider requests are possible.');
        } elseif (\App\Services\Payment\KhqrProviderClient::featureEnabled()) {
            // Demo runs the flows but can never transmit — "possible" would be false.
            $this->line('KHQR feature: <comment>DEMO</comment> (KHQRPAY_DEMO) — simulated locally, no provider request can be made.');
        } else {
            $this->line('KHQR feature: <comment>DISABLED</comment> (KHQR_PAY_ENABLED) — no provider request can be made at all.');
        }

        // The ceiling that actually stops the calls, printed beside the spend —
        // the numbers above only mean something against it.
        $budget = (int) config('services.khqrpay.daily_budget', 0);
        $this->line('');
        if ($budget > 0) {
            $this->line("Daily budget per target: {$budget} live calls (KHQRPAY_DAILY_BUDGET).");
            foreach (['platform', 'merchant'] as $target) {
                $spent = KhqrPaymentService::providerCallsOn($target);
                if ($spent >= $budget) {
                    $this->warn("  {$target}: {$spent}/{$budget} — EXHAUSTED, the gateway is not being called.");
                } else {
                    $this->line("  {$target}: {$spent}/{$budget}");
                }
            }
        } else {
            $this->warn('No daily budget set (KHQRPAY_DAILY_BUDGET=0) — nothing stops this app from '
                .'spending a metered token all day on requests that can only fail.');
        }

        // Counters live in the cache, so they are as durable as the cache store
        // and no further back than their own TTL. Say so rather than let a run of
        // zeroes read as "nothing was spent".
        $this->line('');
        $this->comment('Counters are cache-backed (retained ~3 days). Zeroes older than that, '
            .'or after a cache flush, mean "not recorded" — not "no calls".');
        $this->comment('`cache:clear` / `optimize:clear` flush them — and with them today\'s budget, '
            .'cooldowns and backoffs. Re-cache config with `config:clear && config:cache` instead.');

        if (config('cache.default') === 'array') {
            $this->warn('CACHE_STORE is "array": counters do not survive the request. Use database/redis.');
        }

        return self::SUCCESS;
    }
}
