<?php

use App\Models\Utilities;
use App\Services\RevenueExpense\IncomeRecordingService;
use App\Services\RevenueExpense\MonthlyBillingService;
use Carbon\Carbon;

/**
 * Operators enter the month's charges on the rent-collection page BEFORE taking
 * the payment, and they routinely re-open the Add-Charge modal to correct a
 * figure. A room raises at most one parking (internet, trash) charge per month —
 * the same (rental, utility_type, month, year) invariant MonthlyBillingService
 * enforces for the bill run — so a repeat save must EDIT the open row, never
 * stack a second charge on top of it.
 *
 * `other` is deliberately exempt: it is the ad-hoc bucket with no template, and
 * two unrelated one-off charges in a month are legitimate.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-08-17');
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    $this->apartment = makeApartment(null, ['apartment_number' => 'A-101', 'monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 500,
        'start_date' => '2026-05-10',
    ]);
    $this->service = new IncomeRecordingService(userId: $this->admin->id, period: $this->period);
});

afterEach(fn () => Carbon::setTestNow());

function saveCharge(string $type, float $amount): Utilities
{
    return test()->service->addTenantCharge(test()->rental, [
        'charge_type' => $type,
        'charge_amount' => $amount,
        'billing_month' => 8,
        'billing_year' => 2026,
    ]);
}

it('corrects the open parking charge instead of raising a second one', function () {
    saveCharge('parking', 10);
    saveCharge('parking', 15);

    $rows = Utilities::where('utility_type', 'parking')->get();

    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()->charge_amount)->toBe(15.0);
});

it('does the same for the other recurring types', function (string $type) {
    saveCharge($type, 8);
    saveCharge($type, 12);

    $rows = Utilities::where('utility_type', $type)->get();

    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()->charge_amount)->toBe(12.0);
})->with(['internet', 'trash']);

it('keeps ad-hoc `other` charges additive — two one-offs in a month are legitimate', function () {
    saveCharge('other', 20);
    saveCharge('other', 5);

    $rows = Utilities::where('utility_type', 'other')->get();

    expect($rows)->toHaveCount(2);
    expect((float) $rows->sum('charge_amount'))->toBe(25.0);
});

it('never mutates a paid charge — a new row is raised beside it', function () {
    $first = saveCharge('parking', 10);
    $first->update(['paid_status' => true, 'paid_at' => now()]);

    saveCharge('parking', 15);

    $rows = Utilities::where('utility_type', 'parking')->orderBy('id')->get();

    expect($rows)->toHaveCount(2);
    expect((float) $rows[0]->charge_amount)->toBe(10.0)
        ->and($rows[0]->paid_status)->toBeTrue();
    expect((float) $rows[1]->charge_amount)->toBe(15.0)
        ->and($rows[1]->paid_status)->toBeFalse();
});

it('does not double-charge when the bill run follows a hand-entered parking charge', function () {
    saveCharge('parking', 10);

    // The bill run's own (rental, type, month) guard already skips the pair.
    app(MonthlyBillingService::class)->processSelected([[
        'rental_id' => $this->rental->id,
        'selected' => 1,
        'expenses' => [],
    ]], Carbon::parse('2026-08-17'));

    expect(Utilities::where('utility_type', 'parking')->count())->toBe(1);
});

/**
 * …and because a re-save corrects the open row, the modal has to OPEN on that
 * row: the operator edits the figure they can see, instead of retyping a charge
 * blind and hoping it lands on the right one.
 */
it('hands the Add-Charge modal the charges already recorded for the month', function () {
    saveCharge('parking', 12);
    saveCharge('internet', 8);

    $ctx = $this->get(route('admin.revenue_expense.record_income'))
        ->assertOk()
        ->viewData('chargeContext')[$this->rental->id];

    expect($ctx['parking']['editing'])->toBeTrue()
        ->and($ctx['parking']['amount'])->toBe('12.00')
        ->and($ctx['parking']['paid'])->toBeFalse()
        ->and($ctx['internet']['amount'])->toBe('8.00');
});

it('does not prefill a settled charge — a save there raises a separate row', function () {
    saveCharge('parking', 12)->update(['paid_status' => true, 'paid_at' => now()]);

    $ctx = $this->get(route('admin.revenue_expense.record_income'))
        ->viewData('chargeContext')[$this->rental->id];

    expect($ctx['parking']['editing'])->toBeFalse()
        ->and($ctx['parking']['amount'])->toBe('')
        ->and($ctx['parking']['paid'])->toBeTrue()
        ->and($ctx['parking']['total'])->toBe('12.00');
});

it('reports `other` without prefilling it — the bucket stays additive', function () {
    saveCharge('other', 20);
    saveCharge('other', 5);

    $ctx = $this->get(route('admin.revenue_expense.record_income'))
        ->viewData('chargeContext')[$this->rental->id];

    expect($ctx['other']['editing'])->toBeFalse()
        ->and($ctx['other']['amount'])->toBe('')
        ->and($ctx['other']['count'])->toBe(2)
        ->and($ctx['other']['total'])->toBe('25.00');
});

it('reports a correction rather than an addition on the rent-collection page', function () {
    $this->post(route('admin.revenue_expense.add_charge'), [
        'rental_id' => $this->rental->id,
        'charge_type' => 'parking',
        'charge_amount' => 10,
        'billing_month' => 8,
        'billing_year' => 2026,
    ])->assertRedirect();

    $this->post(route('admin.revenue_expense.add_charge'), [
        'rental_id' => $this->rental->id,
        'charge_type' => 'parking',
        'charge_amount' => 15,
        'billing_month' => 8,
        'billing_year' => 2026,
    ])->assertSessionHas('success', __('messages.flash_charge_updated', [
        'type' => 'Parking',
        'amount' => '15.00',
        'name' => $this->tenant->name,
    ]));

    expect(Utilities::where('utility_type', 'parking')->count())->toBe(1);
});
