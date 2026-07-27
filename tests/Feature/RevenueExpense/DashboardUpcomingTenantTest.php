<?php

use App\Services\Dashboard\DashboardStatsService;
use Carbon\Carbon;

/**
 * A tenant whose tenancy only begins in a later month must not be counted as
 * overdue on the dashboard. The monthly view already excludes them (their
 * start_date falls outside the month window); the full-period ("view=all")
 * window reaches the period close, so the status counter itself must skip
 * not-yet-started rentals rather than compute a bogus current-month due date.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-07-27');
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
        'start_date' => '2026-08-01', // moves in next month
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

it('does not flag a not-yet-started tenant as overdue in the full-period dashboard view', function () {
    $stats = (new DashboardStatsService($this->admin->id, null, null, $this->period->id))
        ->build(
            Carbon::parse('2026-01-01')->startOfDay(),
            Carbon::parse('2026-12-31')->endOfDay(),
            Carbon::parse('2026-07-27') // reference month = current month
        );

    expect($stats['payments']['overdue'])->toBe(0)
        ->and($stats['payments']['total_pending'])->toBe(0.0);
});

it('does not count a future tenant as overdue in the current-month view either', function () {
    $stats = (new DashboardStatsService($this->admin->id, null, null, $this->period->id))
        ->build(
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
            Carbon::parse('2026-07-27')
        );

    expect($stats['payments']['overdue'])->toBe(0);
});
