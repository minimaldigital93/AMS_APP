<?php

use App\Models\Payments;
use App\Models\Settings;
use App\Models\Utilities;
use Carbon\Carbon;

/**
 * The receipt is a document for ONE payment, not a snapshot of the room's
 * current state.
 *
 * Rent and charges settle on separate visits (see "A bill has two sides" in
 * CLAUDE.md), so each visit writes its own Payments row and each row gets its
 * own receipt: its own amount, method, reference, note and receipt number.
 * Without `?payment=` the same URL renders the month's BILL SUMMARY instead —
 * what is owed and what has settled — which is a different document and says
 * so.
 *
 * The rent figure on both comes from BillingCycleService, never from
 * `rentals.rent_amount`: rent is derived from the calendar here, so a prorated
 * move-in month must print the amount that was actually billed.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-08-20');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 300]);
    $this->tenant = makeTenant($this->apartment, ['move_in_date' => '2026-08-08']);
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 300,
        'start_date' => '2026-08-08',
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

/** Settings are account-scoped, so the write has to happen as the admin. */
function collectionDay(int $day): void
{
    test()->actingAs(test()->admin);
    Settings::set('billing_cycle_day', (string) $day);
    auth()->logout();
}

function receipt(array $params = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->admin)->get(route('admin.revenue_expense.print_receipt', array_merge([
        'rental' => test()->rental->id,
        'month' => 8,
        'year' => 2026,
    ], $params)));
}

function payAugustRent(array $overrides = []): Payments
{
    return Payments::create(array_merge([
        'rental_id' => test()->rental->id,
        'amount' => 241.94,
        'due_date' => '2026-09-02',
        'paid_at' => '2026-08-20',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'payment_type' => 'rent',
        'late_fee' => 0,
        'note' => 'Monthly rent payment',
    ], $overrides));
}

function augustCharge(string $type, float $amount, bool $paid = false, ?string $paidAt = null): Utilities
{
    return Utilities::create([
        'tenant_id' => test()->tenant->id,
        'rental_id' => test()->rental->id,
        'utility_type' => $type,
        'charge_amount' => $amount,
        'billing_month' => 8,
        'billing_year' => 2026,
        'paid_status' => $paid,
        'paid_at' => $paidAt,
    ]);
}

it('bills the prorated move-in rent, not the lease rent', function () {
    collectionDay(2);

    // Aug 8 → Sep 2 on a $300 month = 25 days at $300/31.
    $bill = receipt()->viewData('lines')[0];

    expect($bill['label'])->toBe(__('messages.rent'))
        ->and(round($bill['amount'], 2))->toBe(241.94);
});

it('falls back to the lease rent when the account has no collection day', function () {
    expect(receipt()->viewData('lines')[0]['amount'])->toBe(300.0);
});

it('renders the month summary — not a receipt — when no payment is named', function () {
    $response = receipt();

    expect($response->viewData('isReceipt'))->toBeFalse()
        ->and($response->viewData('receiptNumber'))->toBeNull()
        ->and($response->viewData('payment'))->toBeNull();
    $response->assertSee(__('messages.bill_summary'), false);
});

it('bills only what the named payment collected', function () {
    collectionDay(2);
    augustCharge('electricity', 40);
    $rent = payAugustRent();

    $response = receipt(['payment' => $rent->id]);

    // The unpaid electricity charge belongs to the bill, not to this receipt.
    expect($response->viewData('isReceipt'))->toBeTrue()
        ->and($response->viewData('lines'))->toHaveCount(1)
        ->and(round($response->viewData('total'), 2))->toBe(241.94)
        ->and(round($response->viewData('amountPaid'), 2))->toBe(241.94);
});

it('itemises the late fee it collected instead of hiding it in the total', function () {
    $rent = payAugustRent(['late_fee' => 15]);

    $response = receipt(['payment' => $rent->id]);
    $labels = collect($response->viewData('lines'))->pluck('label');

    expect($labels)->toContain(__('messages.late_fee'))
        // Total and amount received agree — the old receipt totalled the bill
        // without the fee while counting the fee as paid, so it printed short.
        ->and(round($response->viewData('total'), 2))->toBe(256.94)
        ->and(round($response->viewData('amountPaid'), 2))->toBe(256.94);
});

