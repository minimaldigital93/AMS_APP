<?php

use App\Enums\PaymentStatus;
use App\Models\Accounts;
use App\Models\Floors;
use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Models\Payments;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * The landlord's confirmation queue.
 *
 * A tenant-initiated rent QR can only be settled by a human who has seen the
 * money land in the landlord's own bank — this app never sees that account.
 * Before this queue existed the session was minted and then visible to nobody:
 * the tenant sat on "waiting for confirmation" and the landlord was never told
 * there was anything to confirm. So the thing worth pinning is not the markup
 * but the loop: tenant starts it, landlord sees it, confirming books it.
 */
function pendingFixture(): array
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

    // An explicit property/floor, because supervisor scoping is a property
    // question and makeApartment()'s default floor has no building.
    $property = Property::create(['name' => 'Main']);
    $floor = Floors::create(['property_id' => $property->id, 'floor_name' => 'F1']);
    $room = makeApartment($floor, ['monthly_rent' => 500, 'status' => 'occupied']);

    $tenant = makeTenant($room, ['move_in_date' => '2026-05-10']);
    $rental = makeRental($tenant, $room, ['rent_amount' => 500, 'start_date' => '2026-05-10']);

    $user = User::factory()->create(['name' => 'Paying Tenant']);
    $user->assignRole('tenant');
    $user->forceFill(['account_id' => $admin->id])->save();
    $tenant->forceFill(['user_id' => $user->id])->save();

    auth()->logout();

    return compact('admin', 'property', 'floor', 'room', 'tenant', 'rental', 'user');
}

/** The tenant starts a rent payment, exactly as the portal does. */
function tenantStartsPayment(array $f, string $side = 'rent'): KhqrPayment
{
    test()->actingAs($f['user'])->post(route('tenant.payments.pay', [
        'side' => $side, 'year' => 2026, 'month' => 8,
    ]))->assertRedirect();

    return KhqrPayment::where('rental_id', $f['rental']->id)->latest('id')->firstOrFail();
}

beforeEach(function () {
    Carbon::setTestNow('2026-08-20');
    Http::preventStrayRequests();
    Http::fake();
});
afterEach(fn () => Carbon::setTestNow());

it('shows the landlord a payment the tenant started', function () {
    $f = pendingFixture();
    $row = tenantStartsPayment($f);

    test()->actingAs($f['admin'])
        ->get(route('admin.revenue_expense.pending_payments'))
        ->assertOk()
        ->assertSee($f['tenant']->name)
        ->assertSee($row->transaction_id)
        ->assertSee(__('messages.confirm_received'));

    Http::assertNothingSent();
});

it('books the money when the landlord confirms', function () {
    $f = pendingFixture();
    $row = tenantStartsPayment($f);

    test()->actingAs($f['admin'])
        ->post(route('admin.revenue_expense.pending_confirm', $row->transaction_id))
        ->assertRedirect(route('admin.revenue_expense.pending_payments'));

    expect($row->fresh()->status)->toBe(PaymentStatus::Paid->value);

    $payment = Payments::where('rental_id', $f['rental']->id)
        ->where('payment_type', 'rent')->firstOrFail();

    expect((float) $payment->amount)->toEqual(500.0)
        ->and($payment->payment_method)->toBe('khqr')
        ->and($payment->transaction_reference)->toBe($row->transaction_id);

    // And it reached the landlord's ledger, under the landlord.
    $ledger = Accounts::where('payment_id', $payment->id)->firstOrFail();
    expect($ledger->user_id)->toBe($f['admin']->id)
        ->and($ledger->category)->toBe(Accounts::CAT_RENT_INCOME);

    Http::assertNothingSent();
});

it('clears the tenant balance once the landlord confirms', function () {
    $f = pendingFixture();
    $row = tenantStartsPayment($f);

    test()->actingAs($f['admin'])
        ->post(route('admin.revenue_expense.pending_confirm', $row->transaction_id));

    $response = test()->actingAs($f['user'])->get(route('tenant.payments.index'));
    $response->assertOk();

    $august = collect($response->viewData('obligations'))->firstWhere('label', 'August 2026');

    expect($august['rent_paid'])->toBeTrue()
        ->and($august['rent_outstanding'])->toEqual(0.0);
});

/**
 * §11: the admin must not have to guess how a payment arrived. The history
 * modal used to show only month/amount/paid, so a tenant's KHQR payment was
 * indistinguishable from cash taken at the door and could not be matched
 * against a bank statement.
 */
