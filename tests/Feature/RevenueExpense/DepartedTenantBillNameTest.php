<?php

use Carbon\Carbon;

/**
 * A moved-out tenant is soft-deleted, but their rental still owns the months
 * they lived in — including the month they leave, until the next tenancy
 * begins. The rent collection page must still name them on that row rather
 * than printing "N/A" beside a room the tenant list shows as let to someone
 * else.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-27');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);

    $this->apartment = makeApartment(null, ['monthly_rent' => 500]);

    // Outgoing tenant: leaves on the 30th, already processed (soft-deleted).
    $this->departed = makeTenant($this->apartment, ['name' => 'Sok Dara', 'move_in_date' => '2026-03-01']);
    $this->departedRental = makeRental($this->departed, $this->apartment, [
        'rent_amount' => 500,
        'start_date' => '2026-03-01',
        'end_date' => '2026-09-30',
    ]);
    $this->departed->delete();

    // Incoming tenant: already on the active list, moves in next month.
    $this->incoming = makeTenant($this->apartment, ['name' => 'Chan Vanna', 'move_in_date' => '2026-10-01']);
    makeRental($this->incoming, $this->apartment, [
        'rent_amount' => 500,
        'start_date' => '2026-10-01',
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

it('names the departed tenant on the month they leave', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 9, 'year' => 2026]));

    $row = collect($response->viewData('tenantBills')->items())
        ->firstWhere(fn ($b) => $b['rental']->id === $this->departedRental->id);

    expect($row['tenant']?->name)->toBe('Sok Dara');
    $response->assertSee('Sok Dara');
});

it('names the departed tenant on their printed bill and receipt', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.print_bill', ['rental' => $this->departedRental->id, 'month' => 9, 'year' => 2026]))
        ->assertOk()
        ->assertSee('Sok Dara');

    $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.print_receipt', ['rental' => $this->departedRental->id, 'month' => 9, 'year' => 2026]))
        ->assertOk()
        ->assertSee('Sok Dara');
});
