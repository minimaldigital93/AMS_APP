<?php

use App\Models\Accounts;
use App\Models\MonthlyPeriod;
use App\Models\Payments;
use App\Models\Utilities;
use App\Services\RevenueExpense\IncomeRecordingService;
use Carbon\Carbon;

/**
 * A payment recorded by mistake can be reversed from the tenant's payment
 * history. Nothing about rent is stored as an invoice, so undoing the payment
 * walks every derived status backwards on its own:
 *
 *   Paid  --(reverse the charges payment)-->  Rent Paid  --(reverse the rent)-->  Pending
 *
 * Closing the month is the deadline and nothing else is: a payment stays
 * reversible for as long as the month it was booked in is open — including
 * after the calendar has rolled on — and is refused once that month, or its
 * fiscal period, has been closed.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-07-25');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 500,
        'start_date' => '2026-05-10',
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

/** Bill July's electricity, then check out rent + charges together. */
function payJulyBill(float $charge = 40.0): void
{
    Utilities::create([
        'tenant_id' => test()->tenant->id,
        'rental_id' => test()->rental->id,
        'utility_type' => 'electricity',
        'meter_reading_in' => 0,
        'meter_reading_out' => 40,
        'charge_amount' => $charge,
        'billing_month' => 7,
        'billing_year' => 2026,
        'paid_status' => false,
        'paid_at' => null,
    ]);

    auth()->login(test()->admin);
    (new IncomeRecordingService(userId: test()->admin->id, period: test()->period))
        ->checkout(test()->rental, [
            'payment_date' => '2026-07-25',
            'payment_method' => 'cash',
            'rent_amount' => 500,
            'pay_rent' => true,
            'pay_utilities' => true,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);
    auth()->logout();
}

/** Close July's monthly period — the deadline a reversal has to beat. */
function closeJuly(): void
{
    MonthlyPeriod::create([
        'fiscal_period_id' => test()->period->id,
        'user_id' => test()->admin->id,
        'name' => 'July 2026',
        'month_number' => 7,
        'year' => 2026,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'opening_balance' => 0,
        'closing_balance' => 0,
        'status' => 'closed',
    ]);
}

function julyRow(): array
{
    $response = test()->actingAs(test()->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 7, 'year' => 2026]));

    return collect($response->viewData('tenantBills')->items())
        ->firstWhere(fn ($b) => $b['rental']->id === test()->rental->id);
}

it('walks a paid bill back to rent-paid and then to pending as each payment is reversed', function () {
    payJulyBill();

    expect(julyRow()['status'])->toBe('paid');

    $charges = Payments::where('rental_id', $this->rental->id)->where('payment_type', 'utilities')->firstOrFail();
    $this->actingAs($this->admin)
        ->delete(route('admin.revenue_expense.reverse_payment', $charges))
        ->assertSessionHas('success');

    // Charges side re-opened; rent still in → pending bucket, "Rent Paid" label.
    $row = julyRow();
    expect($row['status'])->toBe('pending')
        ->and($row['rent_status'])->toBe('paid')
        ->and($row['charges_status'])->toBe('pending');
    expect(Utilities::where('rental_id', $this->rental->id)->first())
        ->paid_status->toBeFalse()
        ->paid_at->toBeNull();

    $rent = Payments::where('rental_id', $this->rental->id)->where('payment_type', 'rent')->firstOrFail();
    $this->actingAs($this->admin)
        ->delete(route('admin.revenue_expense.reverse_payment', $rent))
        ->assertSessionHas('success');

    // Rent unpaid again, past its due day → the overdue end of the same
    // pending-side vocabulary. Either way, no longer paid.
    $row = julyRow();
    expect($row['status'])->toBe('overdue')
        ->and($row['rent_status'])->toBe('overdue');
});

it('unbooks the income both payments recorded', function () {
    payJulyBill();

    expect((float) Accounts::where('account_type', Accounts::TYPE_INCOME)->sum('amount'))->toBe(540.0);

    foreach (Payments::where('rental_id', $this->rental->id)->get() as $payment) {
        $this->actingAs($this->admin)->delete(route('admin.revenue_expense.reverse_payment', $payment));
    }

    expect(Accounts::where('account_type', Accounts::TYPE_INCOME)->count())->toBe(0)
        ->and(Payments::where('rental_id', $this->rental->id)->count())->toBe(0)
        ->and(Payments::withTrashed()->where('rental_id', $this->rental->id)->count())->toBe(2);
});

