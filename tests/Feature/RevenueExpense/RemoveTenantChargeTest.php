<?php

use App\Models\Accounts;
use App\Models\MonthlyPeriod;
use App\Models\Payments;
use App\Models\Utilities;
use App\Services\RevenueExpense\IncomeRecordingService;
use Carbon\Carbon;

/**
 * The eye icon on a rent-collection row opens the charges modal, and every
 * line in it carries a remove button. Four things have to hold, and none of
 * them did:
 *
 * - The button has to be VISIBLE. It was `opacity-0 group-hover:opacity-100`,
 *   so on the phone card list — which opens the same modal — there is no hover
 *   and the control the operator was told to click never appeared.
 * - A removal has to REACH THE PAGE. Every figure on the row is derived from
 *   the charge rows (the Charges column, the Total, the badge, the tiles), and
 *   the handler only spliced its own copy of the array, so the row went on
 *   quoting a charge that no longer existed until a manual refresh.
 * - A REFUSAL has to be shown. The write gates answer an XHR with a JSON 422
 *   but answer a browser with a redirect, which fetch follows to a 200 HTML
 *   page — so `res.ok` was true for a charge that was never removed.
 * - A PAID charge has to be removable, and removing it is a different
 *   operation. It is collected money, so the row cannot simply be dropped: the
 *   payment that settled it is reversed first (PaymentReversalService — the one
 *   sanctioned money-undo path here), which takes its ledger income with it and
 *   puts every OTHER charge on that payment back to unpaid. Dropping the row
 *   alone would leave a Payments row and its income standing with nothing
 *   behind them. Closed money is still never restated: the reversal's own
 *   closed-month refusal is what the modal shows.
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

function removableCharge(string $type = 'electricity', float $amount = 40, bool $paid = false): Utilities
{
    return Utilities::create([
        'tenant_id' => test()->tenant->id,
        'rental_id' => test()->rental->id,
        'utility_type' => $type,
        'meter_reading_in' => 0,
        'meter_reading_out' => 0,
        'charge_amount' => $amount,
        'billing_month' => 7,
        'billing_year' => 2026,
        'paid_status' => $paid,
        'paid_at' => $paid ? '2026-07-20' : null,
    ]);
}

/**
 * Collect the month's outstanding charges the way the checkout modal does, so
 * the paid rows under test carry a real Payments row and real ledger income.
 */
function settleRemovableCharges(string $paymentDate = '2026-07-20'): Payments
{
    auth()->login(test()->admin);
    (new IncomeRecordingService(userId: test()->admin->id, period: test()->period))
        ->checkout(test()->rental, [
            'payment_date' => $paymentDate,
            'payment_method' => 'cash',
            'rent_amount' => 500,
            'pay_rent' => false,
            'pay_utilities' => true,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);
    auth()->logout();

    return Payments::where('rental_id', test()->rental->id)
        ->where('payment_type', 'utilities')
        ->firstOrFail();
}

/** Close July — the deadline a reversal, and so a paid removal, has to beat. */
function closeJulyForRemoval(): void
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

function removableChargesRow(): array
{
    $response = test()->actingAs(test()->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 7, 'year' => 2026]));

    return collect($response->viewData('tenantBills')->items())
        ->firstWhere(fn ($b) => $b['rental']->id === test()->rental->id);
}

it('removes an unpaid charge and takes it back out of the row the modal was opened from', function () {
    $charge = removableCharge('electricity', 40);

    expect(removableChargesRow()['total_bill'])->toBe(540.0);

    $this->actingAs($this->admin)
        ->deleteJson(route('admin.revenue_expense.remove_charge', $charge->id))
        ->assertOk()
        ->assertJson(['success' => true]);

    $row = removableChargesRow();
    expect(Utilities::find($charge->id))->toBeNull()
        ->and($row['utilities'])->toHaveCount(0)
        ->and((float) $row['total_utilities'])->toBe(0.0)
        // The Charges column prints total_bill - monthly_rent, so this is the
        // figure the operator said never updated.
        ->and($row['total_bill'] - $row['monthly_rent'])->toBe(0.0)
        ->and($row['charges_status'])->toBe('none');
});

it('removes a paid charge by reversing the payment that settled it, income and all', function () {
    removableCharge('water', 25);
    $payment = settleRemovableCharges();
    $charge = Utilities::where('rental_id', $this->rental->id)->firstOrFail();

    expect($charge->paid_status)->toBeTrue()
        ->and(Accounts::where('payment_id', $payment->id)->sum('amount'))->toEqual(25.0);

    $this->actingAs($this->admin)
        ->deleteJson(route('admin.revenue_expense.remove_charge', $charge->id))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'reversed' => true,
            'message' => __('messages.flash_charge_removed_reversed', ['amount' => '25.00']),
        ]);

    // The charge is gone AND so is every trace of the money it collected —
    // dropping the row alone would leave this payment and its income standing
    // with nothing behind them.
    expect(Utilities::find($charge->id))->toBeNull()
        ->and(Payments::find($payment->id))->toBeNull()
        ->and(Accounts::where('payment_id', $payment->id)->count())->toBe(0);

    $row = removableChargesRow();
    expect($row['charges_status'])->toBe('none')
        ->and($row['total_bill'])->toBe(500.0);
});

