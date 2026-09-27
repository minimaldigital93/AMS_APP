<?php

use App\Models\Rentals;
use Carbon\Carbon;

/**
 * A room change opens the new rental at the tenant's ORIGINAL move-in date, so
 * the tenant moving in can "start" before the tenant they replaced. The rent
 * collection page must still name the tenant actually in the room — the one
 * the tenant list shows — not whoever has the newest start_date.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-27');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    makeFiscalPeriod($this->admin, ['opening_date' => '2026-01-01', 'closing_date' => '2026-12-31']);

    $this->room = makeApartment(null, ['apartment_number' => '401', 'monthly_rent' => 500]);

    // Previous occupant: in 401 since May, moved out Sep 15 (archived).
    $this->departed = makeTenant($this->room, ['name' => 'Sok Dara', 'move_in_date' => '2026-05-01']);
    $this->departedRental = makeRental($this->departed, $this->room, [
        'rent_amount' => 500, 'start_date' => '2026-05-01', 'end_date' => '2026-09-15',
    ]);
    $this->departed->delete();

    // Current occupant: moved into 401 from another room on Sep 16; the new
    // rental carries their original 2025 move-in date.
    $this->current = makeTenant($this->room, ['name' => 'Chan Vanna', 'move_in_date' => '2025-01-01']);
    $this->currentRental = makeRental($this->current, $this->room, [
        'rent_amount' => 500, 'start_date' => '2025-01-01',
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

function occupantRow($test, int $month)
{
    $response = $test->actingAs($test->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => $month, 'year' => 2026]));

    return collect($response->viewData('tenantBills')->items())
        ->filter(fn ($b) => $b['rental']->apartment_id === $test->room->id)
        ->values();
}

it('names the tenant who is in the room now, not the one who left', function () {
    $rows = occupantRow($this, 9);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['tenant']->name)->toBe('Chan Vanna');
});

it('still names the previous tenant for the months they lived there', function () {
    $rows = occupantRow($this, 6);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['tenant']->name)->toBe('Sok Dara');
});

it('treats a rental left open for an archived tenant as ended when they were archived', function () {
    auth()->login($this->admin);
    $this->departedRental->update(['end_date' => null]);
    auth()->logout();

    expect(occupantRow($this, 10)->first()['tenant']->name)->toBe('Chan Vanna');
});

it('picks the same occupant for the dashboard tiles and break-even', function () {
    auth()->login($this->admin);
    $rentals = Rentals::with(['tenant' => fn ($q) => $q->withTrashed()])->get();

    expect(Rentals::occupantFor($rentals, Carbon::parse('2026-09-30')->endOfDay())->id)->toBe($this->currentRental->id)
        ->and(Rentals::occupantFor($rentals, Carbon::parse('2026-06-30')->endOfDay())->id)->toBe($this->departedRental->id);
});
