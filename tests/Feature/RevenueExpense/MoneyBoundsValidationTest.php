<?php

use App\Models\Accounts;

/**
 * 2026-07 validation audit I1/I2/I3: money inputs are bounded so an over-range
 * value is a validation error, not a decimal-overflow 500 (MySQL runs strict
 * mode); balance-sheet sub_type is validated against its enum; apartment
 * supervisor_id is scoped to same-account supervisors.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin);
    $this->apartment = makeApartment();
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment);
    auth()->logout();
});

it('rejects an income amount over the decimal(10,2) ceiling instead of 500ing', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.revenue_expense.store_income'), [
            'rental_id' => $this->rental->id,
            'amount' => 100000000, // 1 over the 99,999,999.99 ceiling
            'payment_method' => 'cash',
            'payment_type' => 'rent',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('amount');

    expect(Accounts::count())->toBe(0);
});

it('accepts an income amount at the ceiling', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.revenue_expense.store_income'), [
            'rental_id' => $this->rental->id,
            'amount' => 99999999.99,
            'payment_method' => 'cash',
            'payment_type' => 'rent',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertSessionHasNoErrors();
});

it('rejects a balance-sheet sub_type that does not match its item_type', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.fiscalperiod.storeBalanceItem', $this->period), [
            'item_type' => 'liability',
            'sub_type' => 'cash', // an asset sub_type under a liability
            'name' => 'Bad',
            'amount' => 100,
            'as_of_date' => $this->period->opening_date->toDateString(),
        ])
        ->assertSessionHasErrors('sub_type');
});

it('rejects a garbage balance-sheet sub_type', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.fiscalperiod.storeBalanceItem', $this->period), [
            'item_type' => 'asset',
            'sub_type' => 'definitely_not_an_enum_value',
            'name' => 'Bad',
            'amount' => 100,
            'as_of_date' => $this->period->opening_date->toDateString(),
        ])
        ->assertSessionHasErrors('sub_type');
});

it('rejects assigning another accounts user as an apartment supervisor', function () {
    $otherAdmin = makeAdmin();
    auth()->login($otherAdmin);
    $foreignSupervisor = makeSupervisor(['account_id' => $otherAdmin->id]);
    auth()->logout();

    $this->actingAs($this->admin)
        ->put(route('admin.apartments.update', $this->apartment), [
            'apartment_number' => $this->apartment->apartment_number,
            'floor_id' => $this->apartment->floor_id,
            'monthly_rent' => 300,
            'status' => 'available',
            'supervisor_id' => $foreignSupervisor->id,
        ])
        ->assertSessionHasErrors('supervisor_id');
});