it('puts the other charges on that payment back to unpaid, and says how many', function () {
    removableCharge('electricity', 40);
    removableCharge('water', 25);
    settleRemovableCharges();

    $electricity = Utilities::where('utility_type', 'electricity')->firstOrFail();

    // One payment covered both, so reversing it re-opens the water charge too.
    // The operator clicked one line and cannot see that from the modal — the
    // message is the only place it is said.
    $this->actingAs($this->admin)
        ->deleteJson(route('admin.revenue_expense.remove_charge', $electricity->id))
        ->assertOk()
        ->assertJson([
            'reversed' => true,
            'message' => __('messages.flash_charge_removed_reversed_others', ['amount' => '65.00', 'count' => 1]),
        ]);

    expect(Utilities::find($electricity->id))->toBeNull();
    expect(Utilities::where('utility_type', 'water')->firstOrFail())
        ->paid_status->toBeFalse()
        ->paid_at->toBeNull();

    $row = removableChargesRow();
    expect($row['charges_status'])->toBe('pending')
        ->and($row['total_bill'])->toBe(525.0);
});

it('refuses a paid charge whose month has been closed, in the reversal\'s own words', function () {
    removableCharge('water', 25);
    $payment = settleRemovableCharges();
    closeJulyForRemoval();

    $charge = Utilities::where('rental_id', $this->rental->id)->firstOrFail();

    $this->actingAs($this->admin)
        ->deleteJson(route('admin.revenue_expense.remove_charge', $charge->id))
        ->assertStatus(422)
        ->assertJson(['message' => __('messages.flash_payment_reverse_blocked_closed_month')]);

    // Closed money is not restated, and nothing was half-done.
    expect(Utilities::find($charge->id))->not->toBeNull()
        ->and(Payments::find($payment->id))->not->toBeNull()
        ->and(Accounts::where('payment_id', $payment->id)->count())->toBe(1);
});

it('refuses a paid charge no payment can be matched to rather than orphaning its income', function () {
    // The shape a move-out settlement leaves behind: paid, but with its income
    // booked outside any Payments row. There is nothing to reverse and no way
    // to find the ledger rows, so removing it would strand the income.
    $charge = removableCharge('water', 25, paid: true);

    $this->actingAs($this->admin)
        ->deleteJson(route('admin.revenue_expense.remove_charge', $charge->id))
        ->assertStatus(422)
        ->assertJson(['message' => __('messages.flash_charge_payment_unmatched')]);

    expect(Utilities::find($charge->id))->not->toBeNull();
});

it('answers an XHR with a readable JSON 422, never a redirect fetch would follow to a 200', function () {
    $charge = removableCharge('trash', 10);

    $response = $this->actingAs($this->admin)
        ->deleteJson(route('admin.revenue_expense.remove_charge', $charge->id));

    expect($response->headers->get('content-type'))->toContain('json');
});

it('clears one month of unpaid charges and leaves the paid ones booked', function () {
    $unpaid = removableCharge('electricity', 40);
    $paid = removableCharge('water', 25, paid: true);

    $this->actingAs($this->admin)
        ->deleteJson(route('admin.revenue_expense.clear_charges', $this->rental->id).'?month=7&year=2026')
        ->assertOk();

    expect(Utilities::find($unpaid->id))->toBeNull()
        ->and(Utilities::find($paid->id))->not->toBeNull()
        ->and(removableChargesRow()['total_bill'])->toBe(525.0);
});

it('shows the remove button without waiting for a hover that a phone never sends', function () {
    removableCharge('electricity', 40);

    $html = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 7, 'year' => 2026]))
        ->getContent();

    $button = strstr($html, 'removeViewCharge(c.id, i, c.paid)');
    $button = substr($button, 0, strpos($button, '</button>'));

    expect($button)->not->toContain('opacity-0')
        ->and($button)->not->toContain('group-hover:opacity-100')
        // …and it is offered on a PAID line too. `x-show="!c.paid"` was the
        // whole of "the user can't remove a paid charge".
        ->and($button)->not->toContain('x-show="!c.paid"');
});

it('warns that removing a paid charge reverses its payment before anything moves', function () {
    removableCharge('electricity', 40);

    $html = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 7, 'year' => 2026]))
        ->getContent();

    // The consequence is invisible from the line being clicked — the payment
    // comes out of the books and its sibling charges re-open — so the confirm
    // has to spell it out rather than reuse the plain "Remove this charge?".
    expect($html)->toContain(e(__('messages.remove_paid_charge_confirm')))
        ->and($html)->toContain(e(__('messages.remove_charge_confirm')));
});

it('reloads the page when the modal closes after a removal, so the derived figures catch up', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 7, 'year' => 2026]))
        ->getContent();

    // Every close path goes through the handler; none may set the flag directly.
    expect($html)->toContain('closeChargesReceipt()')
        ->and($html)->toContain('this.viewChargesDirty = true;')
        ->and($html)->not->toContain('@click="showChargesReceipt = false"');
});