it('lists the charge rows a utilities payment settled', function () {
    augustCharge('electricity', 40, paid: true, paidAt: '2026-09-01 00:00:00');
    augustCharge('water', 12, paid: true, paidAt: '2026-09-01 00:00:00');
    // A later month's charge settled on another day must not ride along.
    Utilities::create([
        'tenant_id' => $this->tenant->id,
        'rental_id' => $this->rental->id,
        'utility_type' => 'trash',
        'charge_amount' => 5,
        'billing_month' => 9,
        'billing_year' => 2026,
        'paid_status' => true,
        'paid_at' => '2026-09-15 00:00:00',
    ]);

    $charges = Payments::create([
        'rental_id' => $this->rental->id,
        'amount' => 52,
        'due_date' => '2026-09-01',
        'paid_at' => '2026-09-01 00:00:00',
        'payment_method' => 'khqr',
        'payment_status' => 'paid',
        'payment_type' => 'utilities',
        'late_fee' => 0,
    ]);

    $response = receipt(['month' => 9, 'year' => 2026, 'payment' => $charges->id]);

    expect($response->viewData('lines'))->toHaveCount(2)
        ->and(round($response->viewData('total'), 2))->toBe(52.0)
        ->and($response->viewData('paymentMethod'))->toBe('khqr');
});

it('takes method, reference and note from the named payment, not the newest one', function () {
    $rent = payAugustRent(['payment_method' => 'cash', 'transaction_reference' => 'RENT-1', 'note' => 'Paid at office']);
    Payments::create([
        'rental_id' => $this->rental->id,
        'amount' => 40,
        'due_date' => '2026-08-31',
        'paid_at' => '2026-08-31',
        'payment_method' => 'khqr',
        'payment_status' => 'paid',
        'payment_type' => 'utilities',
        'transaction_reference' => 'KHQR-9',
        'late_fee' => 0,
        'note' => 'Meter reading visit',
    ]);

    $response = receipt(['payment' => $rent->id]);

    expect($response->viewData('paymentMethod'))->toBe('cash')
        ->and($response->viewData('reference'))->toBe('RENT-1')
        ->and($response->viewData('note'))->toBe('Paid at office');
});

it('keeps the receipt number stable when a later payment lands', function () {
    $rent = payAugustRent();
    $before = receipt(['payment' => $rent->id])->viewData('receiptNumber');

    Payments::create([
        'rental_id' => $this->rental->id,
        'amount' => 40,
        'due_date' => '2026-08-31',
        'paid_at' => '2026-08-31',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'payment_type' => 'utilities',
        'late_fee' => 0,
    ]);

    expect(receipt(['payment' => $rent->id])->viewData('receiptNumber'))->toBe($before);
});

it('tags each summary line with whether it has settled', function () {
    payAugustRent();
    augustCharge('electricity', 40, paid: true, paidAt: '2026-08-31 00:00:00');
    augustCharge('water', 12);

    $lines = collect(receipt()->viewData('lines'));

    expect($lines->firstWhere('label', __('messages.rent'))['settled'])->toBeTrue()
        ->and($lines->firstWhere('label', __('messages.electricity'))['settled'])->toBeTrue()
        ->and($lines->firstWhere('label', __('messages.water'))['settled'])->toBeFalse()
        // Balance is what is still open, not total minus cash taken.
        ->and(round(receipt()->viewData('balance'), 2))->toBe(12.0)
        ->and(receipt()->viewData('isPaid'))->toBeFalse();
});

it('does not mark the summary paid while charges are still open', function () {
    payAugustRent();
    augustCharge('electricity', 40);

    expect(receipt()->viewData('isPaid'))->toBeFalse();
});

it('treats unread meters as nothing owed, so rent-paid reads as settled', function () {
    payAugustRent();

    expect(receipt()->viewData('isPaid'))->toBeTrue();
});

it('refuses a payment id belonging to another rental', function () {
    $otherApartment = makeApartment();
    $otherTenant = makeTenant($otherApartment);
    $otherRental = makeRental($otherTenant, $otherApartment);

    $foreign = Payments::create([
        'rental_id' => $otherRental->id,
        'amount' => 500,
        'due_date' => '2026-08-01',
        'paid_at' => '2026-08-01',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'payment_type' => 'rent',
        'late_fee' => 0,
    ]);

    receipt(['payment' => $foreign->id])->assertNotFound();
});

it('lists every payment in the month so the collector can pick between them', function () {
    payAugustRent();
    Payments::create([
        'rental_id' => $this->rental->id,
        'amount' => 40,
        'due_date' => '2026-08-31',
        'paid_at' => '2026-08-31',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'payment_type' => 'utilities',
        'late_fee' => 0,
    ]);

    expect(receipt()->viewData('monthPayments'))->toHaveCount(2);
});
