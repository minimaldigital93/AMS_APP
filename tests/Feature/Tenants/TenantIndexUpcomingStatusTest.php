<?php

use App\Services\Tenants\TenantRentProgressCalculator;
use Carbon\Carbon;

/**
 * A tenant whose tenancy only begins in a later month must surface as
 * "upcoming" on the tenant index rent-progress card — never "overdue". This
 * mirrors the guard the dashboard and rent-collection page already carry.
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
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

it('marks a next-month tenant as upcoming, not overdue, on the tenant index', function () {
    $tenant = makeTenant($this->apartment, ['status' => 'active']);
    makeRental($tenant, $this->apartment, [
        'rent_amount' => 500,
        'start_date' => '2026-08-01', // moves in next month
    ]);

    $map = app(TenantRentProgressCalculator::class)->map([$tenant->fresh()]);

    expect($map[$tenant->id]['status'])->toBe('upcoming');
});

it('still flags a genuinely unpaid current tenant as overdue on the tenant index', function () {
    $tenant = makeTenant($this->apartment, ['status' => 'active']);
    makeRental($tenant, $this->apartment, [
        'rent_amount' => 500,
        'start_date' => '2026-06-01', // started last month, nothing paid
    ]);

    $map = app(TenantRentProgressCalculator::class)->map([$tenant->fresh()]);

    expect($map[$tenant->id]['status'])->toBe('overdue');
});
