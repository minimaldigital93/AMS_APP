<?php

namespace App\Console\Commands;

use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RevenueExpense\KhqrCredentials;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dev smoke test: mint a REAL platform (subscription / Flow A) KHQR using the
 * credentials configured in the superadmin Payment Settings panel, so you can
 * scan + pay it from a Bakong app and verify the live khqr.cc integration
 * end-to-end. It hangs the payment on a throwaway "KHQR Test" account +
 * pending subscription; run with --cleanup to delete everything it created.
 *
 * Manual smoke test only — not a production payment path.
 */
class TestKhqrQr extends Command
{
    protected $signature = 'khqr:test-qr
        {amount=0.01 : Amount in USD to mint the QR for}
        {--cleanup : Delete all test data this command has created, then exit}';

    protected $description = 'Mint a real platform KHQR (or --cleanup test rows) to smoke-test the khqr.cc integration';

    /** Phone prefix that tags accounts this command creates, so --cleanup can find them. */
    private const TEST_PHONE_PREFIX = 'KHQRTEST';

    public function handle(KhqrPaymentService $khqr): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        $amount = (float) $this->argument('amount');
        if ($amount <= 0) {
            $this->error('Amount must be greater than zero.');

            return self::FAILURE;
        }

        $creds = KhqrCredentials::platform();
        if ($creds->profileId === '' || $creds->secret === '') {
            $this->error('Platform KHQRPay credentials are not set — configure Profile ID + Secret Key in Superadmin → Payment Settings first.');

            return self::FAILURE;
        }

        if (config('services.khqrpay.demo')) {
            $this->warn('KHQRPAY_DEMO is ON — the QR will be a local simulation, not a real khqr.cc QR. Set KHQRPAY_DEMO=false for a real test.');
        }

        $plan = Plan::where('is_active', true)->orderBy('price_usd')->first() ?? Plan::first();
        if (! $plan) {
            $this->error('No plan exists — create one in Superadmin → Plans first.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => 'KHQR Test',
            'phone' => self::TEST_PHONE_PREFIX.'-'.now()->format('YmdHis'),
            'password' => Hash::make(Str::random(16)),
            'status' => 'inactive',
        ]);
        // An account owner points at itself.
        $user->forceFill(['account_id' => $user->id])->save();

        $subscription = Subscription::create([
            'account_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
        ]);

        try {
            $row = $khqr->createSubscriptionQr($subscription, $amount);
        } catch (\Throwable $e) {
            $this->error('Minting failed: '.$e->getMessage());
            $this->line('See storage/logs/laravel.log for the KHQRPay request/response.');

            return self::FAILURE;
        }

        // KHQRPay is a hosted-checkout gateway — there is no QR image to fetch.
        // Build the signed checkout URL the signup funnel redirects customers to.
        $checkoutUrl = $khqr->subscriptionCheckoutUrl($row, route('subscribe.checkout', $row->public_token));

        $this->newLine();
        $this->info('Hosted checkout ready ✔');
        $this->line('  Amount      : '.number_format($amount, 2).' '.$creds->currency);
        $this->line('  Transaction : '.$row->transaction_id);
        $this->line('  Checkout URL: '.$checkoutUrl);
        $this->newLine();
        $this->line('Open the checkout URL in a browser and pay with a Bakong app.');
        $this->line('It auto-confirms via the webhook; to force a check run: php artisan khqr:reconcile');
        $this->line('Clean up test data when done:  php artisan khqr:test-qr --cleanup');

        return self::SUCCESS;
    }

    /** Remove every account (and its subscriptions + KHQR rows) this command created. */
    private function cleanup(): int
    {
        $users = User::where('phone', 'like', self::TEST_PHONE_PREFIX.'%')->get();
        $payments = 0;
        $subs = 0;

        foreach ($users as $user) {
            foreach (Subscription::where('account_id', $user->id)->pluck('id') as $subId) {
                $payments += KhqrPayment::where('subscription_id', $subId)->delete();
            }
            $subs += Subscription::where('account_id', $user->id)->delete();
            $user->delete();
        }

        $this->info("Cleaned up: {$users->count()} test account(s), {$subs} subscription(s), {$payments} KHQR row(s).");

        return self::SUCCESS;
    }
}
