<?php

use App\Models\Utilities;
use Carbon\Carbon;

/**
 * An "Upcoming" bill has nothing to charge.
 *
 * The rent collection page badges a row Upcoming in two cases — the tenancy has
 * not begun by the end of the month on screen, or the whole month is still
 * ahead — and in both there is no meter to read and no charge to raise. The
 * badge and the affordance have to agree, so the Add-charge button is absent
 * from such a row ($bill['billable']) and addTenantCharge() refuses the post a
 * stale tab could still make.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-08');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);

    // A sitting tenant, and a room whose next tenant only moves in next month.
    $this->apartment = makeApartment(null, ['monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment, ['move_in_date' => '2026-06-10']);
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 500,
        'start_date' => '2026-06-10',
    ]);

    $this->futureApartment = makeApartment(null, ['monthly_rent' => 400]);
    $this->futureTenant = makeTenant($this->futureApartment, ['move_in_date' => '2026-10-01']);
    $this->futureRental = makeRental($this->futureTenant, $this->futureApartment, [
        'rent_amount' => 400,
        'start_date' => '2026-10-01',
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

function billsFor(int $month, int $year): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => $month, 'year' => $year]));
}

function billRow(\Illuminate\Testing\TestResponse $response, int $rentalId): array
{
    return collect($response->viewData('tenantBills')->items())
        ->firstWhere(fn ($b) => $b['rental']->id === $rentalId);
}

it('marks a not-yet-started tenancy unbillable while the sitting tenant stays billable', function () {
    $response = billsFor(9, 2026);

    expect(billRow($response, $this->futureRental->id)['is_upcoming'])->toBeTrue()
        ->and(billRow($response, $this->futureRental->id)['billable'])->toBeFalse()
        // Unchanged for everyone else on the page.
        ->and(billRow($response, $this->rental->id)['is_upcoming'])->toBeFalse()
        ->and(billRow($response, $this->rental->id)['billable'])->toBeTrue();
});

it('marks every row of a future month unbillable', function () {
    // October, viewed in September: the badge reads Upcoming for the sitting
    // tenant too, and no meter has been read for a month nobody has lived.
    $response = billsFor(10, 2026);

    expect($response->viewData('isFutureMonth'))->toBeTrue()
        ->and(billRow($response, $this->rental->id)['billable'])->toBeFalse();
});

it('refuses a charge posted against a not-yet-started tenancy', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.revenue_expense.add_charge'), [
            'rental_id' => $this->futureRental->id,
            'charge_type' => 'internet',
            'charge_amount' => 15,
            'billing_month' => 9,
            'billing_year' => 2026,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', __('messages.flash_charge_tenancy_upcoming', [
            'name' => $this->futureTenant->name,
        ]));

    expect(Utilities::where('rental_id', $this->futureRental->id)->count())->toBe(0);
});

it('refuses a charge posted against a month that has not arrived', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.revenue_expense.add_charge'), [
            'rental_id' => $this->rental->id,
            'charge_type' => 'internet',
            'charge_amount' => 15,
            'billing_month' => 10,
            'billing_year' => 2026,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', __('messages.flash_charge_month_upcoming'));

    expect(Utilities::where('rental_id', $this->rental->id)->count())->toBe(0);
});

it('still accepts a charge on the running month and on a past one', function () {
    foreach ([[9, 2026], [8, 2026]] as [$month, $year]) {
        $this->actingAs($this->admin)
            ->postJson(route('admin.revenue_expense.add_charge'), [
                'rental_id' => $this->rental->id,
                'charge_type' => 'internet',
                'charge_amount' => 15,
                'billing_month' => $month,
                'billing_year' => $year,
            ])
            ->assertOk();
    }

    expect(Utilities::where('rental_id', $this->rental->id)->count())->toBe(2);
});
