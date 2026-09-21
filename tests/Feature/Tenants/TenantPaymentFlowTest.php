<?php

use App\Enums\PaymentStatus;
use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Models\Payments;
use App\Models\User;
use App\Models\Utilities;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * The tenant-facing payment flow.
 *
 * Two things are load-bearing here and both are asserted rather than assumed:
 *
 *  1. ISOLATION. KhqrPayment carries no account scope and no tenant id, so the
 *     only thing between a tenant and someone else's payment session is the
 *     controller re-resolving the row's rental against the signed-in tenant.
 *  2. ZERO BAKONG SPEND. The rent channel builds its QR locally and is
 *     confirmed by the landlord. Nothing in this flow may contact NBC — the
 *     allowance is ~80 requests a DAY for the whole installation, shared with
 *     subscriptions, and this app has already lost a day's worth to a caller
 *     nobody remembered.
 */
function tenantPaymentFixture(array $rentalAttrs = [], array $tenantAttrs = []): array
{
    $admin = makeAdmin();
    auth()->login($admin);
    makeFiscalPeriod($admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);

    MerchantPaymentSetting::create([
        'account_id' => $admin->id,
        'bank_name' => 'Test Bank',
        'bank_account_name' => 'Landlord',
        'bank_account_number' => '000111222',
        'bakong_account_id' => 'landlord@test',
        'currency' => 'USD',
    ]);

    $room = makeApartment(null, ['monthly_rent' => 500, 'status' => 'occupied']);
    $tenant = makeTenant($room, array_merge(['move_in_date' => '2026-05-10'], $tenantAttrs));
    $rental = makeRental($tenant, $room, array_merge([
        'rent_amount' => 500,
        'start_date' => '2026-05-10',
    ], $rentalAttrs));

    $user = User::factory()->create(['name' => 'Tenant Login']);
    $user->assignRole('tenant');
    $user->forceFill(['account_id' => $admin->id])->save();
    $tenant->forceFill(['user_id' => $user->id])->save();

    auth()->logout();

    return compact('admin', 'room', 'tenant', 'rental', 'user');
}

function tenantCharge(array $f, string $type, float $amount, int $month, int $year, bool $paid = false): Utilities
{
    return Utilities::create([
        'tenant_id' => $f['tenant']->id,
        'rental_id' => $f['rental']->id,
        'utility_type' => $type,
        'meter_reading_in' => 0,
        'meter_reading_out' => 0,
        'charge_amount' => $amount,
        'billing_month' => $month,
        'billing_year' => $year,
        'paid_status' => $paid,
    ]);
}

beforeEach(function () {
    Carbon::setTestNow('2026-08-20');
    // Any outbound call at all is a failure in this flow — fake the client so a
    // stray one is caught here instead of on someone's allowance.
    Http::preventStrayRequests();
    Http::fake();
});
afterEach(fn () => Carbon::setTestNow());

/** Rent for every month of the tenancy up to and including the one given. */
function payMonthsUpTo(array $f, array $months): void
{
    foreach ($months as $date) {
        Payments::create([
            'rental_id' => $f['rental']->id, 'amount' => 500,
            'due_date' => $date, 'paid_at' => $date,
            'payment_method' => 'cash', 'payment_status' => 'paid',
            'payment_type' => 'rent', 'late_fee' => 0,
        ]);
    }
}

it('shows the tenant their unpaid rent and each charge by name', function () {
    $f = tenantPaymentFixture();
    payMonthsUpTo($f, ['2026-05-15', '2026-06-15', '2026-07-15']);
    tenantCharge($f, 'electricity', 35, 8, 2026);
    tenantCharge($f, 'water', 12, 8, 2026);

    $response = test()->actingAs($f['user'])->get(route('tenant.payments.index'));

    $response->assertOk()
        ->assertSee(__('messages.electricity'))
        ->assertSee(__('messages.water'))
        ->assertSee('35')
        ->assertSee('12');

    // August rent 500 + 47 of charges, and the tenant is told the one total.
    expect($response->viewData('totalOutstanding'))->toEqual(547.0);

    Http::assertNothingSent();
});

