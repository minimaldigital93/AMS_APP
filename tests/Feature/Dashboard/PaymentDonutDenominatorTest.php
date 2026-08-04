<?php

use App\Services\Dashboard\DashboardStatsService;
use Carbon\Carbon;

/**
 * The three payment donuts (paid/pending/overdue) print "N / total". That
 * total must be the number of bills classified for the reference month —
 * never the room count. Occupancy is *today's* state while the counts are the
 * viewed month's, so a mid-month move-out (room back to available), two
 * tenancies on one room in a month, or a room mothballed after it was let all
 * pushed a count above the denominator and printed "29 / 28" on the server.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-07-27');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
});

afterEach(function () {
    auth()->logout();
    Carbon::setTestNow();
});

function julyStats(int $adminId, ?int $periodId): array
{
    return (new DashboardStatsService($adminId, null, null, $periodId))
        ->build(
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
            Carbon::parse('2026-07-27')
        );
}

it('counts bills, not rooms, so a mid-month move-out cannot exceed the denominator', function () {
    // Room let all year, tenant moved out on the 15th → room is available now.
    $apartment = makeApartment(null, ['monthly_rent' => 500, 'status' => 'available']);
    $leaver = makeTenant($apartment, ['status' => 'inactive']);
    makeRental($leaver, $apartment, [
        'start_date' => '2026-01-01',
        'end_date' => '2026-07-15',
        'rent_amount' => 500,
    ]);

    // A second room still occupied, so the room count is 1 while July has 2 bills.
    $sitting = makeApartment(null, ['monthly_rent' => 500, 'status' => 'occupied']);
    makeRental(makeTenant($sitting), $sitting, [
        'start_date' => '2026-01-01',
        'rent_amount' => 500,
    ]);

    $stats = julyStats($this->admin->id, $this->period->id);
    $payments = $stats['payments'];

    expect($stats['apartments']['occupied'])->toBe(1)
        ->and($payments['bills_total'])->toBe(2)
        ->and($payments['paid'] + $payments['pending'] + $payments['overdue'])
        ->toBe($payments['bills_total']);
});

it('keeps a room mothballed after it was let inside the denominator', function () {
    $apartment = makeApartment(null, [
        'monthly_rent' => 500,
        'status' => 'available',
        'under_maintenance' => true,
    ]);
    makeRental(makeTenant($apartment, ['status' => 'inactive']), $apartment, [
        'start_date' => '2026-01-01',
        'end_date' => '2026-07-20',
        'rent_amount' => 500,
    ]);

    $payments = julyStats($this->admin->id, $this->period->id)['payments'];

    expect($payments['bills_total'])->toBe(1)
        ->and($payments['paid'] + $payments['pending'] + $payments['overdue'])->toBe(1);
});

it('bills a room turned over mid-month once, not once per tenancy', function () {
    // The room is single-occupancy, but the outgoing and incoming tenancies
    // overlap: the leaver's end_date is the 10th and the room was reassigned on
    // the 12th. The rent collection page bills the room once; so must the tile.
    $apartment = makeApartment(null, ['monthly_rent' => 500, 'status' => 'occupied']);

    makeRental(makeTenant($apartment, ['status' => 'inactive']), $apartment, [
        'start_date' => '2026-01-01',
        'end_date' => '2026-07-10',
        'rent_amount' => 500,
    ]);
    makeRental(makeTenant($apartment), $apartment, [
        'start_date' => '2026-07-12',
        'rent_amount' => 500,
    ]);

    $payments = julyStats($this->admin->id, $this->period->id)['payments'];

    expect($payments['bills_total'])->toBe(1)
        ->and($payments['paid'] + $payments['pending'] + $payments['overdue'])->toBe(1);
});

it('still bills a room whose next tenancy has not begun yet', function () {
    // Leaver gone in June, replacement moves in next month: the newest tenancy
    // has not started, so the room falls back to the one that has.
    $apartment = makeApartment(null, ['monthly_rent' => 500, 'status' => 'available']);
    makeRental(makeTenant($apartment, ['status' => 'inactive']), $apartment, [
        'start_date' => '2026-01-01',
        'end_date' => '2026-07-05',
        'rent_amount' => 500,
    ]);
    makeRental(makeTenant($apartment), $apartment, [
        'start_date' => '2026-08-01',
        'rent_amount' => 500,
    ]);

    expect(julyStats($this->admin->id, $this->period->id)['payments']['bills_total'])->toBe(1);
});

it('reports a zero denominator when no tenancy is billable in the month', function () {
    $apartment = makeApartment(null, ['monthly_rent' => 500, 'status' => 'available']);
    makeRental(makeTenant($apartment), $apartment, [
        'start_date' => '2026-09-01', // moves in later
        'rent_amount' => 500,
    ]);

    expect(julyStats($this->admin->id, $this->period->id)['payments']['bills_total'])->toBe(0);
});
