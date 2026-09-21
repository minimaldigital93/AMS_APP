<?php

use App\Enums\PaymentStatus;
use App\Models\Accounts;
use App\Models\BakongApiCall;
use App\Models\BakongToken;
use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Models\Payments;
use App\Models\User;
use App\Services\Bakong\MerchantBakongCredentials;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Hands-off confirmation of a tenant's rent payment.
 *
 * The rules this pins are the ones that cost money when they are wrong:
 *
 *  - It is OFF until the landlord switches it on and supplies a token of their
 *    own. With it off, nothing is sent — the landlord still confirms by hand.
 *  - It spends the LANDLORD's token against the LANDLORD's allowance, never
 *    the platform's. NBC meters per token, and the platform's ~80/day is shared
 *    with every subscription in the installation.
 *  - A refusal is NOT "unpaid". Only a 2xx may say that. A row is never
 *    expired or failed because the gateway would not answer.
 */
function autoConfirmFixture(): array
{
    $admin = makeAdmin();
    auth()->login($admin);
    makeFiscalPeriod($admin, ['opening_date' => '2026-01-01', 'closing_date' => '2026-12-31']);

    MerchantPaymentSetting::create([
        'account_id' => $admin->id,
        'bank_name' => 'Test Bank',
        'bank_account_name' => 'Landlord',
        'bank_account_number' => '000111222',
        'bakong_account_id' => 'landlord@devb',
        'currency' => 'USD',
    ]);

    $room = makeApartment(null, ['monthly_rent' => 500, 'status' => 'occupied']);
    $tenant = makeTenant($room, ['move_in_date' => '2026-05-10']);
    $rental = makeRental($tenant, $room, ['rent_amount' => 500, 'start_date' => '2026-05-10']);

    $user = User::factory()->create(['name' => 'Paying Tenant']);
    $user->assignRole('tenant');
    $user->forceFill(['account_id' => $admin->id])->save();
    $tenant->forceFill(['user_id' => $user->id])->save();

    auth()->logout();

    return compact('admin', 'room', 'tenant', 'rental', 'user');
}

/** Give the landlord a live token and switch self-confirmation on. */
function landlordHasToken(array $f): void
{
    $claims = ['exp' => now()->addDays(60)->timestamp, 'email' => 'landlord@example.test'];
    $b64 = fn (array $p) => rtrim(strtr(base64_encode(json_encode($p)), '+/', '-_'), '=');
    $jwt = $b64(['alg' => 'HS256']).'.'.$b64($claims).'.sig';

    app(MerchantBakongCredentials::class)->import($f['admin']->id, $jwt);
    MerchantPaymentSetting::forAccount($f['admin']->id)->forceFill(['bakong_enabled' => true])->save();
}

function startTenantRentPayment(array $f): KhqrPayment
{
    test()->actingAs($f['user'])->post(route('tenant.payments.pay', [
        'side' => 'rent', 'year' => 2026, 'month' => 8,
    ]))->assertRedirect();

    return KhqrPayment::where('rental_id', $f['rental']->id)->latest('id')->firstOrFail();
}

function fakeBakongPaid(float $amount = 500.0): void
{
    Http::fake(['bakong.test/*' => Http::response([
        'responseCode' => 0,
        'responseMessage' => 'Getting transaction successfully.',
        'data' => [
            'hash' => str_repeat('a', 64),
            'fromAccountId' => 'tenant@devb',
            'toAccountId' => 'landlord@devb',
            'currency' => 'USD',
            'amount' => $amount,
            'description' => 'rent',
        ],
    ], 200)]);
}

beforeEach(function () {
    Carbon::setTestNow('2026-08-20 10:00:00');
    config()->set('bakong.enabled', true);
    config()->set('bakong.demo', false);
    config()->set('bakong.base_url', 'https://bakong.test');
    config()->set('bakong.integrator.email', 'integrator@ams.test');
    config()->set('bakong.verify_cooldown', 0);
    config()->set('bakong.daily_request_limit', 0);
    config()->set('bakong.max_verify_attempts', 0);
    Http::preventStrayRequests();
});
afterEach(fn () => Carbon::setTestNow());

it('sends nothing at all when the landlord has not switched it on', function () {
    Http::fake();
    $f = autoConfirmFixture();
    $row = startTenantRentPayment($f);

    test()->actingAs($f['user'])
        ->get(route('tenant.payments.status', $row->transaction_id))
        ->assertOk()
        ->assertJson(['paid' => false, 'auto' => false]);

    // The default has to be silence: spending a landlord's metered allowance
    // they never enabled is not a default anyone can consent to.
    Http::assertNothingSent();
    expect($row->fresh()->status)->not->toBe(PaymentStatus::Paid->value);
});

/**
 * The page must not promise to watch for money it cannot see. Which sentence
 * it shows is the difference between a tenant waiting calmly and a tenant
 * ringing the landlord.
 */
