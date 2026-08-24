<?php

use App\Models\Settings;
use Illuminate\Support\Carbon;

/**
 * The stay gauge's cycle follows the rent collection day.
 *
 * `Rentals::stayProgress()` feeds the floor-plan gauge (`x-stay-gauge`) and the
 * "Progress / days left" bar on both tenant index pages. It used to anchor the
 * cycle on each tenant's own move-in anniversary, which under a collection day
 * gave every tenant a different renewal date and an arc that restarted
 * mid-month: with a collection day of the 1st, a tenant who moved in on Jun 22
 * read 6% and "29 days left" on Aug 24 while his August rent was 74% through
 * and due in 8 days — and the gauge's amber/rose "renewal imminent" colour
 * fired on the wrong date for everyone.
 *
 * Every figure it returns describes the CURRENT rental month, the centre label
 * included: it is the day within that month ("24/31"), so it resets with the
 * arc every collection day. It is deliberately not the tenancy's running total
 * — a cumulative "3 mo" in a monthly gauge answers a different question.
 */
beforeEach(function () {
    seedRoles();
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
});

afterEach(function () {
    Carbon::setTestNow();
});

function stayOn(string $moveIn, string $today, ?string $endDate = null): array
{
    Carbon::setTestNow(Carbon::parse($today.' 09:00'));

    $tenant = makeTenant();
    $lease = makeRental($tenant, $tenant->apartment, [
        'start_date' => $moveIn,
        'end_date' => $endDate,
        'rent_amount' => 300,
    ]);

    return $lease->stayProgress();
}

it('runs the cycle collection day to collection day, not move-in anniversary', function () {
    Settings::set('billing_cycle_day', '1');

    // Aug 24: 23 of August's 31 days elapsed, 8 to go — regardless of move-in.
    $stay = stayOn('2026-06-22', '2026-08-24');

    expect($stay['next_renewal_label'])->toBe('Sep 1')
        ->and($stay['days_left'])->toBe(8)
        ->and($stay['cycle_percent'])->toBe(74);
});

it('gives every tenant the same renewal date whatever day they moved in', function () {
    Settings::set('billing_cycle_day', '1');

    $renewals = collect(['2026-06-22', '2026-06-29', '2026-07-27'])
        ->map(fn ($moveIn) => stayOn($moveIn, '2026-08-24'))
        ->map(fn ($stay) => [$stay['next_renewal_label'], $stay['days_left'], $stay['cycle_percent']]);

    expect($renewals->unique()->values()->all())->toBe([['Sep 1', 8, 74]]);
});

it('resets the cycle on the collection day', function () {
    Settings::set('billing_cycle_day', '1');

    $lastDay = stayOn('2026-06-22', '2026-08-31');
    $renewalDay = stayOn('2026-06-22', '2026-09-01');

    expect($lastDay['cycle_percent'])->toBe(97)
        ->and($lastDay['days_left'])->toBe(1)
        ->and($renewalDay['cycle_percent'])->toBe(0)
        ->and($renewalDay['days_left'])->toBe(30)
        ->and($renewalDay['next_renewal_label'])->toBe('Oct 1');
});

it('stays in last month\'s period on a day before the collection day', function () {
    // The regression the naive version hits: periodFor() returns the period
    // that STARTS in the given month, which on the 24th of a day-28 account is
    // still in the future — the tenant is living in Jul 28 → Aug 28.
    Settings::set('billing_cycle_day', '28');

    $stay = stayOn('2026-06-22', '2026-08-24');

    expect($stay['next_renewal_label'])->toBe('Aug 28')
        ->and($stay['days_left'])->toBe(4)
        ->and($stay['cycle_percent'])->toBe(87);
});

it('runs the short first cycle from move-in to the collection day', function () {
    Settings::set('billing_cycle_day', '1');

    // Aug 20 → Sep 1 is 12 days; 4 elapsed on Aug 24.
    $stay = stayOn('2026-08-20', '2026-08-24');

    expect($stay['next_renewal_label'])->toBe('Sep 1')
        ->and($stay['days_left'])->toBe(8)
        ->and($stay['cycle_percent'])->toBe(33);
});

it('ends the cycle at a real move-out date instead of renewing', function () {
    Settings::set('billing_cycle_day', '1');

    $stay = stayOn('2026-06-22', '2026-08-24', '2026-08-28');

    expect($stay['next_renewal_label'])->toBe('Aug 28')
        ->and($stay['days_left'])->toBe(4);
});

