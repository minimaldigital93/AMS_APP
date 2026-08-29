<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\KhqrPayment;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Close out API-channel KHQR rows that have been sitting open long after their
 * QR died, WITHOUT asking the gateway about them.
 *
 * khqr:reconcile deliberately cannot do this. A row is only expired there on a
 * conclusive unpaid, and a gateway that refuses every request (no Bakong token,
 * spent allowance, 502) never gives one — so a refused row stays open, and once
 * it falls out of the reconcile window nothing looks at it again. That is how
 * two rows reached seventy-three days in qr_generated.
 *
 * Making the automatic path close them instead would mean writing a payment out
 * of the books on the word of a gateway that declined to answer, which is the
 * one thing the reconcile command is built to never do. So this is the manual
 * counterpart: an operator states, out of band, that a QR from days ago is not
 * going to be paid. Same shape as SuperAdmin\AccountsController::changePlan() —
 * the sanctioned human override for something automation must not decide.
 *
 * It spends NO Bakong quota. It is safe to run when the allowance is gone,
 * which is exactly when the backlog it clears tends to have built up.
 */
class ExpireAbandonedKhqrPayments extends Command
{
    protected $signature = 'khqr:expire-abandoned
        {--hours=24 : Only rows whose QR died at least this many hours ago}
        {--dry-run : List what would be expired and change nothing}
        {--force : Skip the confirmation prompt (for cron/deploy use)}';

    protected $description = 'Expire long-abandoned open KHQR rows without calling the gateway';

    public function handle(AuditLogger $audit): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        // Legacy rows minted before expires_at existed are judged on created_at,
        // the same fallback isStale() uses in khqr:reconcile.
        $rows = KhqrPayment::query()
            ->whereIn('status', PaymentStatus::openValues())
            ->where('channel', 'api')
            ->where(fn ($q) => $q
                ->where('expires_at', '<', $cutoff)
                ->orWhere(fn ($legacy) => $legacy->whereNull('expires_at')->where('created_at', '<', $cutoff)))
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info("No abandoned API-channel rows older than {$hours}h. Nothing to do.");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Transaction', 'Status', 'Target', 'Amount', 'Created', 'QR died'],
            $rows->map(fn (KhqrPayment $r) => [
                $r->id,
                $r->transaction_id,
                $r->status,
                $r->settlement_target ?? '—',
                $r->amount.' '.$r->currency,
                $r->created_at?->toDateTimeString() ?? '—',
                $r->expires_at?->toDateTimeString() ?? '(none — legacy row)',
            ])->all(),
        );

        if ($this->option('dry-run')) {
            $this->comment($rows->count().' row(s) would be expired. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        // Say the quiet part out loud before doing it: if any of these WERE
        // paid, expiring them is what writes that money out of the books, and
        // nothing here has asked the gateway. That is the trade the operator is
        // making, so it should not be made by pressing enter on a blank prompt.
        $this->warn('These rows will be marked expired WITHOUT verifying them against the gateway.');
        $this->warn('If any of them was actually paid, that payment will not be recovered by this command.');

        if (! $this->option('force') && ! $this->confirm('Expire '.$rows->count().' row(s)?', false)) {
            $this->info('Aborted. Nothing changed.');

            return self::SUCCESS;
        }

        $expired = 0;
        foreach ($rows as $row) {
            try {
                DB::transaction(function () use ($row, &$expired, $audit) {
                    $locked = KhqrPayment::whereKey($row->getKey())->lockForUpdate()->first();
                    if (! $locked || ! $locked->isOpen()) {
                        return;
                    }

                    $locked->transitionTo(PaymentStatus::Expired);
                    $locked->save();
                    $expired++;

                    $audit->record('khqr_payment.expired_abandoned', $locked, [
                        'transaction_id' => $locked->transaction_id,
                        'settlement_target' => $locked->settlement_target,
                        'amount' => $locked->amount,
                        'created_at' => $locked->created_at?->toIso8601String(),
                        'reason' => 'operator cleanup; gateway not consulted',
                    ]);
                });
            } catch (\Throwable $e) {
                $this->error("Row {$row->id} ({$row->transaction_id}): {$e->getMessage()}");
                Log::warning('khqr:expire-abandoned failed for row', [
                    'tran' => $row->transaction_id,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        Log::warning('khqr:expire-abandoned closed abandoned rows', ['count' => $expired, 'older_than_hours' => $hours]);
        $this->info("Expired: {$expired}");

        return self::SUCCESS;
    }
}