/**
 * Unpaid earlier months must keep showing. A tenant portal that only ever
 * displays the current month quietly hides arrears from the one person who
 * can clear them.
 */
it('carries unpaid earlier months forward into the total', function () {
    $f = tenantPaymentFixture();
    payMonthsUpTo($f, ['2026-05-15', '2026-07-15']); // June skipped

    $response = test()->actingAs($f['user'])->get(route('tenant.payments.index'));
    $response->assertOk();

    $months = collect($response->viewData('obligations'))->pluck('label');

    expect($months)->toContain('June 2026')
        ->and($months)->toContain('August 2026')
        ->and($months)->not->toContain('July 2026')
        ->and($response->viewData('totalOutstanding'))->toEqual(1000.0);
});

it('does not contact bakong when the tenant opens a payment detail page', function () {
    $f = tenantPaymentFixture();

    test()->actingAs($f['user'])
        ->get(route('tenant.payments.show', ['side' => 'rent', 'year' => 2026, 'month' => 8]))
        ->assertOk()
        ->assertSee(__('messages.continue_to_khqr'));

    Http::assertNothingSent();
});

it('derives the amount server side rather than trusting the request', function () {
    $f = tenantPaymentFixture();

    // The form posts nothing but a CSRF token; these are the fields an attacker
    // would reach for, and none of them exist in the route contract.
    test()->actingAs($f['user'])->post(
        route('tenant.payments.pay', ['side' => 'rent', 'year' => 2026, 'month' => 8]),
        ['amount' => 1, 'rent_amount' => 1, 'rental_id' => 9999, 'late_fee' => 500]
    )->assertRedirect();

    $row = KhqrPayment::where('rental_id', $f['rental']->id)->firstOrFail();

    expect((float) $row->amount)->toEqual(500.0)
        ->and($row->checkout_payload['rent_amount'])->toEqual(500.0)
        ->and($row->checkout_payload['late_fee'])->toEqual(0)
        ->and($row->rental_id)->toBe($f['rental']->id);

    Http::assertNothingSent();
});

it('books the QR against the landlord ledger, not the tenant user', function () {
    $f = tenantPaymentFixture();

    test()->actingAs($f['user'])
        ->post(route('tenant.payments.pay', ['side' => 'rent', 'year' => 2026, 'month' => 8]))
        ->assertRedirect();

    $row = KhqrPayment::where('rental_id', $f['rental']->id)->firstOrFail();

    // user_id on a KHQR row is the LEDGER owner. Writing the tenant's id here
    // would open a second set of books under the payer.
    expect($row->user_id)->toBe($f['admin']->id);
});

it('reuses the open session instead of minting a second QR for the same debt', function () {
    $f = tenantPaymentFixture();

    $pay = fn () => test()->actingAs($f['user'])
        ->post(route('tenant.payments.pay', ['side' => 'rent', 'year' => 2026, 'month' => 8]));

    $first = $pay();
    $second = $pay();

    expect(KhqrPayment::where('rental_id', $f['rental']->id)->count())->toBe(1)
        ->and($second->headers->get('Location'))->toBe($first->headers->get('Location'));
});

it('refuses to mint a QR for rent that is already paid', function () {
    $f = tenantPaymentFixture();
    Payments::create([
        'rental_id' => $f['rental']->id, 'amount' => 500,
        'due_date' => '2026-08-10', 'paid_at' => '2026-08-10',
        'payment_method' => 'cash', 'payment_status' => 'paid',
        'payment_type' => 'rent', 'late_fee' => 0,
    ]);

    test()->actingAs($f['user'])
        ->post(route('tenant.payments.pay', ['side' => 'rent', 'year' => 2026, 'month' => 8]))
        ->assertRedirect(route('tenant.payments.index'));

    expect(KhqrPayment::where('rental_id', $f['rental']->id)->count())->toBe(0);
});

it('refuses to mint a QR for a tenancy that has not begun', function () {
    $f = tenantPaymentFixture(
        ['start_date' => '2026-09-05'],
        ['move_in_date' => '2026-09-05'],
    );

    test()->actingAs($f['user'])
        ->post(route('tenant.payments.pay', ['side' => 'rent', 'year' => 2026, 'month' => 8]))
        ->assertRedirect(route('tenant.payments.index'));

    expect(KhqrPayment::where('rental_id', $f['rental']->id)->count())->toBe(0);
});

