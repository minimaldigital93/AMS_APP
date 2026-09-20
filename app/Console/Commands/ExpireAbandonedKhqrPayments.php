<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\KhqrPayment;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Close out the API-channel KHQR rows khqr.cc left behind, WITHOUT asking any
 * gateway about them.
 *
 * `channel = 'api'` covers both things that ever used it: the dynamic QRs
 * minted at khqr.cc before the provider was retired in 2026-09, and today's
 * direct-Bakong subscription QRs. (Tenant rent is 'manual' and is closed by the
 * landlord's own reject button, so it is never in scope here.)
 *
 * It exists because no automatic path will close these. A row is only expired
 * automatically on a CONCLUSIVE UNPAID, and a gateway that refuses — a rejected
 * token, a spent allowance, a 5xx, or simply nothing left to ask because the
 * session was never rendered — never gives one. So a refused row stays open,
 * and once it falls out of the reconcile window nothing looks at it again. That
 * is how two rows reached seventy-three days in qr_generated. With khqr.cc gone
 * its legacy rows have no gateway at all, and `bakong:reconcile` ships OFF, so
 * in practice this is the only thing that closes either kind.
 *
 * Automating the close would mean writing a payment out of the books on the
 * word of a gateway that never answered. So it stays a human override: an
 * operator states, out of band, that a QR from days ago is not going to be
 * paid. Same shape as SuperAdmin\AccountsController::changePlan().
 *
 * It spends NO Bakong quota, and cannot: there is no client left in this app
 * that could reach khqr.cc even if it wanted to.
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
