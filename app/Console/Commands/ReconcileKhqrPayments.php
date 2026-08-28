<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\KhqrPayment;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for API-channel KHQR payments whose webhook never arrived and
 * whose checkout modal was closed before the poll confirmed: re-verify each
 * recent pending row against KHQRPay and finalize the paid ones. Rows still
 * pending after the cutoff are marked expired (the QR is long dead).
 *
 * Manual-channel rows are untouched — they wait for the landlord on the
 * pending-confirmations page. Scheduled every five minutes — routes/console.php.
 *
 * A row is only expired on a CONCLUSIVE unpaid. When the gateway gives no
 * verdict (allowance spent, 5xx, timeout) the row is left open for the next
 * run: expiry is terminal, and expiring a QR the payer may already have paid —
 * on the word of a gateway that declined to answer — writes their money out of
 * the books with no way back.
 */
class ReconcileKhqrPayments extends Command
{
    protected $signature = 'khqr:reconcile {--expire-after=30 : Minutes before an unverifiable pending QR is marked expired}';

    protected $description = 'Verify and finalize pending API-channel KHQR payments; expire stale ones';

    public function handle(KhqrPaymentService $khqr): int
    {
        $expireAfter = (int) $this->option('expire-after');
        $finalized = 0;
        $expired = 0;
        $refused = 0;

        KhqrPayment::whereIn('status', PaymentStatus::openValues())
            ->where('channel', 'api')
            ->where('created_at', '>', now()->subDay())
            ->chunkById(100, function ($rows) use ($khqr, $expireAfter, &$finalized, &$expired, &$refused) {
                foreach ($rows as $row) {
                    try {
                        $outcome = $khqr->verifyOutcome($row);

                        if ($outcome === KhqrPaymentService::VERIFY_PAID) {
                            $khqr->finalize($row);
                            $finalized++;

                            continue;
                        }

                        // No verdict — the allowance is spent, or the gateway is
                        // refusing. This branch is the one that used to lose
                        // money: a refusal read as "unpaid", and a stale row was
                        // then expired on it. Expiry is terminal, so the next run
                        // would never look at that QR again even after the
                        // gateway recovered. Leave it open and try again.
                        if ($outcome === KhqrPaymentService::VERIFY_REFUSED) {
                            $refused++;

                            continue;
                        }

                        if ($this->isStale($row, $expireAfter)) {
                            $row->transitionTo(PaymentStatus::Expired);
                            $row->save();
                            $expired++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('khqr:reconcile failed for row', ['tran' => $row->transaction_id, 'msg' => $e->getMessage()]);
                    }
                }
            });

        $this->info("Finalized: {$finalized}, expired: {$expired}, unverifiable: {$refused}");

        if ($refused > 0) {
            $this->warn("{$refused} row(s) left open — the gateway returned no verdict. Check `php artisan khqr:usage`.");
        }

        return self::SUCCESS;
    }

    /**
     * A QR is stale once its own expires_at has passed; legacy rows minted before
     * expires_at existed fall back to the created_at + expire-after cutoff.
     */
    private function isStale(KhqrPayment $row, int $expireAfter): bool
    {
        if ($row->expires_at !== null) {
            return $row->expires_at->isPast();
        }

        return $row->created_at->lt(now()->subMinutes($expireAfter));
    }
}