it('never lets one tenant open another tenant payment session', function () {
    $mine = tenantPaymentFixture();

    // A second tenant in the SAME account — account scope cannot separate these
    // two, so the controller's ownership check is the only thing that does.
    auth()->login($mine['admin']);
    $otherRoom = makeApartment(null, ['monthly_rent' => 400, 'status' => 'occupied']);
    $otherTenant = makeTenant($otherRoom, ['move_in_date' => '2026-05-10']);
    $otherRental = makeRental($otherTenant, $otherRoom, ['rent_amount' => 400, 'start_date' => '2026-05-10']);
    $otherUser = User::factory()->create(['name' => 'Other Tenant']);
    $otherUser->assignRole('tenant');
    $otherUser->forceFill(['account_id' => $mine['admin']->id])->save();
    $otherTenant->forceFill(['user_id' => $otherUser->id])->save();
    auth()->logout();

    test()->actingAs($otherUser)
        ->post(route('tenant.payments.pay', ['side' => 'rent', 'year' => 2026, 'month' => 8]))
        ->assertRedirect();

    $theirs = KhqrPayment::where('rental_id', $otherRental->id)->firstOrFail();

    // 404, not 403: a tenant has no business learning the id even exists.
    test()->actingAs($mine['user'])
        ->get(route('tenant.payments.qr', $theirs->transaction_id))
        ->assertNotFound();

    test()->actingAs($mine['user'])
        ->get(route('tenant.payments.status', $theirs->transaction_id))
        ->assertNotFound();
});

it('keeps the QR page and its status poll entirely offline', function () {
    $f = tenantPaymentFixture();

    test()->actingAs($f['user'])
        ->post(route('tenant.payments.pay', ['side' => 'rent', 'year' => 2026, 'month' => 8]));

    $row = KhqrPayment::where('rental_id', $f['rental']->id)->firstOrFail();

    test()->actingAs($f['user'])
        ->get(route('tenant.payments.qr', $row->transaction_id))
        ->assertOk()
        ->assertSee(__('messages.awaiting_confirmation'));

    test()->actingAs($f['user'])
        ->get(route('tenant.payments.status', $row->transaction_id))
        ->assertOk()
        ->assertJson(['paid' => false]);

    // The whole point: a tenant sitting on this page all day costs nothing.
    Http::assertNothingSent();
});

it('settles the tenant payment into the landlord books when confirmed', function () {
    $f = tenantPaymentFixture();
    tenantCharge($f, 'electricity', 35, 8, 2026);

    test()->actingAs($f['user'])
        ->post(route('tenant.payments.pay', ['side' => 'charges', 'year' => 2026, 'month' => 8]));

    $row = KhqrPayment::where('rental_id', $f['rental']->id)->firstOrFail();
    expect((float) $row->amount)->toEqual(35.0);

    // The landlord confirms — the one existing settlement path, reused.
    app(\App\Services\RevenueExpense\KhqrPaymentService::class)->confirmManual($row->fresh());

    expect($row->fresh()->status)->toBe(PaymentStatus::Paid->value);

    $charge = Utilities::where('rental_id', $f['rental']->id)->firstOrFail();
    expect((bool) $charge->paid_status)->toBeTrue();

    expect(Payments::where('rental_id', $f['rental']->id)
        ->where('payment_type', 'utilities')->count())->toBe(1);

    Http::assertNothingSent();
});

it('keeps tenants out of the landlord collection pages', function () {
    $f = tenantPaymentFixture();

    test()->actingAs($f['user'])
        ->get(route('admin.revenue_expense.record_income'))
        ->assertForbidden();
});

it('shows a tenant with no tenancy an empty state rather than an error', function () {
    seedRoles();
    $stray = User::factory()->create();
    $stray->assignRole('tenant');

    test()->actingAs($stray)
        ->get(route('tenant.payments.index'))
        ->assertOk()
        ->assertSee(__('messages.no_active_tenancy'));
});
