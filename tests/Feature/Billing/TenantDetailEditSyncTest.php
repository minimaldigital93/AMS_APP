<?php

use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\Rentals;
use App\Models\Settings;
use Carbon\Carbon;

/**
 * Editing a tenant's lease details must reach the lease.
 *
 * Every money figure in this app is derived from the `rentals` row — rent due,
 * proration, due day, arrears, the contract PDF — while the tenant edit form
 * writes `tenants`. If the edit doesn't carry across, the profile shows the
 * corrected move-in date / deposit while billing keeps charging the old one.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-20');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 300, 'status' => 'occupied']);
    $this->tenant = makeTenant($this->apartment, [
        'move_in_date' => '2026-08-08',
        'deposit' => 300,
    ]);
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 300,
        'start_date' => '2026-08-08',
        'payment_due_day' => 8,
        'deposit' => 300,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function editPayload(array $overrides = []): array
{
    return array_merge([
        'apartment_id' => test()->apartment->id,
        'name' => test()->tenant->name,
        'phone' => test()->tenant->phone,
        'move_in_date' => '2026-08-08',
        'status' => 'active',
        'deposit' => 300,
    ], $overrides);
}

it('carries a corrected move-in date onto the active lease', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.tenants.update', $this->tenant->id), editPayload([
            'move_in_date' => '2026-08-20',
        ]))
        ->assertRedirect();

    $this->rental->refresh();

    expect($this->rental->start_date->toDateString())->toBe('2026-08-20')
        ->and($this->rental->payment_due_day)->toBe(20);
});

it('reprices the move-in month after the move-in date is corrected', function () {
    Settings::set('billing_cycle_day', '2');

    $this->actingAs($this->admin)
        ->put(route('admin.tenants.update', $this->tenant->id), editPayload([
            'move_in_date' => '2026-08-20',
        ]));

    $response = $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_income', [
        'month' => 8, 'year' => 2026,
    ]));

    $bill = collect($response->viewData('tenantBills')->items())
        ->firstWhere(fn ($b) => $b['rental']->id === $this->rental->id);

    // Aug 20 → Sep 2 = 13 of August's 31 days, so 13 x ($300 / 31) = $125.81.
    expect($bill['monthly_rent'])->toBe(125.81);
});

it('carries a corrected deposit onto the lease and its booked income row', function () {
    Accounts::create([
        'fiscal_period_id' => \App\Models\FiscalPeriods::first()->id,
        'user_id' => $this->admin->id,
        'account_type' => Accounts::TYPE_INCOME,
        'category' => Accounts::CAT_DEPOSIT_INCOME,
        'description' => 'Security deposit received',
        'amount' => 300,
        'transaction_date' => '2026-08-08',
        'reference_number' => 'deposit:rental:'.$this->rental->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.tenants.update', $this->tenant->id), editPayload([
            'deposit' => 450,
        ]));

    expect($this->rental->refresh()->deposit)->toBe(450.0)
        ->and(Accounts::where('reference_number', 'deposit:rental:'.$this->rental->id)->value('amount'))
        ->toEqual(450.0);
});

it('carries a repriced room onto the active lease', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.apartments.update', $this->apartment->id), [
            'apartment_number' => $this->apartment->apartment_number,
            'monthly_rent' => 380,
            'status' => 'occupied',
        ])
        ->assertRedirect();

    expect($this->rental->refresh()->rent_amount)->toBe(380.0);
});

it('leaves an ended lease alone when the room is repriced', function () {
    $old = Rentals::create([
        'apartment_id' => $this->apartment->id,
        'tenant_id' => $this->tenant->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-08-07',
        'rent_amount' => 300,
        'deposit' => 300,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.apartments.update', $this->apartment->id), [
            'apartment_number' => $this->apartment->apartment_number,
            'monthly_rent' => 380,
            'status' => 'occupied',
        ]);

    expect($old->refresh()->rent_amount)->toBe(300.0);
});

it('reprints the contract due day from the corrected move-in date', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.tenants.update', $this->tenant->id), editPayload([
            'move_in_date' => '2026-08-20',
        ]));

    $data = app(\App\Services\Contracts\ContractGenerator::class)->viewData($this->rental->refresh());

    expect($data['dueDay'])->toBe(20);
});

it('syncs the lease from the supervisor panel too', function () {
    $property = \App\Models\Property::create(['name' => 'Main']);
    $floor = \App\Models\Floors::create(['property_id' => $property->id, 'floor_name' => 'F1']);
    $room = makeApartment($floor, ['monthly_rent' => 300, 'status' => 'occupied']);
    $tenant = makeTenant($room, ['move_in_date' => '2026-08-08', 'deposit' => 300, 'phone' => '555-0101']);
    $rental = makeRental($tenant, $room, [
        'rent_amount' => 300,
        'start_date' => '2026-08-08',
        'payment_due_day' => 8,
        'deposit' => 300,
    ]);

    $supervisor = makeSupervisor(['account_id' => $this->admin->id]);
    $property->update(['supervisor_id' => $supervisor->id]);

    $this->actingAs($supervisor)
        ->put(route('supervisor.tenants.update', $tenant->id), [
            'apartment_id' => $room->id,
            'name' => $tenant->name,
            'phone' => $tenant->phone,
            'move_in_date' => '2026-08-20',
            'status' => 'active',
            'deposit' => 300,
        ])
        ->assertRedirect();

    $rental->refresh();

    expect($rental->start_date->toDateString())->toBe('2026-08-20')
        ->and($rental->payment_due_day)->toBe(20);
});

it('does not touch a lease the tenant has already left', function () {
    $ended = Rentals::create([
        'apartment_id' => Apartments::create([
            'floor_id' => $this->apartment->floor_id,
            'apartment_number' => 'OLD-1',
            'monthly_rent' => 200,
            'status' => 'available',
        ])->id,
        'tenant_id' => $this->tenant->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-08-07',
        'rent_amount' => 200,
        'deposit' => 200,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.tenants.update', $this->tenant->id), editPayload([
            'move_in_date' => '2026-08-20',
        ]));

    expect($ended->refresh()->start_date->toDateString())->toBe('2026-01-01');
});
