<?php

use App\Models\Rentals;
use App\Services\NotificationService;
use Carbon\Carbon;

/**
 * Fixed lease term (3/6/12 months) chosen on the assign-tenant form: it is
 * validated, stored on the rental, drives the contract-overdue helpers, and
 * surfaces an overdue notification to staff once the term has lapsed.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin);
    $this->vacant = makeApartment(null, ['apartment_number' => 'T-101', 'status' => 'available', 'monthly_rent' => 400]);
    auth()->logout();
});

it('stores the chosen contract term on the new lease', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), [
            'tenant_option' => 'new',
            'name' => 'Term Tenant',
            'phone' => '0961112223',
            'move_in_date' => now()->toDateString(),
            'deposit' => 100,
            'contract_term_months' => 6,
        ])
        ->assertRedirect(route('admin.floors.index'));

    expect(Rentals::where('apartment_id', $this->vacant->id)->sole()->contract_term_months)->toBe(6);
});

it('rejects a contract term that is not 3, 6 or 12', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), [
            'tenant_option' => 'new',
            'name' => 'Bad Term',
            'phone' => '0961112224',
            'move_in_date' => now()->toDateString(),
            'deposit' => 100,
            'contract_term_months' => 5,
        ])
        ->assertSessionHasErrors('contract_term_months');
});

it('leaves the term null when none is chosen (open-ended tenancy)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), [
            'tenant_option' => 'new',
            'name' => 'Open Ended',
            'phone' => '0961112225',
            'move_in_date' => now()->toDateString(),
            'deposit' => 0,
        ])
        ->assertRedirect(route('admin.floors.index'));

    expect(Rentals::where('apartment_id', $this->vacant->id)->sole()->contract_term_months)->toBeNull();
});

it('computes the contract end date and overdue state', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-22'));

    $rental = new Rentals;
    $rental->start_date = Carbon::parse('2026-01-22');
    $rental->contract_term_months = 3;
    $rental->end_date = null;

    expect($rental->contractEndDate()->toDateString())->toBe('2026-04-22')
        ->and($rental->contractIsOverdue())->toBeTrue()
        ->and($rental->contractMonthsOverdue())->toBe(3);

    // An open-ended lease is never overdue.
    $open = new Rentals;
    $open->start_date = Carbon::parse('2020-01-01');
    $open->contract_term_months = null;
    expect($open->contractIsOverdue())->toBeFalse();

    Carbon::setTestNow();
});

it('renews a live fixed term by adding months on from the current end date', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-22'));
    auth()->login($this->admin);

    // A live 6-month lease (start 2026-01-22 → ends 2026-07-22) renewed by 6
    // extends the total term to 12 months from start.
    $tenant = makeTenant($this->vacant, ['phone' => '0961113330']);
    $rental = makeRental($tenant, $this->vacant);
    $rental->update(['start_date' => Carbon::parse('2026-01-22'), 'contract_term_months' => 6, 'end_date' => null]);

    expect($rental->renewTerm(6))->toBe(12)
        ->and($rental->fresh()->contract_term_months)->toBe(12)
        ->and($rental->contractEndDate()->toDateString())->toBe('2027-01-22');

    auth()->logout();
    Carbon::setTestNow();
});

it('renews a lapsed contract from today, not from the stale end date', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-22'));
    auth()->login($this->admin);

    // Started a year ago on a 3-month term → long overdue. Renewing by 3 pushes
    // the end to ~today + 3 (2026-10-22), never start + 6.
    $tenant = makeTenant($this->vacant, ['phone' => '0961113331']);
    $rental = makeRental($tenant, $this->vacant);
    $rental->update(['start_date' => Carbon::parse('2025-07-22'), 'contract_term_months' => 3, 'end_date' => null]);

    expect($rental->renewTerm(3))->toBe(15) // 2025-07-22 → 2026-10-22 = 15 months
        ->and($rental->contractEndDate()->toDateString())->toBe('2026-10-22')
        ->and($rental->contractIsOverdue())->toBeFalse();

    auth()->logout();
    Carbon::setTestNow();
});

it('surfaces an overdue-contract notification to staff', function () {
    auth()->login($this->admin);
    $tenant = makeTenant($this->vacant, ['phone' => '0961119999']);
    $rental = makeRental($tenant, $this->vacant);
    $rental->update(['start_date' => now()->subMonths(5), 'contract_term_months' => 3, 'end_date' => null]);
    auth()->logout();

    $feed = app(NotificationService::class)->for($this->admin->fresh());

    expect($feed->pluck('type'))->toContain('contract_overdue');
});
