<?php

use Carbon\Carbon;

/**
 * Tenant turnover on the Record Income page.
 *
 * A room is single-occupancy and gets exactly one bill row per month. When a
 * tenant leaves with a move-out date later in the month, the room is freed
 * immediately and a new tenant can be assigned while the outgoing rental's
 * end_date is still in the future — both tenancies overlap. The page must bill
 * only the current occupant, or the room shows up twice with its rent counted
 * twice (and the departed tenant carries a phantom overdue bill).
 */
beforeEach(function () {
    Carbon::setTestNow('2026-07-15 10:00:00');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 500]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

function billsForApartment($response, $apartmentId)
{
    return collect($response->viewData('tenantBills')->items())
        ->filter(fn ($b) => $b['apartment']->id === $apartmentId)
        ->values();
}

function recordIncomeFor($test, int $month, int $year)
{
    return $test->actingAs($test->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => $month, 'year' => $year]));
}

it('bills only the incoming tenant when the outgoing move-out date is still ahead', function () {
    auth()->login($this->admin);
    $outgoing = makeTenant($this->apartment, ['name' => 'Outgoing']);
    makeRental($outgoing, $this->apartment, [
        'start_date' => '2026-04-01',
        'end_date' => '2026-07-31',
    ]);
    // The leave flow soft-deletes the tenant — the stale row rendered with no name.
    $outgoing->delete();

    $incoming = makeTenant($this->apartment, ['name' => 'Incoming']);
    makeRental($incoming, $this->apartment, [
        'start_date' => '2026-07-16',
        'end_date' => null,
    ]);
    auth()->logout();

    $response = recordIncomeFor($this, 7, 2026);
    $response->assertOk();

    $bills = billsForApartment($response, $this->apartment->id);

    expect($bills)->toHaveCount(1)
        ->and($bills[0]['tenant']->name)->toBe('Incoming')
        ->and($bills[0]['status'])->not->toBe('overdue');

    // One 500 room expects 500, not 1000, and carries no phantom overdue.
    expect($response->viewData('totalRentExpected'))->toBe(500.0)
        ->and($response->viewData('overdueCount'))->toBe(0);
});

it('keeps the departed tenant on the months they actually lived through', function () {
    auth()->login($this->admin);
    $outgoing = makeTenant($this->apartment, ['name' => 'Outgoing']);
    $oldRental = makeRental($outgoing, $this->apartment, [
        'start_date' => '2026-04-01',
        'end_date' => '2026-07-31',
    ]);
    auth()->logout();

    // June — squarely inside their tenancy. Anchoring the rental filter to
    // now() used to drop this row entirely once the move-out date passed.
    $bills = billsForApartment(recordIncomeFor($this, 6, 2026), $this->apartment->id);

    expect($bills)->toHaveCount(1)
        ->and($bills[0]['rental']->id)->toBe($oldRental->id);
});

it('drops a finished tenancy from the months after it ended', function () {
    auth()->login($this->admin);
    $outgoing = makeTenant($this->apartment, ['name' => 'Outgoing']);
    makeRental($outgoing, $this->apartment, [
        'start_date' => '2026-04-01',
        'end_date' => '2026-06-30',
    ]);
    auth()->logout();

    expect(billsForApartment(recordIncomeFor($this, 7, 2026), $this->apartment->id))->toBeEmpty();
});

it('still bills the sitting tenant when the next tenancy starts next month', function () {
    auth()->login($this->admin);
    $sitting = makeTenant($this->apartment, ['name' => 'Sitting']);
    makeRental($sitting, $this->apartment, [
        'start_date' => '2026-04-01',
        'end_date' => '2026-07-31',
    ]);

    $next = makeTenant($this->apartment, ['name' => 'Next']);
    makeRental($next, $this->apartment, [
        'start_date' => '2026-08-01',
        'end_date' => null,
    ]);
    auth()->logout();

    // July belongs to the sitting tenant, not the one who has not moved in.
    $july = billsForApartment(recordIncomeFor($this, 7, 2026), $this->apartment->id);
    expect($july)->toHaveCount(1)
        ->and($july[0]['tenant']->name)->toBe('Sitting');

    // August belongs to the new tenant, once the old tenancy has ended.
    $august = billsForApartment(recordIncomeFor($this, 8, 2026), $this->apartment->id);
    expect($august)->toHaveCount(1)
        ->and($august[0]['tenant']->name)->toBe('Next');
});