it('tells the tenant which kind of waiting this is', function () {
    Http::fake();
    $f = autoConfirmFixture();

    // No landlord token: only the landlord can confirm, and the page says so.
    $manual = startTenantRentPayment($f);
    test()->actingAs($f['user'])
        ->get(route('tenant.payments.qr', $manual->transaction_id))
        ->assertOk()
        ->assertSee(__('messages.awaiting_confirmation'));

    // With a token the page really is watching.
    landlordHasToken($f);
    $auto = startTenantRentPayment($f);
    test()->actingAs($f['user'])
        ->get(route('tenant.payments.qr', $auto->transaction_id))
        ->assertOk()
        ->assertSee(__('messages.waiting_for_payment'));

    Http::assertNothingSent();
});

it('marks the payment paid on its own once Bakong confirms it', function () {
    $f = autoConfirmFixture();
    landlordHasToken($f);
    $row = startTenantRentPayment($f);
    fakeBakongPaid();

    test()->actingAs($f['user'])
        ->get(route('tenant.payments.status', $row->transaction_id))
        ->assertOk()
        ->assertJson(['paid' => true, 'auto' => true]);

    expect($row->fresh()->status)->toBe(PaymentStatus::Paid->value);
});

it('books it into the landlord ledger exactly as a manual confirm would', function () {
    $f = autoConfirmFixture();
    landlordHasToken($f);
    $row = startTenantRentPayment($f);
    fakeBakongPaid();

    test()->actingAs($f['user'])->get(route('tenant.payments.status', $row->transaction_id));

    $payment = Payments::where('rental_id', $f['rental']->id)
        ->where('payment_type', 'rent')->firstOrFail();

    expect((float) $payment->amount)->toEqual(500.0)
        ->and($payment->payment_method)->toBe('khqr');

    $ledger = Accounts::where('payment_id', $payment->id)->firstOrFail();
    expect($ledger->user_id)->toBe($f['admin']->id)
        ->and($ledger->category)->toBe(Accounts::CAT_RENT_INCOME);
});

it('clears the balance on the tenant own page', function () {
    $f = autoConfirmFixture();
    landlordHasToken($f);
    $row = startTenantRentPayment($f);
    fakeBakongPaid();

    test()->actingAs($f['user'])->get(route('tenant.payments.status', $row->transaction_id));

    $response = test()->actingAs($f['user'])->get(route('tenant.payments.index'));
    $august = collect($response->viewData('obligations'))->firstWhere('label', 'August 2026');

    expect($august['rent_paid'])->toBeTrue()
        ->and($august['rent_outstanding'])->toEqual(0.0);
});

it('takes the payment out of the landlord confirmation queue', function () {
    $f = autoConfirmFixture();
    landlordHasToken($f);
    $row = startTenantRentPayment($f);
    fakeBakongPaid();

    test()->actingAs($f['user'])->get(route('tenant.payments.status', $row->transaction_id));

    test()->actingAs($f['admin'])
        ->get(route('admin.revenue_expense.pending_payments'))
        ->assertOk()
        ->assertSee(__('messages.no_pending_tenant_payments'));
});

/**
 * The whole reason this is not on the platform token.
 */
it('charges the request to the landlord allowance, not the platform', function () {
    $f = autoConfirmFixture();
    landlordHasToken($f);
    $row = startTenantRentPayment($f);
    fakeBakongPaid();

    test()->actingAs($f['user'])->get(route('tenant.payments.status', $row->transaction_id));

    $call = BakongApiCall::where('allowed', true)
        ->where('reason', 'payment_verification')
        ->latest('id')->firstOrFail();

    expect($call->target)->toBe('merchant')
        ->and($call->account_id)->toBe($f['admin']->id);

    // Nothing was charged to the platform's budget, which subscriptions live on.
    expect(BakongApiCall::spentOn('platform'))->toBe(0);
});

it('never borrows the platform token for a landlord payment', function () {
    $f = autoConfirmFixture();

    // A perfectly good PLATFORM token exists — and must not be used here,
    // because it confirms one party's money with another party's credential.
    BakongToken::create([
        'email' => 'integrator@ams.test',
        'token' => 'platform.token.value',
        'expires_at' => now()->addDays(60),
        'verified_at' => now(),
    ]);

    Http::fake();
    $row = startTenantRentPayment($f);

    test()->actingAs($f['user'])
        ->get(route('tenant.payments.status', $row->transaction_id))
        ->assertOk()
        ->assertJson(['auto' => false]);

    Http::assertNothingSent();
});

/**
 * A refusal is not a verdict. This is the expensive mistake: reading "we could
 * not ask" as "they did not pay" writes real money out of the books.
 */
