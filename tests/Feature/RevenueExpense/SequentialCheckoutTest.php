<?php

use App\Models\Accounts;
use App\Models\Payments;
use App\Models\Utilities;
use Carbon\Carbon;

/**
 * A bill is collected on TWO visits, and each one is its own payment.
 *
 * Rent is handed over before the month ends; the meters are read and the
 * charges collected at the turn of the month. So the first checkout takes the
 * rent alone and the second takes the charges alone, and the two must not
 * borrow anything from each other — least of all the payment method, which is
 * whatever the tenant happened to hand over on that visit (cash for the rent,
 * a bank transfer or KHQR for the charges).
 *
 * Everything derived downstream keys off the individual Payments row, so this
 * pins that each visit writes its own: its own method, its own type, its own
 * anchor date and its own ledger rows.
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

function seqVisit(array $overrides = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->admin)->post(route('admin.revenue_expense.checkout'), array_merge([
        'rental_id' => test()->rental->id,
        'payment_method' => 'cash',
        'payment_date' => '2026-07-25',
        'billing_month' => 7,
        'billing_year' => 2026,
        // The hidden field is on the form whichever side is being collected.
        'rent_amount' => 500,
    ], $overrides));
}

function seqAddCharge(string $type = 'electricity', float $amount = 42.5): Utilities
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
    ]);
}

function seqJulyRow(): array
{
    $response = test()->actingAs(test()->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 7, 'year' => 2026]));

    return collect($response->viewData('tenantBills')->items())
        ->firstWhere(fn ($b) => $b['rental']->id === test()->rental->id);
}

it('records the rent visit and the charges visit as two payments, each with its own method', function () {
    // ── Visit 1: rent only, paid in cash. No meter has been read yet.
    seqVisit(['pay_rent' => 1]);

    // ── The meters are read and the charge is raised.
    seqAddCharge();

    // ── Visit 2: charges only, settled by bank transfer.
    seqVisit([
        'payment_method' => 'bank',
        'payment_date' => '2026-08-02',
        'pay_utilities' => 1,
    ]);

    $payments = Payments::where('rental_id', $this->rental->id)->get()->keyBy('payment_type');

    expect($payments)->toHaveCount(2);

    expect($payments['rent']->amount)->toBe(500.0)
        ->and($payments['rent']->payment_method)->toBe('cash')
        // Anchored in the month the rent is FOR — that is what turns July green.
        ->and($payments['rent']->paid_at->format('Y-m'))->toBe('2026-07');

    expect($payments['utilities']->amount)->toBe(42.5)
        // The second visit's method is its own; the first one's does not carry.
        ->and($payments['utilities']->payment_method)->toBe('bank');

    // Each visit booked its own income, under its own category, and neither
    // ledger row hangs off the other's payment.
    $ledger = Accounts::where('user_id', $this->admin->id)->get();
    expect((float) $ledger->where('category', Accounts::CAT_RENT_INCOME)->sum('amount'))->toBe(500.0)
        ->and((float) $ledger->where('category', Accounts::CAT_UTILITY_INCOME)->sum('amount'))->toBe(42.5)
        ->and($ledger->firstWhere('category', Accounts::CAT_RENT_INCOME)->payment_id)->toBe($payments['rent']->id)
        ->and($ledger->firstWhere('category', Accounts::CAT_UTILITY_INCOME)->payment_id)->toBe($payments['utilities']->id);
});

it('offers only the outstanding side on each visit, and settles the row once both are in', function () {
    // Nothing collected yet: rent is what this visit is for, the meters are
    // unread. (Due on the 10th + 3 days' grace, so on the 25th it reads overdue
    // — still the rent side, and still the only side there is.)
    $row = seqJulyRow();
    expect($row['rent_status'])->toBe('overdue')
        ->and($row['charges_status'])->toBe('none')
        ->and($row['has_outstanding'])->toBeTrue();

    seqVisit(['pay_rent' => 1]);

    // Rent in, meters unread — a running month is not settled, so the row is
    // still pending and the "Rent Paid" badge labels it. The checkout button is
    // gone until there is something collectable.
    $row = seqJulyRow();
    expect($row['rent_status'])->toBe('paid')
        ->and($row['charges_status'])->toBe('none')
        ->and($row['status'])->toBe('pending')
        ->and($row['charges_settled'])->toBeFalse()
        ->and($row['has_outstanding'])->toBeFalse();

    seqAddCharge();

    // The charge makes the second visit collectable — and only the charges side
    // is outstanding, which is what the modal pre-ticks.
    $row = seqJulyRow();
    expect($row['rent_status'])->toBe('paid')
        ->and($row['charges_status'])->toBe('pending')
        ->and($row['has_outstanding'])->toBeTrue()
        ->and($row['unpaid_utility_only'])->toBe(42.5);

    seqVisit(['payment_method' => 'khqr', 'pay_utilities' => 1]);

    $row = seqJulyRow();
    expect($row['rent_status'])->toBe('paid')
        ->and($row['charges_status'])->toBe('paid')
        ->and($row['status'])->toBe('paid')
        ->and($row['has_outstanding'])->toBeFalse();
});

it('re-posting the rent on the charges visit never books it twice', function () {
    seqVisit(['pay_rent' => 1]);
    seqAddCharge();

    // Stale tab / double-click: pay_rent rides along with the charges visit.
    seqVisit(['pay_rent' => 1, 'pay_utilities' => 1])
        ->assertSessionHas('warning');

    expect(Payments::where('rental_id', $this->rental->id)->where('payment_type', 'rent')->count())->toBe(1)
        ->and((float) Accounts::where('user_id', $this->admin->id)->where('category', Accounts::CAT_RENT_INCOME)->sum('amount'))->toBe(500.0);
});

it('collects no late fee on a charges-only visit — it is a rent-side line', function () {
    seqVisit(['pay_rent' => 1]);
    seqAddCharge();

    // The modal no longer offers the field once the rent is out of the visit;
    // this is the stale-tab post. Whatever it carries, nothing extra is taken —
    // and the checkout total must agree, which is what calculateCheckoutTotal()
    // was getting wrong: it quoted $52.50 and the app booked $42.50.
    seqVisit([
        'payment_date' => '2026-08-02',
        'pay_utilities' => 1,
        'late_fee' => 10,
    ]);

    expect((float) Payments::where('rental_id', $this->rental->id)->sum('late_fee'))->toBe(0.0)
        ->and(Payments::where('rental_id', $this->rental->id)->where('payment_type', 'utilities')->first()->amount)
        ->toBe(42.5);
});

it('keeps the late fee on the rent visit that actually charged it', function () {
    // Late rent, collected with the fee, on the visit that takes the rent.
    seqVisit(['pay_rent' => 1, 'late_fee' => 10]);

    $rent = Payments::where('rental_id', $this->rental->id)->where('payment_type', 'rent')->first();
    expect($rent->late_fee)->toBe(10.0)
        ->and($rent->amount)->toBe(500.0);

    seqAddCharge();
    seqVisit(['payment_method' => 'bank', 'pay_utilities' => 1]);

    // The fee stayed on the rent row; the charges row carries none of it.
    expect(Payments::where('rental_id', $this->rental->id)->where('payment_type', 'utilities')->first()->late_fee)
        ->toBe(0.0)
        ->and(seqJulyRow()['late_fees'])->toBe(10.0);
});

it('keeps the charges out of the rent visit even when they are already raised', function () {
    // The bill run (or a hand entry) raised this month's charges before the
    // tenant came with the rent. The rent visit still takes the rent alone:
    // the charges card is not offered and pay_utilities is not pre-ticked, so
    // the collector cannot hand back "rent + charges" on the first visit and
    // leave the second one with nothing to collect.
    seqAddCharge();

    $row = seqJulyRow();
    expect($row['rent_status'])->not->toBe('paid')
        ->and($row['charges_status'])->toBe('pending')
        // The row is still collectable — it is the rent that is outstanding.
        ->and($row['has_outstanding'])->toBeTrue();

    // The modal is Alpine state, so the gate is pinned where it is written.
    $html = test()->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 7, 'year' => 2026]))
        ->getContent();

    expect($html)->toContain("chargesStatus === 'pending' && rentAlreadyPaid")
        ->and($html)->toContain("this.payUtilities = chargesStatus === 'pending' && this.rentAlreadyPaid;");

    // …and once the rent is in, the same row offers the charges.
    seqVisit(['pay_rent' => 1]);
    $after = seqJulyRow();
    expect($after['rent_status'])->toBe('paid')
        ->and($after['charges_status'])->toBe('pending')
        ->and($after['has_outstanding'])->toBeTrue();
});
