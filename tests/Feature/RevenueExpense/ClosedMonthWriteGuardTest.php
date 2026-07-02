<?php

use App\Models\MonthlyPeriod;
use App\Models\Payments;

/**
 * A closed monthly period's totals and carried-forward balance are frozen at
 * close. Every money-write endpoint validates its date with NotInClosedMonth so
 * a backdated entry can't silently desync the frozen figures from the ledger —
 * the month must be reopened first.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);

    $this->period = makeFiscalPeriod($this->admin);

    $lastMonth = now()->subMonthNoOverflow();
    $this->closedMonth = MonthlyPeriod::create([
        'fiscal_period_id' => $this->period->id,
        'user_id' => $this->admin->id,
        'name' => $lastMonth->format('F Y'),
        'month_number' => $lastMonth->month,
        'year' => $lastMonth->year,
        'start_date' => $lastMonth->copy()->startOfMonth()->toDateString(),
        'end_date' => $lastMonth->copy()->endOfMonth()->toDateString(),
        'opening_balance' => 0,
        'closing_balance' => 0,
        'total_income' => 0,
        'total_expenses' => 0,
        'net_income' => 0,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    $apartment = makeApartment();
    $this->rental = makeRental(makeTenant($apartment), $apartment);
});

function incomePayload(int $rentalId, string $date): array
{
    return [
        'rental_id' => $rentalId,
        'amount' => 500,
        'payment_method' => 'cash',
        'payment_type' => 'rent',
        'transaction_date' => $date,
    ];
}

it('rejects income backdated into a closed monthly period', function () {
    $this->post(route('admin.revenue_expense.store_income'), incomePayload(
        $this->rental->id,
        now()->subMonthNoOverflow()->startOfMonth()->addDays(4)->toDateString(),
    ))->assertSessionHasErrors('transaction_date');

    expect(Payments::count())->toBe(0);
});

it('accepts income dated in an open month', function () {
    $this->post(route('admin.revenue_expense.store_income'), incomePayload(
        $this->rental->id,
        now()->toDateString(),
    ))->assertSessionHas('success');

    expect(Payments::count())->toBe(1);
});

it('rejects an other-expense dated inside a closed month', function () {
    $this->post(route('admin.revenue_expense.store_other_expense'), [
        'category' => 'maintenance',
        'description' => 'Backdated repair',
        'amount' => 100,
        'transaction_date' => now()->subMonthNoOverflow()->startOfMonth()->addDays(4)->toDateString(),
    ])->assertSessionHasErrors('transaction_date');
});
