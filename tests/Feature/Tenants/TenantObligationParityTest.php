<?php

use App\Models\Payments;
use App\Models\Utilities;
use App\Services\Tenants\TenantObligationService;
use Carbon\Carbon;

/**
 * The tenant's own "what do I owe" screen and the landlord's rent collection
 * page answer the SAME question, so they must never give different answers.
 *
 * Rent here is derived rather than invoiced, which is exactly why this keeps
 * being a live risk: every screen that states what a month owes re-derives it,
 * and CLAUDE.md lists four call sites that had already drifted — the tenant
 * badge, the contract, the printable bill and the dashboard tiles. A tenant
 * reading a different balance than the person collecting it is the worst
 * version of that bug, because the tenant cannot see the page that disagrees.
 *
 * So this asserts agreement against the collection page's OWN output, scenario
 * by scenario, rather than against numbers copied into the test.
 */
function obligationFixture(array $rentalAttrs = []): array
{
    $admin = makeAdmin();
    auth()->login($admin);
    makeFiscalPeriod($admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);

    $room = makeApartment(null, ['monthly_rent' => 500]);
    $tenant = makeTenant($room, ['move_in_date' => '2026-05-10']);
    $rental = makeRental($tenant, $room, array_merge([
        'rent_amount' => 500,
        'start_date' => '2026-05-10',
    ], $rentalAttrs));

    return compact('admin', 'room', 'tenant', 'rental');
}

/** The collection page's own bill row for this rental, in the viewed month. */
function collectionPageRow(array $f, int $month, int $year): array
{
    $response = test()->actingAs($f['admin'])->get(route('admin.revenue_expense.record_income', [
        'month' => $month,
        'year' => $year,
    ]));
    $response->assertOk();

    $row = collect($response->viewData('tenantBills')->items())
        ->first(fn ($b) => $b['rental']->id === $f['rental']->id);

    expect($row)->not->toBeNull('the collection page did not bill this rental');

    return $row;
}

function payObligationRent(int $rentalId, string $date, float $amount = 500): Payments
{
    return Payments::create([
        'rental_id' => $rentalId, 'amount' => $amount,
        'due_date' => $date, 'paid_at' => $date,
        'payment_method' => 'cash', 'payment_status' => 'paid',
        'payment_type' => 'rent', 'late_fee' => 0,
    ]);
}

function charge(array $f, string $type, float $amount, int $month, int $year, bool $paid = false): Utilities
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
        'paid_at' => $paid ? Carbon::create($year, $month, 20) : null,
    ]);
}

/** Every field both sides state about the same month has to match. */
function assertAgrees(array $f, int $month, int $year): void
{
    $row = collectionPageRow($f, $month, $year);
    $obligation = app(TenantObligationService::class)->forRental($f['rental'], $month, $year);

    expect($obligation['rent_amount'])->toEqual($row['monthly_rent'])
        ->and($obligation['rent_status'])->toBe($row['rent_status'])
        ->and($obligation['charges_status'])->toBe($row['charges_status'])
        ->and($obligation['charges_settled'])->toBe($row['charges_settled'])
        ->and($obligation['status'])->toBe($row['status'])
        ->and($obligation['has_outstanding'])->toBe($row['has_outstanding'])
        ->and($obligation['unpaid_charge_total'])->toEqual($row['unpaid_charge_total'])
        ->and($obligation['is_upcoming'])->toBe($row['is_upcoming'])
        ->and($obligation['late_fee_suggested'])->toEqual($row['late_fee_suggested'])
        ->and($obligation['due_date']->toDateString())->toBe($row['due_date']->toDateString());
}

beforeEach(fn () => Carbon::setTestNow('2026-08-20'));
afterEach(fn () => Carbon::setTestNow());

it('agrees on an unpaid month with no charges raised', function () {
    $f = obligationFixture();

    assertAgrees($f, 8, 2026);
});

it('agrees once rent is in but the charges are still open', function () {
    $f = obligationFixture();
    payObligationRent($f['rental']->id, '2026-08-10');
    charge($f, 'electricity', 35, 8, 2026);

    assertAgrees($f, 8, 2026);

    $obligation = app(TenantObligationService::class)->forRental($f['rental'], 8, 2026);

    // Rent in, charges open: still the pending bucket, and what is owed is the
    // charge alone — never the rent again.
    expect($obligation['status'])->toBe('pending')
        ->and($obligation['rent_status'])->toBe('paid')
        ->and($obligation['total_outstanding'])->toEqual(35.0);
});

it('agrees when both sides are settled', function () {
    $f = obligationFixture();
    payObligationRent($f['rental']->id, '2026-08-10');
    charge($f, 'water', 12, 8, 2026, paid: true);

    assertAgrees($f, 8, 2026);

    expect(app(TenantObligationService::class)->forRental($f['rental'], 8, 2026))
        ->toMatchArray(['status' => 'paid', 'total_outstanding' => 0.0]);
});

it('agrees on an overdue month, late fee included', function () {
    $f = obligationFixture();
    auth()->login($f['admin']);
    settings(['late_fee_percent' => '1']);

    assertAgrees($f, 7, 2026);

    expect(app(TenantObligationService::class)->forRental($f['rental'], 7, 2026)['rent_status'])
        ->toBe('overdue');
});

it('agrees on a prorated move-in month under a collection day', function () {
    Carbon::setTestNow('2026-08-25');

    $admin = makeAdmin();
    auth()->login($admin);
    settings(['billing_cycle_day' => '2']);
    makeFiscalPeriod($admin, ['opening_date' => '2026-01-01', 'closing_date' => '2026-12-31']);

    $room = makeApartment(null, ['monthly_rent' => 300]);
    $tenant = makeTenant($room, ['move_in_date' => '2026-08-08']);
    $rental = makeRental($tenant, $room, ['rent_amount' => 300, 'start_date' => '2026-08-08']);

    $f = compact('admin', 'room', 'tenant', 'rental');

    assertAgrees($f, 8, 2026);

    // The whole point of deriving: the prorated figure, not the raw column.
    expect(app(TenantObligationService::class)->forRental($rental, 8, 2026)['rent_amount'])
        ->toBe(241.94);
});

it('agrees that a tenancy starting later owes nothing yet', function () {
    Carbon::setTestNow('2026-08-20');

    $admin = makeAdmin();
    auth()->login($admin);
    makeFiscalPeriod($admin, ['opening_date' => '2026-01-01', 'closing_date' => '2026-12-31']);

    $room = makeApartment(null, ['monthly_rent' => 400]);
    $tenant = makeTenant($room, ['move_in_date' => '2026-09-05']);
    $rental = makeRental($tenant, $room, ['rent_amount' => 400, 'start_date' => '2026-09-05']);

    $f = compact('admin', 'room', 'tenant', 'rental');

    assertAgrees($f, 8, 2026);

    $obligation = app(TenantObligationService::class)->forRental($rental, 8, 2026);

    expect($obligation['is_upcoming'])->toBeTrue()
        ->and($obligation['has_outstanding'])->toBeFalse()
        ->and($obligation['total_outstanding'])->toEqual(0.0);
});

/**
 * A charge raised but never collected in a month that has since ended still
 * reads as owed — the tenant must keep seeing it after the month rolls over.
 */
it('agrees on a past month left unpaid', function () {
    $f = obligationFixture();
    charge($f, 'electricity', 40, 6, 2026);

    assertAgrees($f, 6, 2026);

    expect(app(TenantObligationService::class)->forRental($f['rental'], 6, 2026))
        ->toMatchArray(['status' => 'overdue', 'total_outstanding' => 540.0]);
});
