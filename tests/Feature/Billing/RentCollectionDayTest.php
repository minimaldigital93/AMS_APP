<?php

use App\Models\Settings;
use App\Services\Billing\BillingCycleService;

/**
 * The rent collection day, end to end.
 *
 * Two rules only: the move-in month is prorated up to the collection day of the
 * following month, and every month after that is the full rent. The suite also
 * pins the backward-compatibility contract — with no collection day set, rent
 * must derive exactly as it always has.
 */
beforeEach(function () {
    seedRoles();
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
    $this->cycles = app(BillingCycleService::class);
});

it('leaves rent on the tenant move-in day when no collection day is set', function () {
    $tenant = makeTenant();
    $lease = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-08-08',
        'rent_amount' => 300,
    ]);

    expect($this->cycles->collectionDay())->toBeNull()
        ->and($this->cycles->periodFor($lease, 8, 2026))->toBeNull()
        ->and($this->cycles->periodFor($lease, 9, 2026))->toBeNull();
});

it('prorates the move-in month up to the collection day of the next month', function () {
    Settings::set('billing_cycle_day', '2');

    $tenant = makeTenant();
    $lease = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-08-08',
        'rent_amount' => 300,
    ]);

    $first = $this->cycles->periodFor($lease, 8, 2026);

    // Aug 8 → Sep 2 is 25 of August's 31 days: 25 × ($300 ÷ 31).
    expect($first->start->toDateString())->toBe('2026-08-08')
        ->and($first->end->toDateString())->toBe('2026-09-02')
        ->and($first->days)->toBe(25)
        ->and($first->amount)->toBe(241.94)
        ->and($first->isProrated)->toBeTrue()
        ->and($first->dueDate->toDateString())->toBe('2026-09-02');
});

it('charges the full rent on the collection day every month after that', function (int $month, string $due, int $days) {
    Settings::set('billing_cycle_day', '2');

    $tenant = makeTenant();
    $lease = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-08-08',
        'rent_amount' => 300,
    ]);

    $period = $this->cycles->periodFor($lease, $month, 2026);

    expect($period->amount)->toBe(300.0)
        ->and($period->isProrated)->toBeFalse()
        ->and($period->days)->toBe($days)
        ->and($period->dueDate->toDateString())->toBe($due)
        ->and($period->start->toDateString())->toBe($due);
})->with([
    'september' => [9, '2026-09-02', 30],
    'october' => [10, '2026-10-02', 31],
    'november' => [11, '2026-11-02', 30],
    'december' => [12, '2026-12-02', 31],
]);

it('bills a whole month when the tenant moves in on the collection day', function () {
    Settings::set('billing_cycle_day', '2');

    $tenant = makeTenant();
    $lease = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-08-02',
        'rent_amount' => 300,
    ]);

    $first = $this->cycles->periodFor($lease, 8, 2026);

    expect($first->days)->toBe(31)
        ->and($first->amount)->toBe(300.0)
        ->and($first->isProrated)->toBeFalse();
});

it('starts exactly one period in each calendar month, whatever the collection day', function (int $day) {
    Settings::set('billing_cycle_day', (string) $day);

    $tenant = makeTenant();
    $lease = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-08-08',
        'rent_amount' => 300,
    ]);

    // This is what lets the month-navigated rent page keep working: walking the
    // months must produce a gapless, non-overlapping chain of periods.
    $cursor = $this->cycles->periodFor($lease, 8, 2026);
    expect($cursor->start->toDateString())->toBe('2026-08-08');

    foreach ([[9, 2026], [10, 2026], [11, 2026], [12, 2026], [1, 2027]] as [$m, $y]) {
        $next = $this->cycles->periodFor($lease, $m, $y);
        expect($next->start->toDateString())->toBe($cursor->end->toDateString())
            ->and($next->start->month)->toBe($m);
        $cursor = $next;
    }
})->with([
    'day 1' => [1],
    'day 2' => [2],
    'day 15' => [15],
    'day 28' => [28],
]);

it('crosses February and a leap February without losing a day', function () {
    Settings::set('billing_cycle_day', '2');

    $tenant = makeTenant();
    $lease = makeRental($tenant, $tenant->apartment, ['start_date' => '2026-01-02', 'rent_amount' => 300]);
    $leap = makeRental(makeTenant(), null, ['start_date' => '2028-01-02', 'rent_amount' => 300]);

    expect($this->cycles->periodFor($lease, 2, 2026)->days)->toBe(28)
        ->and($this->cycles->periodFor($lease, 2, 2026)->amount)->toBe(300.0)
        ->and($this->cycles->periodFor($leap, 2, 2028)->days)->toBe(29)
        ->and($this->cycles->periodFor($leap, 2, 2028)->amount)->toBe(300.0);
});

it('ignores a collection day outside 1–28', function (string $stored) {
    Settings::set('billing_cycle_day', $stored);

    expect($this->cycles->collectionDay())->toBeNull();
})->with(['zero' => ['0'], 'blank' => [''], 'the 29th' => ['29'], 'the 31st' => ['31']]);

it('defaults the overdue grace to the three days the contract has always promised', function () {
    expect($this->cycles->overdueDays())->toBe(BillingCycleService::DEFAULT_OVERDUE_DAYS)
        ->and(BillingCycleService::DEFAULT_OVERDUE_DAYS)->toBe(3);

    Settings::set('billing_overdue_days', '7');
    expect($this->cycles->overdueDays())->toBe(7);

    Settings::set('billing_overdue_days', '0');
    expect($this->cycles->overdueDays())->toBe(0);
});

it('carries the prorated move-in month into the tenant arrears', function () {
    Settings::set('billing_cycle_day', '2');

    $tenant = makeTenant();
    makeRental($tenant, $tenant->apartment, [
        'start_date' => now()->subMonth()->startOfMonth()->addDays(7)->toDateString(),
        'rent_amount' => 300,
    ]);

    $history = $tenant->fresh()->paymentHistory();
    $moveInMonth = $history->last();

    // The move-in month is a part month, so it must be below a full rent — and
    // the same figure the rent-collection page shows.
    expect($moveInMonth['rent_amount'])->toBeLessThan(300.0)
        ->and($moveInMonth['rent_amount'])->toBeGreaterThan(0.0)
        ->and($history->first()['rent_amount'])->toBe(300.0);
});

it('keeps arrears on whole calendar months when no collection day is set', function () {
    $tenant = makeTenant();
    makeRental($tenant, $tenant->apartment, [
        'start_date' => now()->subMonths(2)->startOfMonth()->addDays(7)->toDateString(),
        'rent_amount' => 500,
    ]);

    expect($tenant->fresh()->outstandingCharges()['rent_due'])->toBe(1500.0);
});
