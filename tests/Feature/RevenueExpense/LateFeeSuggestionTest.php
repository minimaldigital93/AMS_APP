<?php

use Carbon\Carbon;

/**
 * Late fee = percent of monthly rent per day overdue. The percentage is an
 * account setting (late_fee_percent) and the rent-collection page prefills the
 * checkout late-fee field with the computed suggestion.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-06-15');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    // Started well before the current month → this month's rent is due on the
    // 1st, so by the 15th it is overdue.
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 500,
        'start_date' => '2026-01-01',
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function billFor($response, $rentalId): ?array
{
    return collect($response->viewData('tenantBills')->items())
        ->firstWhere(fn ($b) => $b['rental']->id === $rentalId);
}

it('suggests a late fee of rent x percent x overdue days when the setting is on', function () {
    settings(['late_fee_percent' => '2']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income'));

    $response->assertOk();
    $bill = billFor($response, $this->rental->id);

    expect($bill)->not->toBeNull()
        ->and($bill['overdue_days'])->toBeGreaterThan(0)
        ->and($bill['late_fee_suggested'])
        ->toEqual(round(500 * (2 / 100) * $bill['overdue_days'], 2));
});

it('suggests no late fee when the percentage is unset or zero', function () {
    settings(['late_fee_percent' => '0']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income'));

    $bill = billFor($response, $this->rental->id);

    expect($bill['late_fee_suggested'])->toEqual(0.0);
});

it('rejects a late fee percentage above 100', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.updateBatch'), ['settings' => ['late_fee_percent' => '150']])
        ->assertSessionHasErrors('settings.late_fee_percent');
});

it('stores a valid late fee percentage', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.updateBatch'), ['settings' => ['late_fee_percent' => '3.5']])
        ->assertSessionHasNoErrors();

    expect(settings('late_fee_percent'))->toEqual('3.5');
});
