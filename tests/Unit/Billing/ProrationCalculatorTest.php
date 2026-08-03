<?php

use App\Services\Billing\ProrationCalculator;
use Carbon\Carbon;

/**
 * Partial-period rent maths. Pure, so the whole month-length matrix is cheap to
 * cover exhaustively — which is the point: February, the 30/31-day months and
 * leap years are exactly where prorated rent silently goes wrong.
 */
beforeEach(function () {
    $this->calc = new ProrationCalculator;
});

it('counts a half-open period, so back-to-back cycles never double-charge the boundary day', function () {
    // Aug 8 → Sep 2 is the worked example from the feature spec.
    expect($this->calc->daysBetween(Carbon::parse('2026-08-08'), Carbon::parse('2026-09-02')))->toBe(25);

    // The shared boundary day belongs to the LATER period only.
    $first = $this->calc->daysBetween(Carbon::parse('2026-09-02'), Carbon::parse('2026-10-02'));
    $second = $this->calc->daysBetween(Carbon::parse('2026-10-02'), Carbon::parse('2026-11-02'));
    expect($first + $second)->toBe(61) // Sep 2 → Nov 2 = 30 + 31
        ->and($this->calc->daysBetween(Carbon::parse('2026-09-02'), Carbon::parse('2026-11-02')))->toBe(61);
});

it('never returns a negative or backwards day count', function () {
    expect($this->calc->daysBetween(Carbon::parse('2026-09-10'), Carbon::parse('2026-09-02')))->toBe(0)
        ->and($this->calc->daysBetween(Carbon::parse('2026-09-02'), Carbon::parse('2026-09-02')))->toBe(0);
});

it('prorates the spec example to the cent', function () {
    // $300/mo, moved in Aug 8, collection day 2 → 25 of August's 31 days.
    expect($this->calc->prorate(300, Carbon::parse('2026-08-08'), Carbon::parse('2026-09-02')))
        ->toBe(241.94);
});

it('bills exactly one month of rent for a full cycle in any month length', function (string $start, string $end) {
    expect($this->calc->prorate(300, Carbon::parse($start), Carbon::parse($end)))->toBe(300.0);
})->with([
    'february (28 days)' => ['2026-02-02', '2026-03-02'],
    'february (29 days, leap)' => ['2028-02-02', '2028-03-02'],
    'april (30 days)' => ['2026-04-02', '2026-05-02'],
    'may (31 days)' => ['2026-05-02', '2026-06-02'],
    'december → january' => ['2026-12-02', '2027-01-02'],
]);

it('spreads rent across the real length of the month the period starts in', function () {
    // February is short, so a February day is worth more than a March day.
    expect($this->calc->dailyRate(280, Carbon::parse('2026-02-05')))->toBe(10.0)   // 280 / 28
        ->and($this->calc->dailyRate(280, Carbon::parse('2028-02-05')))->toBe(280 / 29) // leap
        ->and($this->calc->dailyRate(310, Carbon::parse('2026-03-05')))->toBe(10.0);  // 310 / 31
});

it('treats a leap day as a real billable day', function () {
    // Feb 28 → Mar 1 in 2028 spans Feb 28 AND Feb 29 = 2 days.
    expect($this->calc->daysBetween(Carbon::parse('2028-02-28'), Carbon::parse('2028-03-01')))->toBe(2)
        // Same dates in a non-leap year = 1 day.
        ->and($this->calc->daysBetween(Carbon::parse('2026-02-28'), Carbon::parse('2026-03-01')))->toBe(1);
});

it('returns zero rather than dividing by zero for a zero rent or empty period', function () {
    expect($this->calc->prorate(0, Carbon::parse('2026-08-08'), Carbon::parse('2026-09-02')))->toBe(0.0)
        ->and($this->calc->prorate(300, Carbon::parse('2026-08-08'), Carbon::parse('2026-08-08')))->toBe(0.0)
        ->and($this->calc->dailyRate(0, Carbon::parse('2026-08-08')))->toBe(0.0);
});
