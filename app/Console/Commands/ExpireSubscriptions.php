<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Flip lapsed subscriptions (active/trialing past expires_at) to 'expired'.
 *
 * Access is already gated lazily by EnsureSubscriptionActive on every request;
 * this makes the stored status truthful for the superadmin panel, reporting,
 * and the topbar notifications. Scheduled daily — see routes/console.php.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark active/trialing subscriptions past their expiry date as expired';

    public function handle(): int
    {
        $expired = 0;

        Subscription::whereIn('status', ['active', 'trialing'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(200, function ($subscriptions) use (&$expired) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update(['status' => 'expired']);
                    $expired++;
                }
            });

        if ($expired > 0) {
            Log::info("subscriptions:expire marked {$expired} subscription(s) expired");
        }

        $this->info("Expired: {$expired}");

        return self::SUCCESS;
    }
}
