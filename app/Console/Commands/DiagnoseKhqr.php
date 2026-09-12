<?php

namespace App\Console\Commands;

use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Console\Command;

/**
 * The terminal copy of the "payment could not be started" popup.
 *
 * Both read the same KhqrPaymentService::platformDiagnostics(), so a support
 * conversation and an SSH session cannot disagree about what the gateway said.
 * This one exists because the failure it diagnoses can lock the operator out of
 * the very page the popup lives on: no active subscription and a gateway that
 * won't take payment leaves nowhere in the UI to stand.
 *
 * OFFLINE BY DEFAULT since the KHQR zero-request audit. A live run costs two
 * requests against a metered Bakong token — the handoff probe opens a throwaway
 * checkout session at khqr.cc — so "is the token there?" must never be answered
 * by spending one of its requests. The config half of the report (feature
 * switch, credentials, today's spend, webhook URL) is free and is where most
 * failures are already visible; --live adds the two probes and is the only way
 * to make this command contact anyone.
 */
class DiagnoseKhqr extends Command
{
    protected $signature = 'khqr:diagnose
        {--live : Also run the two live gateway probes. Costs 2 requests against the daily Bakong allowance.}';

    protected $description = 'Check whether the platform KHQR profile can take a subscription payment (offline unless --live)';

    public function handle(KhqrPaymentService $khqr): int
    {
        $live = (bool) $this->option('live');

        if ($live && ! KhqrPaymentService::featureEnabled()) {
            // Say it before the report rather than leaving the reader to spot
            // two 'info' rows: they asked for a live run and are not getting one.
            $this->warn('KHQR is disabled (KHQR_PAY_ENABLED) — --live was ignored and no provider requests were made.');
            $live = false;
        }

        $report = $khqr->platformDiagnostics($live);

        $icons = ['ok' => '<info>✔</info>', 'fail' => '<fg=red>✘</>', 'warn' => '<comment>!</comment>', 'info' => 'ⓘ'];

        $this->line('');
        foreach ($report['checks'] as $check) {
            $this->line(($icons[$check['state']] ?? '?').'  '.$check['label']);
            if (filled($check['detail'])) {
                $this->line('   <fg=gray>'.$check['detail'].'</>');
            }
            // The fix for this specific check, in the same place the popup puts
            // it — an SSH session and a support call must not end up reading
            // different advice off the same report.
            if (filled($check['remedy'] ?? null)) {
                $this->line('   → '.$check['remedy']);
            }
            // The popup offers this on a copy button; here it just has to be
            // selectable, so print it on its own line rather than wrapped in
            // the report's punctuation. Skipped when it only repeats the detail
            // (the webhook URL is both) — in a terminal the detail is already
            // selectable, and printing the same string twice reads as two
            // different values worth comparing.
            if (filled($check['copy'] ?? null) && $check['copy'] !== ($check['detail'] ?? null)) {
                $this->line('');
                $this->line('   <fg=cyan>'.$check['copy'].'</>');
            }
            $this->line('');
        }

        // What the preflight recorded last time a customer was actually turned
        // away. The live run above can come back green a minute later, so
        // without this the report can contradict the person reading it.
        if ($fault = $khqr->lastPlatformCheckoutFault()) {
            $this->line('');
            $this->warn('Last refusal recorded by the checkout preflight:');
            $this->line('   <fg=gray>'.implode(' · ', array_filter([
                $fault['probe'] ?? null,
                isset($fault['status']) ? 'HTTP '.$fault['status'] : null,
                $fault['message'] ?? null,
                $fault['at'] ?? null,
            ])).'</>');
        }

        $this->line('');

        if (! ($report['live'] ?? false)) {
            $this->comment('Offline report — no request was sent to khqr.cc or Bakong.');
        }

        if ($report['healthy']) {
            $this->info(match (true) {
                // In demo mode --live is also a no-op, so offering it would send
                // the reader looking for an answer this command cannot give.
                (bool) config('services.khqrpay.demo') => 'Demo mode — the gateway is never contacted. Set KHQRPAY_DEMO=false to check the real profile.',
                (bool) ($report['live'] ?? false) => 'Platform KHQR looks able to take payments.',
                default => 'Platform KHQR configuration looks complete. Add --live to ask the gateway whether it will actually transact.',
            });

            return self::SUCCESS;
        }

        $this->error('Platform KHQR cannot take payments — subscribe/renew will be refused before the redirect.');

        return self::FAILURE;
    }
}
