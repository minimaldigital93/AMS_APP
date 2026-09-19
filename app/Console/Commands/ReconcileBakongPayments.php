<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\KhqrPayment;
use App\Services\Bakong\BakongProviderClient;
use App\Services\Bakong\BakongTransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The safety net: confirm Bakong payments that landed while nobody was looking.
 *
 * IT SHIPS OFF, AND THAT IS A DELIBERATE DOWNGRADE FROM THE KHQRPay VERSION.
 * There, the net existed to rescue payments whose WEBHOOK failed to arrive — a
 * real, recurring failure with a clear cause. Bakong sends no webhook at all, so
 * there is no delivery to fail: a payment is confirmed by a poll or it is not.
 * That makes the net far less valuable here and exactly as expensive, so
 * BAKONG_RECONCILE_ENABLED defaults to false and the scheduler skips it.
 *
 * What it is still worth switching on for: the payer who scans, pays, and closes
 * the tab before the page confirms. Their money has arrived and nothing is
 * watching. Without the net that row expires and the subscription never
 * activates.
 *
 * Three bounds, and the window is the important one:
 *
 *  - ONLY qr_generated / waiting_payment rows, only provider 'bakong', only
 *    within expires_at + BAKONG_RECONCILE_GRACE. The KHQRPay net swept every
 *    open API row created in the last DAY, which with a 10-minute QR is 288
 *    live calls per abandoned checkout — and because a refusal correctly never
 *    closes a row, nothing ever took it back out of scope. The allowance was
 *    gone by 02:30 with nobody having touched the app. "Ask again later" has no
 *    exit when the gateway never answers, so the bound must come from how long
 *    the asking lasts.
 *
 *  - A REFUSAL NEITHER CONFIRMS NOR EXPIRES. Expiry is terminal, so expiring on
 *    a refusal means the net never looks at that QR again even after the
 *    gateway recovers — and with no webhook, nothing else ever will either.
 *
 *  - Every call still goes through BakongProviderClient, so the cooldown, the
 *    attempt cap and the daily ceiling apply to the net exactly as they do to a
 *    browser poll. A scheduled command must never be trusted to gate itself,
 *    which is why this checks the feature switch too even though the scheduler
 *    already skips on it.
 */
class ReconcileBakongPayments extends Command
{
    /** The only statuses worth asking about: a QR was rendered and not yet decided. */
    private const VERIFIABLE_STATUSES = [
        PaymentStatus::QrGenerated,
        PaymentStatus::WaitingPayment,
    ];

    protected $signature = 'bakong:reconcile
                            {--limit=25 : Most rows to inspect in one run}
                            {--dry-run : Report what would be verified without calling Bakong}';

    protected $description = 'Confirm Bakong payments that landed after the payer closed the page';

    public function handle(BakongTransactionService $bakong): int
    {
        // Defence in depth. The scheduler already skips on both switches; a
        // command run by hand, by a forgotten cron, or by a deploy script does
        // not go through the scheduler at all.
        if (! BakongProviderClient::featureEnabled()) {
            $this->warn('Bakong is switched off — nothing to reconcile, and nothing was sent.');

            return self::SUCCESS;
        }

        if (! config('bakong.reconcile_enabled')) {
            $this->warn('BAKONG_RECONCILE_ENABLED is false. No rows were verified.');

            return self::SUCCESS;
        }

        $grace = max(0, (int) config('bakong.reconcile_grace', 30));
        $rows = $this->candidates($grace, (int) $this->option('limit'));

        if ($rows->isEmpty()) {
            $this->info('No open Bakong payments inside the reconcile window.');

            return self::SUCCESS;
        }

        $this->line("Inspecting {$rows->count()} open payment(s), grace {$grace} min.");

        $confirmed = $refused = $unpaid = 0;

        foreach ($rows as $row) {
            if ($this->option('dry-run')) {
                $this->line("  would verify {$row->transaction_id} (expires {$row->expires_at})");

                continue;
            }

            // The grace is handed to the client so its session gate allows the
            // rescue — and ONLY the rescue. A row past even the grace is not
            // askable, which is what stops the net running forever on a QR the
            // gateway will never answer about.
            $outcome = $bakong->verifyOutcome($row, $grace);

            match ($outcome) {
                BakongTransactionService::VERIFY_PAID => $this->confirm_($row, $bakong, $confirmed),
                BakongTransactionService::VERIFY_UNPAID => $this->expire($row, $bakong, $unpaid),
                default => $refused++,
            };
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — no Bakong request was made.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("Confirmed: {$confirmed}   Expired: {$unpaid}   Refused (left open): {$refused}");

        return self::SUCCESS;
    }

    /**
     * Open Bakong API rows whose QR is still inside its grace window.
     *
     * created_at is bounded at a day regardless, the same hard ceiling
     * isActiveBakongSession() applies: a row with a bad expires_at must not stay
     * askable indefinitely however generous the grace is.
     */
    private function candidates(int $grace, int $limit)
    {
        return KhqrPayment::query()
            ->where('provider', 'bakong')
            ->where('channel', 'api')
            ->whereIn('status', array_map(fn ($s) => $s->value, self::VERIFIABLE_STATUSES))
            ->whereNull('paid_at')
            ->whereNotNull('qr_md5')
            ->where('created_at', '>=', now()->subDay())
            ->where(function ($q) use ($grace) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now()->subMinutes($grace));
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    private function confirm_(KhqrPayment $row, BakongTransactionService $bakong, int &$confirmed): void
    {
        Log::info('Bakong reconcile confirmed a payment', ['transaction' => $row->transaction_id]);

        app(\App\Services\RevenueExpense\KhqrPaymentService::class)->finalize($row);
        $this->line("  <fg=green>confirmed</> {$row->transaction_id}");
        $confirmed++;
    }

    /**
     * Only a CONCLUSIVE unpaid closes a row, and only once its own deadline has
     * passed. A payment can still land in the seconds after a poll said no.
     */
    private function expire(KhqrPayment $row, BakongTransactionService $bakong, int &$unpaid): void
    {
        if ($bakong->expireIfElapsed($row)) {
            $this->line("  expired {$row->transaction_id}");
            $unpaid++;
        }
    }
}
