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
 * Every run costs live requests against a metered Bakong token — one probe per
 * check, and the handoff probe opens a throwaway checkout session at khqr.cc.
 * Don't loop it.
 */
class DiagnoseKhqr extends Command
{
    protected $signature = 'khqr:diagnose';

    protected $description = 'Check whether the platform KHQR profile can actually take a subscription payment';

    public function handle(KhqrPaymentService $khqr): int
    {
        $report = $khqr->platformDiagnostics();

        $icons = ['ok' => '<info>✔</info>', 'fail' => '<fg=red>✘</>', 'warn' => '<comment>!</comment>', 'info' => 'ⓘ'];

        $this->line('');
        foreach ($report['checks'] as $check) {
            $this->line(($icons[$check['state']] ?? '?').'  '.$check['label']);
            if (filled($check['detail'])) {
                $this->line('   <fg=gray>'.$check['detail'].'</>');
            }
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

        if ($report['healthy']) {
            $this->info('Platform KHQR looks able to take payments.');

            return self::SUCCESS;
        }

        $this->error('Platform KHQR cannot take payments — subscribe/renew will be refused before the redirect.');

        return self::FAILURE;
    }
}
