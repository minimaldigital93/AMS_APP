<?php

use App\Models\Accounts;

/**
 * 2026-07 audit F7: money dates must fall inside the active fiscal period's
 * range. Rows are stamped with the period id but every report windows on the
 * period's date range — an out-of-range row lands in the books yet shows in
 * no report.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => now()->startOfYear()->toDateString(),
        'closing_date' => now()->endOfYear()->toDateString(),
    ]);
    $this->apartment = makeApartment();
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment);
    auth()->logout();
});

it('rejects recording income dated outside the active period', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.revenue_expense.store_income'), [
            'rental_id' => $this->rental->id,
            'amount' => 100,
            'payment_method' => 'cash',
            'payment_type' => 'rent',
            'transaction_date' => now()->subYear()->toDateString(), // before the period opened
        ])
        ->assertSessionHasErrors('transaction_date');

    expect(Accounts::count())->toBe(0);
});

it('accepts income dated inside the active period', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.revenue_expense.store_income'), [
            'rental_id' => $this->rental->id,
            'amount' => 100,
            'payment_method' => 'cash',
            'payment_type' => 'rent',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    expect(Accounts::count())->toBe(1);
});
