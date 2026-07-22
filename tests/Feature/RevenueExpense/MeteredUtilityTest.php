<?php

use App\Models\Utilities;
use App\Services\RevenueExpense\IncomeRecordingService;

beforeEach(function () {
    $this->admin = makeAdmin();
    $this->actingAs($this->admin); // so settings() resolve for this account
    $this->period = makeFiscalPeriod($this->admin);
    $this->apartment = makeApartment(null, ['apartment_number' => 'A-101', 'monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment, ['rent_amount' => 500]);
    $this->service = new IncomeRecordingService(userId: $this->admin->id, period: $this->period);
});

it('records an opening reading with meter-in only and no charge', function () {
    $this->service->addTenantCharge($this->rental, [
        'charge_type' => 'electricity',
        'meter_reading_in' => 100,
        'billing_month' => 5,
        'billing_year' => 2026,
    ]);

    expect(Utilities::count())->toBe(1);

    $row = Utilities::sole();
    expect((float) $row->meter_reading_in)->toBe(100.0);
    expect($row->meter_reading_out)->toBeNull();
    expect((float) $row->charge_amount)->toBe(0.0);
    expect($row->paid_status)->toBeFalse();
});

it('auto-calculates the charge from usage × unit price and upserts the month row', function () {
    settings(['utility_meter_auto_calc' => '1', 'utility_electricity_price' => '0.25']);

    // Opening reading first…
    $this->service->addTenantCharge($this->rental, [
        'charge_type' => 'electricity',
        'meter_reading_in' => 100,
        'billing_month' => 5,
        'billing_year' => 2026,
    ]);

    // …then the closing reading. A bogus client amount must be ignored.
    $this->service->addTenantCharge($this->rental, [
        'charge_type' => 'electricity',
        'meter_reading_in' => 100,
        'meter_reading_out' => 250,
        'charge_amount' => 999,
        'billing_month' => 5,
        'billing_year' => 2026,
    ]);

    // Same continuous meter → one row, not two.
    expect(Utilities::count())->toBe(1);

    $row = Utilities::sole();
    expect((float) $row->meter_reading_out)->toBe(250.0);
    // usage 150 × $0.25 = $37.50
    expect((float) $row->charge_amount)->toBe(37.5);
});

it('honours the typed amount in manual mode while still storing the meters', function () {
    settings(['utility_meter_auto_calc' => '0', 'utility_electricity_price' => '0.25']);

    $this->service->addTenantCharge($this->rental, [
        'charge_type' => 'electricity',
        'meter_reading_in' => 100,
        'meter_reading_out' => 250,
        'charge_amount' => 40,
        'billing_month' => 5,
        'billing_year' => 2026,
    ]);

    $row = Utilities::sole();
    expect((float) $row->charge_amount)->toBe(40.0); // typed, not 37.50
    expect((float) $row->meter_reading_in)->toBe(100.0);
    expect((float) $row->meter_reading_out)->toBe(250.0);
});

it('carries the previous month closing reading forward as the next month meter-in', function () {
    // A closed May reading (out = 250) should seed June's opening.
    Utilities::create([
        'tenant_id' => $this->tenant->id,
        'rental_id' => $this->rental->id,
        'utility_type' => 'electricity',
        'meter_reading_in' => 100,
        'meter_reading_out' => 250,
        'charge_amount' => 37.5,
        'billing_month' => 5,
        'billing_year' => 2026,
        'paid_status' => false,
    ]);

    $data = $this->get(route('admin.revenue_expense.record_income', ['month' => 6, 'year' => 2026]))
        ->assertOk()
        ->viewData('meterContext');

    expect($data[$this->rental->id]['electricity']['start'])->toBe('250');
    expect($data[$this->rental->id]['electricity']['out'])->toBe('');
});

it('never mutates a settled (paid) metered row — it creates a fresh one', function () {
    Utilities::create([
        'tenant_id' => $this->tenant->id,
        'rental_id' => $this->rental->id,
        'utility_type' => 'electricity',
        'meter_reading_in' => 100,
        'meter_reading_out' => 250,
        'charge_amount' => 37.5,
        'billing_month' => 5,
        'billing_year' => 2026,
        'paid_status' => true,
    ]);

    $this->service->addTenantCharge($this->rental, [
        'charge_type' => 'electricity',
        'meter_reading_in' => 250,
        'meter_reading_out' => 300,
        'charge_amount' => 20,
        'billing_month' => 5,
        'billing_year' => 2026,
    ]);

    // Paid row untouched + a new unpaid row alongside it.
    expect(Utilities::count())->toBe(2);
    expect(Utilities::where('paid_status', true)->sole()->charge_amount)->toEqual(37.5);
    expect(Utilities::where('paid_status', false)->sole()->meter_reading_in)->toEqual(250.0);
});

it('rejects a closing reading below the opening reading via the add-charge endpoint', function () {
    $this->post(route('admin.revenue_expense.add_charge'), [
        'rental_id' => $this->rental->id,
        'charge_type' => 'water',
        'meter_reading_in' => 300,
        'meter_reading_out' => 200,
        'billing_month' => 5,
        'billing_year' => 2026,
    ])->assertSessionHasErrors('meter_reading_out');

    expect(Utilities::count())->toBe(0);
});
