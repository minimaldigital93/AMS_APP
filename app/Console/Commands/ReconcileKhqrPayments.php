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

        KhqrPayment::whereIn('status', PaymentStatus::openValues())
            ->where('channel', 'api')
            ->where('created_at', '>', now()->subDay())
            ->chunkById(100, function ($rows) use ($khqr, $expireAfter, &$finalized, &$expired) {
                foreach ($rows as $row) {
                    try {
                        if ($khqr->verify($row)) {
                            $khqr->finalize($row);
                            $finalized++;
                        } elseif ($this->isStale($row, $expireAfter)) {
                            $row->transitionTo(PaymentStatus::Expired);
                            $row->save();
                            $expired++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('khqr:reconcile failed for row', ['tran' => $row->transaction_id, 'msg' => $e->getMessage()]);
                    }
                }
            });

        $this->info("Finalized: {$finalized}, expired: {$expired}");

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