it('never reports negative progress for a lease that has not started', function () {
    Settings::set('billing_cycle_day', '1');

    $stay = stayOn('2026-10-05', '2026-08-24');

    expect($stay['cycle_percent'])->toBe(0)
        ->and($stay['days_left'])->toBeGreaterThanOrEqual(0)
        ->and($stay['next_renewal_label'])->toBe('Nov 1');
});

it('keeps the move-in anniversary cycle when no collection day is set', function () {
    // The backward-compatibility seam: a blank setting changes nothing.
    $stay = stayOn('2026-06-22', '2026-08-24');

    expect($stay['next_renewal_label'])->toBe('Sep 22')
        ->and($stay['days_left'])->toBe(29)
        ->and($stay['cycle_percent'])->toBe(6);
});

it('labels the day within the current rental month, not the length of the tenancy', function () {
    Settings::set('billing_cycle_day', '1');

    // Two months into the tenancy, but the gauge speaks only about August:
    // day 24 of the Aug 1 → Sep 1 cycle's 31.
    $stay = stayOn('2026-06-22', '2026-08-24');

    expect($stay['cycle_label'])->toBe('24/31')
        ->and($stay['cycle_day'])->toBe(24)
        ->and($stay['cycle_days'])->toBe(31);
});

it('measures the day against the short first cycle, not the calendar month', function () {
    Settings::set('billing_cycle_day', '1');

    // Aug 20 → Sep 1 is a 12-day first cycle; the 24th is day 5 of it.
    expect(stayOn('2026-08-20', '2026-08-24')['cycle_label'])->toBe('5/12');
});

it('counts the day from the collection day, not from the 1st', function () {
    // Day-28 account on Aug 24: the running cycle is Jul 28 → Aug 28, so this
    // is day 28 of 31 — nearly over — not day 24 of August.
    Settings::set('billing_cycle_day', '28');

    expect(stayOn('2026-06-22', '2026-08-24')['cycle_label'])->toBe('28/31');
});

it('restarts the day count at 1 on every collection day', function () {
    Settings::set('billing_cycle_day', '1');

    // The whole point: the label recounts with the arc instead of accumulating.
    expect(stayOn('2026-06-22', '2026-08-31')['cycle_label'])->toBe('31/31')
        ->and(stayOn('2026-06-22', '2026-09-01')['cycle_label'])->toBe('1/30');
});

it('never labels a day past the end of a cycle cut short by move-out', function () {
    Settings::set('billing_cycle_day', '1');

    // Cycle ends at the move-out date, so the count stops there too.
    $stay = stayOn('2026-06-22', '2026-08-24', '2026-08-28');

    expect($stay['cycle_label'])->toBe('24/27')
        ->and($stay['cycle_day'])->toBeLessThanOrEqual($stay['cycle_days']);
});

it('shows the same rental month on the floors list, the floor plan and the supervisor list', function () {
    // The three pages carried three implementations of this cycle until 2026-08
    // — the two lists derived their own from the tenant's move-in day and
    // ignored the collection day entirely.
    Settings::set('billing_cycle_day', '1');
    Carbon::setTestNow(Carbon::parse('2026-08-24 09:00'));

    $floor = makeFloor('Floor 1');
    $apartment = makeApartment($floor, ['status' => 'occupied']);
    $tenant = makeTenant($apartment, ['move_in_date' => '2026-06-22']);
    makeRental($tenant, $apartment, ['start_date' => '2026-06-22', 'rent_amount' => 300]);

    $supervisor = makeSupervisor(['account_id' => $this->admin->id]);
    $floor->property?->forceFill(['supervisor_id' => $supervisor->id])->save();

    // 23 of August's 31 days elapsed, 8 to go — day 24 of 31 on all three.
    $urls = [
        route('admin.floors.index'),
        route('admin.floors.plan3d'),
        route('supervisor.apartments.index'),
    ];

    foreach ($urls as $url) {
        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertSee('Day 24 of 31')
            ->assertSee('Sep 1');
    }

    // The floor plan prints it in the gauge centre as well as the tooltip.
    $this->actingAs($this->admin)->get(route('admin.floors.plan3d'))
        ->assertSee('24/31');
});

it('counts the day within the anniversary cycle when no collection day is set', function () {
    // Jun 22 → Aug 24 with no collection day: the cycle is Aug 22 → Sep 22, so
    // day 3 of 31 — the same question, just anchored on the move-in day.
    expect(stayOn('2026-06-22', '2026-08-24')['cycle_label'])->toBe('3/31');
});