it('removes the late fee ledger row with the rent payment that carried it', function () {
    auth()->login($this->admin);
    (new IncomeRecordingService(userId: $this->admin->id, period: $this->period))
        ->checkout($this->rental, [
            'payment_date' => '2026-07-25',
            'payment_method' => 'cash',
            'rent_amount' => 500,
            'late_fee' => 15,
            'pay_rent' => true,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);
    auth()->logout();

    expect(Accounts::where('category', Accounts::CAT_LATE_FEE_INCOME)->count())->toBe(1);

    $rent = Payments::where('rental_id', $this->rental->id)->firstOrFail();
    $this->actingAs($this->admin)->delete(route('admin.revenue_expense.reverse_payment', $rent));

    expect(Accounts::count())->toBe(0);
});

it('still reverses an earlier month\'s payment while that month is open', function () {
    payJulyBill();

    // The calendar has rolled on, but nobody has closed July: its totals are
    // still live, so a mistake found now is corrected the same way one found
    // in July would have been.
    Carbon::setTestNow('2026-08-05');

    $rent = Payments::where('rental_id', $this->rental->id)->where('payment_type', 'rent')->firstOrFail();
    $charges = Payments::where('rental_id', $this->rental->id)->where('payment_type', 'utilities')->firstOrFail();

    // The payment history keeps offering the undo control for both sides.
    $this->actingAs($this->admin)
        ->get(route('admin.tenants.show', $this->tenant))
        ->assertOk()
        ->assertSee(route('admin.revenue_expense.reverse_payment', $rent), false)
        ->assertSee(route('admin.revenue_expense.reverse_payment', $charges), false);

    $this->actingAs($this->admin)
        ->delete(route('admin.revenue_expense.reverse_payment', $rent))
        ->assertSessionHas('success');

    expect(Payments::find($rent->id))->toBeNull()
        ->and(julyRow()['rent_status'])->toBe('overdue');
});

it('stops offering the undo once the booked month is closed', function () {
    payJulyBill();
    Carbon::setTestNow('2026-08-05');

    closeJuly();

    $rent = Payments::where('rental_id', $this->rental->id)->where('payment_type', 'rent')->firstOrFail();
    $charges = Payments::where('rental_id', $this->rental->id)->where('payment_type', 'utilities')->firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('admin.revenue_expense.reverse_payment', $rent))
        ->assertSessionHas('error');

    expect(Payments::find($rent->id))->not->toBeNull()
        ->and(julyRow()['status'])->toBe('paid');

    $this->actingAs($this->admin)
        ->get(route('admin.tenants.show', $this->tenant))
        ->assertOk()
        ->assertDontSee(route('admin.revenue_expense.reverse_payment', $rent), false)
        ->assertDontSee(route('admin.revenue_expense.reverse_payment', $charges), false);
});

it('reverses this month\'s collection of an earlier month\'s bill', function () {
    // Rent is collected late all the time: July's bill, paid Aug 3. The money
    // was booked as August income, so undoing it restates only August.
    Carbon::setTestNow('2026-08-03');

    auth()->login($this->admin);
    (new IncomeRecordingService(userId: $this->admin->id, period: $this->period))
        ->checkout($this->rental, [
            'payment_date' => '2026-08-03',
            'payment_method' => 'cash',
            'rent_amount' => 500,
            'pay_rent' => true,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);
    auth()->logout();

    $rent = Payments::where('rental_id', $this->rental->id)->where('payment_type', 'rent')->firstOrFail();
    expect($rent->paid_at->month)->toBe(7)   // anchored in the billed month
        ->and(julyRow()['rent_status'])->toBe('paid');

    $this->actingAs($this->admin)
        ->delete(route('admin.revenue_expense.reverse_payment', $rent))
        ->assertSessionHas('success');

    expect(Payments::find($rent->id))->toBeNull()
        ->and(Accounts::count())->toBe(0)
        ->and(julyRow()['rent_status'])->toBe('overdue');
});

it('refuses to reverse a payment booked in a closed month', function () {
    payJulyBill();

    closeJuly();

    $rent = Payments::where('rental_id', $this->rental->id)->where('payment_type', 'rent')->firstOrFail();
    $this->actingAs($this->admin)
        ->delete(route('admin.revenue_expense.reverse_payment', $rent))
        ->assertSessionHas('error');

    expect(Payments::find($rent->id))->not->toBeNull()
        ->and(julyRow()['rent_status'])->toBe('paid');
});

it('refuses to reverse a payment booked in a closed fiscal period', function () {
    payJulyBill();
    $this->period->update(['status' => 'closed']);

    // Checked at the service — the route itself is behind fiscal.period, so a
    // closed period never reaches the controller in the first place.
    $this->actingAs($this->admin);
    $rent = Payments::where('rental_id', $this->rental->id)->where('payment_type', 'rent')->firstOrFail();
    $result = app(\App\Services\RevenueExpense\PaymentReversalService::class)->reverse($rent);

    expect($result['reversed'])->toBeFalse()
        ->and($result['reason'])->toBe('closed_period')
        ->and(Payments::find($rent->id))->not->toBeNull();
});

it('does not let another account reverse a payment', function () {
    payJulyBill();
    $rent = Payments::where('rental_id', $this->rental->id)->where('payment_type', 'rent')->firstOrFail();

    $other = makeAdmin(['name' => 'Other Owner']);
    makeFiscalPeriod($other, ['opening_date' => '2026-01-01', 'closing_date' => '2026-12-31']);

    $this->actingAs($other)
        ->delete(route('admin.revenue_expense.reverse_payment', $rent))
        ->assertNotFound();

    expect(Payments::withoutGlobalScope('account')->find($rent->id))->not->toBeNull();
});

it('offers the undo control on the tenant detail page in both panels', function () {
    payJulyBill();
    $rent = Payments::where('payment_type', 'rent')->firstOrFail();
    $charges = Payments::where('payment_type', 'utilities')->firstOrFail();

    $this->actingAs($this->admin)
        ->get(route('admin.tenants.show', $this->tenant))
        ->assertOk()
        ->assertSee(route('admin.revenue_expense.reverse_payment', $rent), false)
        ->assertSee(route('admin.revenue_expense.reverse_payment', $charges), false);

    // Admins previewing the supervisor panel see the whole account.
    $this->actingAs($this->admin)
        ->get(route('supervisor.tenants.show', $this->tenant))
        ->assertOk()
        ->assertSee(route('supervisor.revenue_expense.reverse_payment', $rent), false);
});
