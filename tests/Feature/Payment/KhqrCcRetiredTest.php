<?php

use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Services\Payment\PaymentManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/**
 * khqr.cc IS GONE, AND MUST STAY GONE.
 *
 * The provider was retired in 2026-09 after a month in which the upstream
 * Bakong token — which khqr.cc held a copy of — was drained to its daily limit
 * every day, while this app's own ledger showed six requests. Every payment
 * then failed with errorCode 17, and because a refused request is metered
 * exactly like a successful one, the day was already lost by the time anyone
 * noticed.
 *
 * The guarantee below is deliberately STRUCTURAL rather than behavioural. A
 * test that merely watched one flow would pass while a forgotten scheduler, a
 * page load or a queue job kept calling — which is precisely how the leak
 * survived so long the first time. So this asserts that the code to call
 * khqr.cc does not exist: no client, no endpoint, no credential, no schedule.
 */
it('has no source file that can reach khqr.cc', function () {
    $offenders = [];

    // database/migrations is deliberately NOT scanned: migrations are frozen
    // history. The ones that CREATED these columns still name them, and
    // rewriting them to hide that would be lying about how the schema got here
    // — the drop migration beside them is the honest record.
    foreach (['app', 'config', 'routes', 'resources/views'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir)));

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $body = file_get_contents($file->getPathname());

            // 'khqr.cc' appears in prose in a few places explaining WHY it is
            // gone; what must not appear is anything that could form a request
            // to it, or the credentials one would be signed with.
            foreach (['khqr.cc/', 'khqrpay_secret', 'khqrpay_profile_id', 'services.khqrpay', 'KhqrProviderClient'] as $needle) {
                if (str_contains($body, $needle)) {
                    $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).' → '.$needle;
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('has no khqr.cc configuration left to switch back on', function () {
    // A config key nobody reads is how a retired integration comes back: it
    // looks configurable, so somebody configures it, and then wonders why
    // nothing happens.
    expect(config('services.khqrpay'))->toBeNull()
        ->and(config('rent_qr.ttl'))->not->toBeNull();
});

it('has no webhook endpoint', function () {
    // /khqr/callback was public AND CSRF-exempt, authenticated only by a
    // khqr.cc secret this app no longer holds. The Bakong Open API publishes no
    // callback to replace it, so a forged POST naming a real transaction id
    // would have been the cheapest possible way to activate a subscription for
    // free.
    expect(Route::has('khqr.callback'))->toBeFalse();

    $this->postJson('/khqr/callback', ['transaction_id' => 'X', 'status' => 'PAID'])->assertNotFound();

    // And nothing is exempt from CSRF any more.
    expect(file_exists(app_path('Services/Payment/WebhookIngestService.php')))->toBeFalse()
        ->and(file_exists(app_path('Http/Controllers/KhqrCallbackController.php')))->toBeFalse()
        ->and(file_exists(app_path('Services/Payment/KhqrProviderClient.php')))->toBeFalse();
});

it('has no scheduled command that could call a gateway on its own', function () {
    $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->map(fn ($e) => $e->command ?? $e->description)
        ->implode(' ');

    // `khqr:reconcile` ran every five minutes on live credentials. It is the
    // shape of failure that matters more than the command: something running
    // with nobody at the keyboard, spending a metered token on rows it could
    // never confirm.
    expect($scheduled)->not->toContain('khqr:reconcile')
        ->and($scheduled)->not->toContain('khqr:diagnose');
});

it('retired the commands that existed only to talk to khqr.cc', function () {
    $commands = array_keys(app(\Illuminate\Contracts\Console\Kernel::class)->all());

    expect($commands)->not->toContain('khqr:reconcile')
        ->and($commands)->not->toContain('khqr:diagnose')
        ->and($commands)->not->toContain('khqr:usage')
        ->and($commands)->not->toContain('khqr:test-qr')
        // These survive, and both are needed after the cutover: one closes the
        // rows khqr.cc left open, the other reports the allowance that replaced
        // it.
        ->and($commands)->toContain('khqr:expire-abandoned')
        ->and($commands)->toContain('bakong:usage');
});

it('sends nothing anywhere when a tenant is charged by KHQR', function () {
    Http::fake();

    $admin = makeAdmin();
    $period = makeFiscalPeriod($admin);
    $apartment = makeApartment(null, ['apartment_number' => 'Z-9', 'monthly_rent' => 300]);
    $tenant = makeTenant($apartment);
    $rental = makeRental($tenant, $apartment, ['rent_amount' => 300]);
    $rental->forceFill(['account_id' => $admin->id])->save();

    $settings = new MerchantPaymentSetting(['bakong_account_id' => 'landlord@aclb', 'currency' => 'USD']);
    $settings->account_id = $admin->id;
    $settings->save();

    $service = new \App\Services\RevenueExpense\KhqrPaymentService;

    $row = $service->createQr($rental, $period, $admin->id, 300.0, [
        'pay_rent' => true, 'rent_amount' => 300, 'payment_date' => now()->toDateString(),
    ]);
    $service->pollAndAdvance($row);
    $service->pollAndAdvance($row->fresh());
    $service->confirmManual($row->fresh());

    // Asserted at the HTTP layer, not against a flag somebody remembered to
    // check: mint, poll, poll again and settle — the whole life of a rent
    // payment — and not one byte leaves this server.
    Http::assertNothingSent();
    expect($row->fresh()->status)->toBe('paid');
});

it('still reads the payments khqr.cc took', function () {
    // The money is history and must keep reporting. Dropping the driver would
    // turn a settled payment from last quarter into a 500 on the payments
    // console and in platform finance.
    $legacy = KhqrPayment::create([
        'transaction_id' => 'LEGACY-READ-1',
        'provider' => 'khqrpay',
        'amount' => 5.99, 'currency' => 'USD', 'status' => 'paid',
        'settlement_target' => 'platform', 'channel' => 'api',
        'paid_at' => now()->subMonth(),
        'checkout_payload' => ['type' => 'subscription'],
    ]);

    expect(app(PaymentManager::class)->for($legacy)->provider())->toBe('khqrpay')
        ->and($legacy->fresh()->isPaid())->toBeTrue();
});
