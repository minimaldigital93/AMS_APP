<?php

use App\Models\Payments;
use App\Models\Utilities;
use Carbon\Carbon;

/**
 * Rent and charges are collected on SEPARATE visits: the tenant pays rent
 * before the month ends, then the meters are read and the utilities/other
 * charges are collected at the turn of the month.
 *
 * The bill row therefore has two independent sides, and the row status folds
 * them into ONE of three buckets — paid / pending / overdue. Paid means the
 * whole bill is settled, so a rent-paid tenant who still owes charges is
 * pending. The pending tile is split by side the same way; before this existed
 * a rent-paid tenant's unpaid charges left the tile entirely, which under this
 * workflow is every tenant every month.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-07-25');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 500,
        'start_date' => '2026-05-10',
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

function julyBills(): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 7, 'year' => 2026]));
}

function julyBill(\Illuminate\Testing\TestResponse $response): array
{
    return collect($response->viewData('tenantBills')->items())
        ->firstWhere(fn ($b) => $b['rental']->id === test()->rental->id);
}

function payJulyRent(): Payments
{
    return Payments::create([
        'rental_id' => test()->rental->id,
        'amount' => 500,
        'due_date' => '2026-07-10',
        'paid_at' => '2026-07-25',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'payment_type' => 'rent',
        'late_fee' => 0,
    ]);
}

function addJulyCharge(string $type = 'electricity', float $amount = 42.5): Utilities
{
    return Utilities::create([
        'tenant_id' => test()->tenant->id,
        'rental_id' => test()->rental->id,
        'utility_type' => $type,
        'meter_reading_in' => 0,
        'meter_reading_out' => 0,
        'charge_amount' => $amount,
        'billing_month' => 7,
        'billing_year' => 2026,
        'paid_status' => false,
        'paid_at' => null,
    ]);
}

it('keeps a rent-paid row out of paid while the running month has no charges billed yet', function () {
    payJulyRent();

    $response = julyBills();
    $bill = julyBill($response);

    // 'none' is not 'paid': the meters simply haven't been read yet, so the
    // month is not finished and the row must not read as fully settled.
    // Nothing is collectable yet either — hence has_outstanding false.
    expect($bill['rent_status'])->toBe('paid')
        ->and($bill['charges_status'])->toBe('none')
        ->and($bill['status'])->toBe('pending')
        ->and($bill['has_outstanding'])->toBeFalse();

    expect($response->viewData('paidCount'))->toBe(0)
        ->and($response->viewData('pendingCount'))->toBe(1)
        ->and($response->viewData('totalPendingRent'))->toBe(0.0)
        ->and($response->viewData('totalPendingCharges'))->toBe(0.0);
});

it('settles a closed month that was never billed any charges', function () {
    payJulyRent();

    // August: July is over. An account whose rent is utilities-inclusive never
    // writes a charge row, and its finished months must not sit in partial
    // forever — nothing was billed and nothing is owed.
    Carbon::setTestNow('2026-08-04');

    $response = julyBills();
    $bill = julyBill($response);

    expect($bill['charges_status'])->toBe('none')
        ->and($bill['status'])->toBe('paid')
        ->and($bill['has_outstanding'])->toBeFalse();

    expect($response->viewData('paidCount'))->toBe(1)
        ->and($response->viewData('pendingCount'))->toBe(0);
});

it('prints one status per row and says why on hover', function () {
    payJulyRent();
    addJulyCharge('electricity', 42.5);

    // ONE badge. Rent in, charges still owed reads "Rent Paid" — a label on
    // the pending bucket (the row is still pending), never "Paid" and never a
    // second badge beside it.
    $response = julyBills();

    expect(julyBill($response)['status'])->toBe('pending');

    $response
        ->assertSee(__('messages.rent_paid'))
        ->assertDontSee(__('messages.partial'))
        // Which side is still open survives as the badge's tooltip.
        ->assertSee(__('messages.rent_paid_charges_due'));
});

it('says on hover when a rent-paid row is only waiting on the meter reading', function () {
    payJulyRent();

    julyBills()
        ->assertSee(__('messages.rent_paid'))
        ->assertSee(__('messages.rent_paid_charges_unread'));
});

it('prints paid only once the charges are settled too', function () {
    payJulyRent();
    $charge = addJulyCharge('electricity', 42.5);
    $charge->update(['paid_status' => true, 'paid_at' => '2026-07-25']);

    $response = julyBills();

    expect(julyBill($response)['status'])->toBe('paid');

    $response->assertDontSee(__('messages.rent_paid'));
});

it('keeps the row pending once end-of-month charges are added to a rent-paid tenant', function () {
    payJulyRent();
    addJulyCharge();

    $response = julyBills();
    $bill = julyBill($response);

    expect($bill['rent_status'])->toBe('paid')
        ->and($bill['charges_status'])->toBe('pending')
        ->and($bill['status'])->toBe('pending')
        ->and($bill['has_outstanding'])->toBeTrue()
        ->and($bill['unpaid_charge_total'])->toBe(42.5);

    // Not settled, but rent is in — pending, never overdue, never paid.
    expect($response->viewData('pendingCount'))->toBe(1)
        ->and($response->viewData('paidCount'))->toBe(0)
        ->and($response->viewData('overdueCount'))->toBe(0);
});

it('keeps a rent-paid tenant\'s unpaid charges in the pending total', function () {
    payJulyRent();
    addJulyCharge('water', 18.0);

    $response = julyBills();

    // The regression this whole split exists for: rent is in, so the rent side
    // is zero, but the charges are still owed and must stay visible.
    expect($response->viewData('totalPendingRent'))->toBe(0.0)
        ->and($response->viewData('totalPendingCharges'))->toBe(18.0)
        ->and($response->viewData('totalPending'))->toBe(18.0);
});

it('counts both sides while nothing is paid', function () {
    addJulyCharge('electricity', 40.0);

    $response = julyBills();
    $bill = julyBill($response);

    expect($bill['rent_status'])->toBeIn(['pending', 'overdue'])
        ->and($bill['charges_status'])->toBe('pending')
        // Rent unpaid drives the row; the charges ride along in the same bucket.
        ->and($bill['status'])->toBe($bill['rent_status'])
        ->and($bill['has_outstanding'])->toBeTrue();

    expect($response->viewData('totalPendingRent'))->toBe(500.0)
        ->and($response->viewData('totalPendingCharges'))->toBe(40.0)
        ->and($response->viewData('totalPending'))->toBe(540.0);
});

it('settles charges on a second visit without re-billing the rent', function () {
    payJulyRent();
    addJulyCharge('electricity', 42.5);

    // The end-of-month charges round: rent line left unticked.
    $this->actingAs($this->admin)
        ->post(route('admin.revenue_expense.checkout'), [
            'rental_id' => $this->rental->id,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-01',
            'billing_month' => 7,
            'billing_year' => 2026,
            'rent_amount' => 500,
            'pay_utilities' => 1,
        ])->assertRedirect();

    // Exactly one rent payment — the charges visit must not book rent again.
    expect(Payments::where('rental_id', $this->rental->id)->where('payment_type', 'rent')->count())->toBe(1)
        ->and(Payments::where('rental_id', $this->rental->id)->where('payment_type', 'utilities')->sum('amount'))
        ->toEqual(42.5);

    $response = julyBills();
    $bill = julyBill($response);

    expect($bill['charges_status'])->toBe('paid')
        ->and($bill['status'])->toBe('paid')
        ->and($bill['has_outstanding'])->toBeFalse()
        ->and($response->viewData('totalPendingCharges'))->toBe(0.0);
});

it('keeps the add-charge button on a rent-paid row whose meters are unread', function () {
    payJulyRent();

    // charges_status 'none' — the row that most needs its meter reading typed
    // in. Hiding the button here is what made the workflow impossible.
    julyBills()->assertSee('openAddCharge('.$this->rental->id.',', false);
});

it('hides the add-charge button once rent and charges are both settled', function () {
    payJulyRent();
    addJulyCharge('electricity', 42.5)->update(['paid_status' => true, 'paid_at' => '2026-08-01']);

    $response = julyBills();
    $bill = julyBill($response);

    expect($bill['rent_status'])->toBe('paid')
        ->and($bill['charges_status'])->toBe('paid');

    $response->assertDontSee('openAddCharge('.$this->rental->id.',', false);
});

it('hands the checkout modal only the charges that are still unpaid', function () {
    payJulyRent();
    $paid = addJulyCharge('water', 20.0);
    $paid->update(['paid_status' => true, 'paid_at' => '2026-07-25']);
    addJulyCharge('electricity', 35.0);

    $bill = julyBill(julyBills());

    // The gross figures still describe the month's bill…
    expect($bill['total_utilities'])->toEqual(55.0)
        // …while the checkout modal is quoted only what's collectable, so a
        // second visit can't re-quote a charge the first one settled.
        ->and($bill['unpaid_utility_only'])->toBe(35.0)
        ->and($bill['unpaid_charge_total'])->toBe(35.0);
});
