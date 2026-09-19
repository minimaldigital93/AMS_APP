<?php

namespace App\Console\Commands;

use App\Models\BakongApiCall;
use App\Services\Bakong\BakongProviderClient;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * "Why did AMS_APP use 73 Bakong requests today?"
 *
 * That question is the whole reason bakong_api_calls is a table rather than a
 * cache counter. A running total says the allowance is going; only a per-call
 * ledger says that sixty of them were one abandoned checkout, or that the
 * scheduler is running somewhere nobody remembered.
 *
 * Entirely offline — it reads the ledger and contacts nobody. A report that
 * spent the allowance it was reporting on would be worse than no report,
 * especially since the moment anyone runs this is the moment the allowance is
 * already under pressure.
 */
class ShowBakongUsage extends Command
{
    protected $signature = 'bakong:usage
                            {--days=7 : How many days back to report}
                            {--target=platform : Which settlement target’s allowance}';

    protected $description = 'Show Bakong Open API requests spent per day, and why';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $target = (string) $this->option('target');
        $limit = (int) config('bakong.daily_request_limit', 0);

        $this->newLine();
        $this->line('<options=bold>Bakong Open API usage</> — target: '.$target);
        $this->line($limit > 0
            ? 'Internal daily ceiling: '.$limit.' (Bakong meters ~100/day; the margin is deliberate)'
            : '<fg=yellow>No internal ceiling configured (BAKONG_DAILY_REQUEST_LIMIT=0).</>');
        $this->newLine();

        $rows = [];

        for ($i = 0; $i < $days; $i++) {
            $day = Carbon::now()->subDays($i);
            $spent = BakongApiCall::spentOn($target, $day);
            $byReason = BakongApiCall::spentByReasonOn($target, $day);
            $blocked = BakongApiCall::blockedByReasonOn($target, $day);

            $rows[] = [
                $day->toDateString(),
                $this->spentCell($spent, $limit),
                $this->breakdown($byReason) ?: '—',
                // Refusals are shown beside the spend because they are how the
                // guards report for duty. A large verify_cooldown count is the
                // throttle working; a large daily_budget_exhausted count is the
                // day already lost and every later refusal merely noise.
                $this->breakdown($blocked) ?: '—',
            ];
        }

        $this->table(['Date', 'Spent', 'Requests made (by reason)', 'Refused before sending (by gate)'], $rows);

        $this->newLine();
        $this->line('Feature switch: '.(BakongProviderClient::providerCallsPermitted()
            ? '<fg=green>on</>'
            : '<fg=yellow>off — no requests can leave this server</>'));
        $this->line('This report made no Bakong request.');

        return self::SUCCESS;
    }

    private function spentCell(int $spent, int $limit): string
    {
        if ($limit <= 0) {
            return (string) $spent;
        }

        $colour = match (true) {
            $spent >= $limit => 'red',
            $spent >= (int) ($limit * 0.75) => 'yellow',
            default => 'green',
        };

        return "<fg={$colour}>{$spent} / {$limit}</>";
    }

    /** @param array<string, int> $counts */
    private function breakdown(array $counts): string
    {
        arsort($counts);

        return implode('  ', array_map(
            fn (string $k, int $v) => $k.' '.$v,
            array_keys($counts),
            $counts,
        ));
    }
}
