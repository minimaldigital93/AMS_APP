<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Property;
use App\Services\Billing\BillingCycleService;
use App\Services\Tenants\TenantRentProgressCalculator;
use Carbon\Carbon;

/**
 * The rent-progress badge on the tenant index answers a CURRENT MONTH question,
 * and both panels render it from the same calculator — so they must give the
 * same answer.
 *
 * They did not. The supervisor page passed its active fiscal period into
 * TenantRentProgressCalculator, which widened the payment window to the whole
 * year while the percentage still divided by ONE month's rent: any tenant with
 * payment history read 100% "paid" in an unpaid month, permanently, for exactly
 * the people whose job is collecting rent. The admin page, passing no period,
 * called the same tenant overdue at the same moment.
 *
 * The calculator also read `rentals.rent_amount` raw, so a prorated move-in
 * month — paid in full at the prorated figure — was stuck at "partial".
 */
function rentProgressFixture(): array
{
    $admin = makeAdmin();
    auth()->login($admin);
    makeFiscalPeriod($admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);

    $property = Property::create(['name' => 'Main']);
    $floor = Floors::create(['property_id' => $property->id, 'floor_name' => 'F1']);
    $room = Apartments::create([
        'floor_id' => $floor->id, 'apartment_number' => 'F101',
        'monthly_rent' => 500, 'status' => 'occupied',
    ]);
    $tenant = makeTenant($room, ['move_in_date' => '2026-05-10']);
    $rental = makeRental($tenant, $room, [
        'rent_amount' => 500,
        'start_date' => '2026-05-10',
    ]);

    $sup = makeSupervisor(['account_id' => $admin->id]);
    $property->update(['supervisor_id' => $sup->id]);

    return compact('admin', 'sup', 'tenant', 'rental');
}

function payRent(int $rentalId, string $date, float $amount = 500): Payments
{
    return Payments::create([
        'rental_id' => $rentalId, 'amount' => $amount,
        'due_date' => $date, 'paid_at' => $date,
        'payment_method' => 'cash', 'payment_status' => 'paid',
        'payment_type' => 'rent', 'late_fee' => 0,
    ]);
}

beforeEach(fn () => Carbon::setTestNow('2026-08-20'));
afterEach(fn () => Carbon::setTestNow());

it('does not count earlier months of the fiscal year as this month rent', function () {
    $f = rentProgressFixture();

    // Paid May, June, July in full. AUGUST IS UNPAID.
    foreach (['2026-05-15', '2026-06-15', '2026-07-15'] as $date) {
        payRent($f['rental']->id, $date);
    }

    $progress = app(TenantRentProgressCalculator::class)->map([$f['tenant']])[$f['tenant']->id];

    expect($progress['paid'])->toEqual(0.0)
        ->and($progress['percent'])->toEqual(0.0)
        ->and($progress['status'])->toBe('overdue');
});

it('gives the admin and supervisor pages the same rent status', function () {
    $f = rentProgressFixture();

    foreach (['2026-05-15', '2026-06-15', '2026-07-15'] as $date) {
        payRent($f['rental']->id, $date);
    }

    $statusOn = function (string $route, $user) use ($f) {
        $response = test()->actingAs($user)->get(route($route));
        $response->assertOk();

        return $response->viewData('rentProgressMap')[$f['tenant']->id]['status'];
    };

    $admin = $statusOn('admin.tenants.index', $f['admin']);
    $supervisor = $statusOn('supervisor.tenants.index', $f['sup']);

    expect($supervisor)->toBe($admin)
        ->and($admin)->toBe('overdue');
});

it('reads paid once this month rent is actually collected', function () {
    $f = rentProgressFixture();
    payRent($f['rental']->id, '2026-08-10');

    $progress = app(TenantRentProgressCalculator::class)->map([$f['tenant']])[$f['tenant']->id];

    expect($progress['paid'])->toEqual(500.0)
        ->and($progress['percent'])->toEqual(100.0)
        ->and($progress['status'])->toBe('paid');
});

/**
 * Rent owed is derived, never the raw column — the same rule the collection
 * page and the close preflight follow.
 */
it('measures a prorated move-in month against the prorated rent', function () {
    Carbon::setTestNow('2026-08-25');

    $admin = makeAdmin();
    auth()->login($admin);
    settings(['billing_cycle_day' => '2']); // account-scoped — needs its owner
    makeFiscalPeriod($admin, ['opening_date' => '2026-01-01', 'closing_date' => '2026-12-31']);

    $room = makeApartment(null, ['monthly_rent' => 300]);
    $tenant = makeTenant($room, ['move_in_date' => '2026-08-08']);
    $rental = makeRental($tenant, $room, [
        'rent_amount' => 300,
        'start_date' => '2026-08-08',
    ]);

    // Aug 8 → Sep 2 is 25 days of a $300 month.
    $due = app(BillingCycleService::class)->periodFor($rental, 8, 2026)->amount;
    expect($due)->toBe(241.94);

    payRent($rental->id, '2026-08-25', $due);

    $progress = app(TenantRentProgressCalculator::class)->map([$tenant])[$tenant->id];

    expect($progress['rent'])->toBe(241.94)
        ->and($progress['percent'])->toEqual(100.0)
        ->and($progress['status'])->toBe('paid');
});

/**
 * The move-in month's rent is not due until the collection day of the FOLLOWING
 * month, so an unpaid one is pending — never overdue.
 */
it('does not call a not-yet-due prorated month overdue', function () {
    Carbon::setTestNow('2026-08-25');

    $admin = makeAdmin();
    auth()->login($admin);
    settings(['billing_cycle_day' => '2']); // account-scoped — needs its owner
    makeFiscalPeriod($admin, ['opening_date' => '2026-01-01', 'closing_date' => '2026-12-31']);

    $room = makeApartment(null, ['monthly_rent' => 300]);
    $tenant = makeTenant($room, ['move_in_date' => '2026-08-08']);
    makeRental($tenant, $room, ['rent_amount' => 300, 'start_date' => '2026-08-08']);

    $progress = app(TenantRentProgressCalculator::class)->map([$tenant])[$tenant->id];

    expect($progress['status'])->not->toBe('overdue')
        ->and($progress['due_date']->toDateString())->toBe('2026-09-02');
});
