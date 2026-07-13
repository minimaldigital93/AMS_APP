<?php

use App\Services\TenantLeaveCalculator;
use Carbon\Carbon;

/**
 * Move-out pro-rata rent (2026-07 accounting audit, E1): the settlement must
 * charge only the FINAL unbilled month — earlier months were already billed by
 * the normal monthly rent flow. The pre-fix formula multiplied the WHOLE
 * tenancy's days by rent/30, overcharging every move-out after month one
 * (a 14-month tenant leaving mid-month was billed ~14.5 months of rent again).
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    $this->calculator = new TenantLeaveCalculator;
});

it('prorates only the final month for a long tenancy', function () {
    $room = makeApartment(null, ['monthly_rent' => 300]);
    $tenant = makeTenant($room, ['move_in_date' => '2025-01-10']);
    $rental = makeRental($tenant, $room, ['start_date' => '2025-01-10', 'rent_amount' => 300]);

    // 14+ months in, leaving on the 13th → 13 days × (300/30) = 130, NOT ~4,350.
    $proRata = $this->calculator->calculateProRataRent($rental, Carbon::parse('2026-03-13'));

    expect($proRata)->toEqual(130.0);
});

it('charges days since move-in when the tenant leaves within the first month', function () {
    $room = makeApartment(null, ['monthly_rent' => 300]);
    $tenant = makeTenant($room, ['move_in_date' => '2026-07-05']);
    $rental = makeRental($tenant, $room, ['start_date' => '2026-07-05', 'rent_amount' => 300]);

    // Moved in on the 5th, leaves on the 13th → 9 days inclusive → 90.
    $proRata = $this->calculator->calculateProRataRent($rental, Carbon::parse('2026-07-13'));

    expect($proRata)->toEqual(90.0);
});

it('caps a full 31-day month at one month of rent', function () {
    $room = makeApartment(null, ['monthly_rent' => 300]);
    $tenant = makeTenant($room, ['move_in_date' => '2025-06-01']);
    $rental = makeRental($tenant, $room, ['start_date' => '2025-06-01', 'rent_amount' => 300]);

    // Leaving July 31st: 31 days in the month, capped at 30/30 → exactly one rent.
    $proRata = $this->calculator->calculateProRataRent($rental, Carbon::parse('2026-07-31'));

    expect($proRata)->toEqual(300.0);
});

it('still reports total tenancy length via stay days', function () {
    $room = makeApartment(null, ['monthly_rent' => 300]);
    $tenant = makeTenant($room, ['move_in_date' => '2026-06-01']);
    $rental = makeRental($tenant, $room, ['start_date' => '2026-06-01', 'rent_amount' => 300]);

    // stay_days is record-keeping (tenancy length), not a billing input.
    expect($this->calculator->calculateStayDays($rental, Carbon::parse('2026-07-13')))->toBe(43);
});
