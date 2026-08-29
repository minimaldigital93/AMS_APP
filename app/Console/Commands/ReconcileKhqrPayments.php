<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\KhqrPayment;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * THE WINDOW IS THE QUOTA BOUND — see reconcileWindow().
 */
class ReconcileKhqrPayments extends Command
{
    protected $signature = 'khqr:reconcile
        {--expire-after=30 : Minutes before an unverifiable pending QR with no expires_at is marked expired}
        {--grace= : Minutes past a QR\'s expiry to keep re-verifying it (default: services.khqrpay.reconcile_grace)}';

    protected $description = 'Verify and finalize pending API-channel KHQR payments; expire stale ones';

    public function handle(KhqrPaymentService $khqr): int
    {
        $expireAfter = (int) $this->option('expire-after');
        $grace = $this->option('grace') !== null
            ? (int) $this->option('grace')
            : (int) config('services.khqrpay.reconcile_grace', 60);

        $finalized = 0;
        $expired = 0;
        $refused = 0;

        $this->reconcileWindow($expireAfter, $grace)
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
                        //
                        // It is also the branch that used to burn the day's
                        // quota: a row that can never be closed stayed in scope
                        // for a full day and was re-asked every five minutes.
                        // The window now bounds how long "try again" lasts.
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

        $this->reportAbandoned($expireAfter, $grace);

        return self::SUCCESS;
    }

    /**
     * Which rows this run is allowed to spend a live Bakong request on.
     *
     * Every row matched here costs one metered call per run, so this query IS
     * the quota bound, and it used to have none worth the name: any open
     * api-channel row created in the last 24 hours qualified. With a 10-minute
     * QR that is 288 live calls for a single abandoned checkout — against a
     * token allowed roughly 100 a day — every one of them asking about a QR
     * that stopped being payable within the first ten minutes. And because a
     * gateway with no Bakong token answers every one of them with a refusal,
     * and a refusal (correctly) never closes the row, nothing ever took the row
     * back out of scope. The allowance was gone before anyone was awake.
     *
     * The net still has to outlive the QR — a payment can land in the last
     * seconds before expiry and its webhook can still fail, and then this is the
     * only thing that will ever find it — so the window is the QR's own life
     * plus `grace` minutes, not the QR's life alone. Skipping expired rows
     * outright would have been cheaper and would have dropped exactly the
     * payments this command exists to rescue.
     *
     * Legacy rows minted before expires_at existed fall back to the same
     * created_at + expire-after cutoff isStale() uses.
     *
     * @return Builder<KhqrPayment>
     */
    private function reconcileWindow(int $expireAfter, int $grace): Builder
    {
        return KhqrPayment::query()
            ->whereIn('status', PaymentStatus::openValues())
            ->where('channel', 'api')
            // Hard ceiling, kept from the original query: whatever `grace` is
            // set to, never re-verify a row for more than a day.
            ->where('created_at', '>', now()->subDay())
            ->where(function (Builder $q) use ($expireAfter, $grace) {
                $q->where('expires_at', '>', now()->subMinutes($grace))
                    ->orWhere(fn (Builder $legacy) => $legacy
                        ->whereNull('expires_at')
                        ->where('created_at', '>', now()->subMinutes($expireAfter + $grace)));
            });
    }

    /**
     * Rows that have fallen out of the window still open.
     *
     * They are no longer costing anything, which is the point — but they are
     * also no longer being watched by anything, and an open row nobody is
     * looking at is how ids 5 and 8 sat in qr_generated for seventy-three days.
     * Printed rather than auto-expired: closing a payment on no evidence is the
     * mistake this whole command is written around. `khqr:expire-abandoned` is
     * the deliberate, operator-run way to clear them.
     */
    private function reportAbandoned(int $expireAfter, int $grace): void
    {
        $abandoned = KhqrPayment::query()
            ->whereIn('status', PaymentStatus::openValues())
            ->where('channel', 'api')
            ->whereNotIn('id', $this->reconcileWindow($expireAfter, $grace)->select('id'))
            ->count();

        if ($abandoned > 0) {
            $this->warn("{$abandoned} open row(s) are past the reconcile window and are no longer being "
                .'verified. Review with `php artisan khqr:expire-abandoned --dry-run`.');
        }
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