it('shows the admin how the money arrived', function () {
    $f = pendingFixture();
    $row = tenantStartsPayment($f);

    test()->actingAs($f['admin'])
        ->post(route('admin.revenue_expense.pending_confirm', $row->transaction_id));

    test()->actingAs($f['admin'])
        ->get(route('admin.tenants.show', $f['tenant']->id))
        ->assertOk()
        ->assertSee(__('messages.khqr'))
        ->assertSee($row->transaction_id);
});

it('books nothing when the landlord rejects', function () {
    $f = pendingFixture();
    $row = tenantStartsPayment($f);

    test()->actingAs($f['admin'])
        ->post(route('admin.revenue_expense.pending_reject', $row->transaction_id))
        ->assertRedirect();

    expect($row->fresh()->status)->toBe(PaymentStatus::Rejected->value)
        ->and(Payments::where('rental_id', $f['rental']->id)->count())->toBe(0)
        ->and(Accounts::count())->toBe(0);
});

/**
 * Confirming twice must not book twice — a landlord double-clicking, or two
 * co-admins working the same queue, is the ordinary case rather than the
 * exotic one.
 */
it('does not book a second time when confirmed twice', function () {
    $f = pendingFixture();
    $row = tenantStartsPayment($f);

    $confirm = fn () => test()->actingAs($f['admin'])
        ->post(route('admin.revenue_expense.pending_confirm', $row->transaction_id));

    $confirm();
    $confirm();

    expect(Payments::where('rental_id', $f['rental']->id)->count())->toBe(1)
        ->and(Accounts::where('category', Accounts::CAT_RENT_INCOME)->count())->toBe(1);
});

it('leaves the queue empty when no tenant has started anything', function () {
    $f = pendingFixture();

    test()->actingAs($f['admin'])
        ->get(route('admin.revenue_expense.pending_payments'))
        ->assertOk()
        ->assertSee(__('messages.no_pending_tenant_payments'));
});

/**
 * A landlord-initiated session belongs to the page that created it. Listing it
 * here too would invite the same money being confirmed from two places.
 */
it('lists only tenant-initiated sessions', function () {
    $f = pendingFixture();

    $row = tenantStartsPayment($f);
    $row->forceFill(['initiated_by_user_id' => null])->save();

    test()->actingAs($f['admin'])
        ->get(route('admin.revenue_expense.pending_payments'))
        ->assertOk()
        ->assertSee(__('messages.no_pending_tenant_payments'));
});

it('never shows one account the payments of another', function () {
    $mine = pendingFixture();
    $row = tenantStartsPayment($mine);

    $otherAdmin = makeAdmin();
    // Logged in first: FiscalPeriods is account-scoped, so a period created
    // while the tenant session is still acting would be stamped to the WRONG
    // account and invisible to its own owner.
    auth()->login($otherAdmin);
    makeFiscalPeriod($otherAdmin, ['opening_date' => '2026-01-01', 'closing_date' => '2026-12-31']);
    auth()->logout();

    test()->actingAs($otherAdmin)
        ->get(route('admin.revenue_expense.pending_payments'))
        ->assertOk()
        ->assertDontSee($row->transaction_id);

    // And it cannot be settled by id from outside the account either.
    test()->actingAs($otherAdmin)
        ->post(route('admin.revenue_expense.pending_confirm', $row->transaction_id))
        ->assertNotFound();

    expect($row->fresh()->status)->not->toBe(PaymentStatus::Paid->value);
});

it('scopes the queue to a supervisor assigned properties', function () {
    $f = pendingFixture();
    $row = tenantStartsPayment($f);

    $sup = makeSupervisor(['account_id' => $f['admin']->id]);

    // Assigned to a DIFFERENT building than the one the payment came from.
    $other = Property::create(['name' => 'Elsewhere', 'supervisor_id' => $sup->id]);

    test()->actingAs($sup)
        ->get(route('supervisor.revenue_expense.pending_payments'))
        ->assertOk()
        ->assertDontSee($row->transaction_id);

    // Reassign the payment's own property to them and it appears.
    $f['property']->update(['supervisor_id' => $sup->id]);

    test()->actingAs($sup)
        ->get(route('supervisor.revenue_expense.pending_payments'))
        ->assertOk()
        ->assertSee($row->transaction_id);

    expect($other->supervisor_id)->toBe($sup->id);
});