it('leaves the row open when the gateway refuses, never calling it unpaid', function () {
    $f = autoConfirmFixture();
    landlordHasToken($f);
    $row = startTenantRentPayment($f);

    // NBC says the day is over — a refusal, not an answer about the payer.
    Http::fake(['bakong.test/*' => Http::response([
        'responseCode' => 1,
        'errorCode' => 17,
        'responseMessage' => 'Daily request limit of 100 exceeded',
    ], 200)]);

    test()->actingAs($f['user'])
        ->get(route('tenant.payments.status', $row->transaction_id))
        ->assertOk()
        ->assertJson(['paid' => false, 'gateway_error' => true]);

    $fresh = $row->fresh();

    expect($fresh->status)->not->toBe(PaymentStatus::Paid->value)
        ->and($fresh->status)->not->toBe(PaymentStatus::Expired->value)
        ->and($fresh->status)->not->toBe(PaymentStatus::Failed->value);

    // Still the landlord's to settle by hand.
    test()->actingAs($f['admin'])
        ->get(route('admin.revenue_expense.pending_payments'))
        ->assertOk()
        ->assertSee($row->transaction_id);
});

it('reports a genuine unpaid without touching the row', function () {
    $f = autoConfirmFixture();
    landlordHasToken($f);
    $row = startTenantRentPayment($f);

    Http::fake(['bakong.test/*' => Http::response([
        'data' => null,
        'errorCode' => 1,
        'responseCode' => 1,
        'responseMessage' => 'Transaction could not be found.Please check and try again.',
    ], 200)]);

    test()->actingAs($f['user'])
        ->get(route('tenant.payments.status', $row->transaction_id))
        ->assertOk()
        ->assertJson(['paid' => false, 'gateway_error' => false]);

    expect(Payments::where('rental_id', $f['rental']->id)->count())->toBe(0);
});

it('does not book twice when the tenant page polls again', function () {
    $f = autoConfirmFixture();
    landlordHasToken($f);
    $row = startTenantRentPayment($f);
    fakeBakongPaid();

    $poll = fn () => test()->actingAs($f['user'])
        ->get(route('tenant.payments.status', $row->transaction_id));

    $poll();
    $poll();
    $poll();

    expect(Payments::where('rental_id', $f['rental']->id)->count())->toBe(1)
        ->and(Accounts::where('category', Accounts::CAT_RENT_INCOME)->count())->toBe(1);
});

/**
 * A rent QR is resolved by an ANSWER, never by a clock.
 *
 * Landlords with their own token mint rent rows on the 'api' channel, which is
 * what khqr:expire-abandoned sweeps. Expiring one on age would close a payment
 * nobody has asked Bakong about — a tenant who paid and closed the page would
 * lose the money from the books and from the landlord's queue at once.
 */
it('never lets the abandoned sweep expire a rent payment', function () {
    Http::fake();
    $f = autoConfirmFixture();
    landlordHasToken($f);
    $row = startTenantRentPayment($f);

    // Long past any TTL.
    $row->forceFill(['expires_at' => now()->subDays(3)])->save();

    test()->artisan('khqr:expire-abandoned', ['--force' => true])->assertExitCode(0);

    expect($row->fresh()->status)->not->toBe(PaymentStatus::Expired->value);

    // And it is still where the landlord can act on it.
    test()->actingAs($f['admin'])
        ->get(route('admin.revenue_expense.pending_payments'))
        ->assertOk()
        ->assertSee($row->transaction_id);

    Http::assertNothingSent();
});

it('keeps one landlord exhaustion away from another', function () {
    $a = autoConfirmFixture();
    landlordHasToken($a);
    $rowA = startTenantRentPayment($a);

    $b = autoConfirmFixture();
    landlordHasToken($b);
    $rowB = startTenantRentPayment($b);

    // ONE dynamic fake: Http::fake() merges stubs rather than replacing them,
    // so re-faking the same URL mid-test would leave the first stub answering
    // both landlords. The first call (A's) is over limit, the second (B's) is
    // a normal settlement.
    $call = 0;
    Http::fake(function () use (&$call) {
        $call++;

        return $call === 1
            ? Http::response([
                'responseCode' => 1, 'errorCode' => 17,
                'responseMessage' => 'Daily request limit of 100 exceeded',
            ], 200)
            : Http::response([
                'responseCode' => 0,
                'responseMessage' => 'Getting transaction successfully.',
                'data' => [
                    'hash' => str_repeat('a', 64),
                    'fromAccountId' => 'tenant@devb',
                    'toAccountId' => 'landlord@devb',
                    'currency' => 'USD',
                    'amount' => 500.0,
                    'description' => 'rent',
                ],
            ], 200);
    });

    test()->actingAs($a['user'])->get(route('tenant.payments.status', $rowA->transaction_id));

    // A's exhaustion latch is A's alone — B's token is untouched and settles.
    test()->actingAs($b['user'])
        ->get(route('tenant.payments.status', $rowB->transaction_id))
        ->assertOk()
        ->assertJson(['paid' => true]);

    expect($rowB->fresh()->status)->toBe(PaymentStatus::Paid->value)
        ->and($rowA->fresh()->status)->not->toBe(PaymentStatus::Paid->value);
});
