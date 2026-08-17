<?php

use App\Models\ApartmentFixedExpense;
use App\Models\Property;
use App\Models\TenantVehicle;
use App\Models\Utilities;
use App\Services\RevenueExpense\MonthlyBillingService;
use Carbon\Carbon;

beforeEach(function () {
    $this->admin = makeAdmin();
    $this->period = makeFiscalPeriod($this->admin);
    $this->apartment = makeApartment(null, ['apartment_number' => 'V-101']);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment);
    $this->service = new MonthlyBillingService;
});

// ---------------------------------------------------------------------------
// The card itself
// ---------------------------------------------------------------------------

it('records a vehicle from the tenant detail card', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.tenants.vehicles.store', $this->tenant), [
            'vehicle_type' => 'motorbike',
            'vehicle_model' => '  Honda   Dream ',
            'plate_number' => ' 2a-0000 ',
            'monthly_fee' => 10,
        ])
        ->assertRedirect(route('admin.tenants.show', $this->tenant->id));

    $vehicle = TenantVehicle::first();

    // Plates are normalised so the per-account unique rule can actually catch
    // the same vehicle being registered twice.
    expect($vehicle->plate_number)->toBe('2A-0000')
        ->and($vehicle->vehicle_type)->toBe('motorbike')
        // The model is description only — trimmed, but never uppercased and
        // never part of the plate uniqueness.
        ->and($vehicle->vehicle_model)->toBe('Honda Dream')
        ->and($vehicle->monthly_fee)->toEqual(10.0)
        ->and($vehicle->account_id)->toBe($this->admin->id);
});

it('refuses a plate already registered in the account', function () {
    $other = makeTenant();

    $this->actingAs($this->admin)->post(route('admin.tenants.vehicles.store', $this->tenant), [
        'vehicle_type' => 'car', 'plate_number' => '2A-0000', 'monthly_fee' => 25,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.tenants.vehicles.store', $other), [
            'vehicle_type' => 'car', 'plate_number' => '2A-0000', 'monthly_fee' => 25,
        ])
        ->assertSessionHasErrors('plate_number');

    expect(TenantVehicle::count())->toBe(1);
});

it('shows the tenant’s vehicles on the detail page in both panels', function () {
    $this->tenant->vehicles()->create([
        'vehicle_type' => 'tuktuk', 'vehicle_model' => 'Honda Dream',
        'plate_number' => '1B-1234', 'monthly_fee' => 15,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.tenants.show', $this->tenant->id))
        ->assertOk()
        ->assertSee('1B-1234')
        ->assertSee('Honda Dream');

    $this->actingAs($this->admin)
        ->get(route('supervisor.tenants.show', $this->tenant->id))
        ->assertOk()
        ->assertSee('1B-1234')
        ->assertSee('Honda Dream');
});

it('removing a vehicle leaves charges already billed alone', function () {
    $vehicle = $this->tenant->vehicles()->create([
        'vehicle_type' => 'car', 'plate_number' => '2A-0000', 'monthly_fee' => 30,
    ]);

    $this->service->processAll(\App\Models\Apartments::query(), Carbon::parse('2026-05-10'));
    expect(Utilities::where('utility_type', 'parking')->count())->toBe(1);

    $this->actingAs($this->admin)
        ->delete(route('admin.tenants.vehicles.destroy', [$this->tenant, $vehicle]))
        ->assertRedirect();

    expect(TenantVehicle::count())->toBe(0)
        // Billed money is owed (or collected) — deleting the vehicle is not a
        // restatement of the month.
        ->and(Utilities::where('utility_type', 'parking')->first()->charge_amount)->toEqual(30.0);
});

it('403s a supervisor managing a tenant outside their properties', function () {
    $supervisor = makeSupervisor(['account_id' => $this->admin->id]);
    $property = Property::create(['name' => 'Not Theirs', 'supervisor_id' => null, 'account_id' => $this->admin->id]);
    $this->apartment->floor->update(['property_id' => $property->id]);

    $this->actingAs($supervisor)
        ->post(route('supervisor.tenants.vehicles.store', $this->tenant), [
            'vehicle_type' => 'car', 'plate_number' => '9Z-9999', 'monthly_fee' => 20,
        ])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// …and the money it turns into
// ---------------------------------------------------------------------------

it('bills every priced vehicle as one parking charge', function () {
    $this->tenant->vehicles()->createMany([
        ['vehicle_type' => 'car', 'plate_number' => '2A-0001', 'monthly_fee' => 30],
        ['vehicle_type' => 'motorbike', 'plate_number' => '2A-0002', 'monthly_fee' => 10],
    ]);

    $result = $this->service->processAll(\App\Models\Apartments::query(), Carbon::parse('2026-05-10'));

    // A room's parking is ONE charge — (rental, type, month, year) is unique —
    // so two vehicles are one row for their combined fee.
    $rows = Utilities::where('utility_type', 'parking')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->charge_amount)->toEqual(40.0)
        ->and($rows->first()->paid_status)->toBeFalse()
        ->and($result['total'])->toEqual(40.0);
});

it('does not bill an unpriced vehicle', function () {
    $this->tenant->vehicles()->create([
        'vehicle_type' => 'motorbike', 'plate_number' => '2A-0003', 'monthly_fee' => 0,
    ]);

    $this->service->processAll(\App\Models\Apartments::query(), Carbon::parse('2026-05-10'));

    expect(Utilities::count())->toBe(0);
});

it('lets priced vehicles supersede the room’s fixed parking cost', function () {
    ApartmentFixedExpense::create([
        'apartment_id' => $this->apartment->id,
        'expense_name' => 'Parking',
        'expense_type' => 'parking',
        'amount' => 40,
        'is_active' => true,
    ]);
    $this->tenant->vehicles()->create([
        'vehicle_type' => 'car', 'plate_number' => '2A-0004', 'monthly_fee' => 25,
    ]);

    $result = $this->service->processAll(\App\Models\Apartments::query(), Carbon::parse('2026-05-10'));

    // Only one parking row can exist for the month; the vehicle fee is the
    // authority, and the room template must not be counted on top of it.
    $rows = Utilities::where('utility_type', 'parking')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->charge_amount)->toEqual(25.0)
        ->and($result['total'])->toEqual(25.0);
});

it('bills vehicle parking for a rental that has no fixed-expense templates', function () {
    $this->tenant->vehicles()->create([
        'vehicle_type' => 'car', 'plate_number' => '2A-0005', 'monthly_fee' => 20,
    ]);

    // The form posts no `expenses` array at all for such a rental — only the
    // apartment checkbox — and that used to skip the whole row.
    $result = $this->service->processSelected([
        ['rental_id' => $this->rental->id, 'selected' => true],
    ], Carbon::parse('2026-05-10'));

    expect($result['count'])->toBe(1)
        ->and(Utilities::where('utility_type', 'parking')->first()->charge_amount)->toEqual(20.0);
});

it('shows the vehicle parking line on the bill-generation page', function () {
    $this->tenant->vehicles()->create([
        'vehicle_type' => 'car', 'plate_number' => '2A-0007', 'monthly_fee' => 20,
    ]);

    // It has no fixed-expense template to tick, so it is printed read-only —
    // the checkbox/amount inputs would fail the request's `expense_id` rule.
    $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.generate_bills'))
        ->assertOk()
        ->assertSee(__('messages.vehicle_parking'))
        ->assertDontSee('[expenses][0][expense_id]', false);
});

// ---------------------------------------------------------------------------
// …and what the rent-collection page's "Add charge" modal quotes for parking
// ---------------------------------------------------------------------------

it('quotes add-charge parking off the tenant’s registered vehicles', function () {
    $this->tenant->vehicles()->createMany([
        ['vehicle_type' => 'car', 'plate_number' => '2A-0008', 'monthly_fee' => 30],
        ['vehicle_type' => 'motorbike', 'plate_number' => '2A-0009', 'monthly_fee' => 10],
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_income'));
    $response->assertOk();

    $ctx = $response->viewData('vehicleContext')[$this->rental->id] ?? null;

    // Two vehicles, ONE parking charge for their combined fee — the same figure
    // the bill run would raise, so the modal can't quote a different number.
    expect($ctx)->not->toBeNull()
        ->and($ctx['count'])->toBe(2)
        ->and($ctx['fee'])->toEqual(40.0)
        ->and($ctx['amount'])->toBe('40.00')
        ->and($ctx['plates'])->toContain('2A-0008')
        ->and($ctx['billed'])->toBeFalse();
});

it('offers no add-charge parking for a tenant with no priced vehicle', function () {
    // An unpriced vehicle is recorded, not billed (parking included in the
    // rent) — so the chip stays greyed out, exactly as with no vehicle at all.
    $this->tenant->vehicles()->create([
        'vehicle_type' => 'motorbike', 'plate_number' => '2A-0010', 'monthly_fee' => 0,
    ]);
    settings(['utility_parking_fee' => 15]);

    $response = $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_income'));
    $response->assertOk();

    $ctx = $response->viewData('vehicleContext')[$this->rental->id] ?? null;

    // The account-wide default fee is NOT the fallback: parking is whatever the
    // tenant's own vehicles come to, or nothing.
    expect($ctx['count'])->toBe(0)
        ->and($ctx['fee'])->toEqual(0.0)
        ->and($ctx['amount'])->toBe('');
});

it('bills the summed vehicle fee once on the rent-collection page', function () {
    // The room also carries a fixed parking template — only one parking charge
    // per room per month exists, and the priced vehicles are the authority for
    // it, so the template must not ride along on the rent side as well.
    ApartmentFixedExpense::create([
        'apartment_id' => $this->apartment->id,
        'expense_name' => 'Parking', 'expense_type' => 'parking',
        'amount' => 40, 'is_active' => true,
    ]);
    $this->tenant->vehicles()->createMany([
        ['vehicle_type' => 'car', 'plate_number' => '2A-0012', 'monthly_fee' => 30],
        ['vehicle_type' => 'tuktuk', 'plate_number' => '2A-0013', 'monthly_fee' => 10],
    ]);

    $this->service->processAll(\App\Models\Apartments::query(), now());

    $response = $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_income'));
    $bill = collect($response->viewData('tenantBillsAll'))->firstWhere('rental.id', $this->rental->id);

    // The two vehicles come to one $40 charge on the charges side, and the
    // superseded template is gone from the rent side rather than counted twice.
    expect($bill['total_other_charges'])->toEqual(40.0)
        ->and($bill['unpaid_other_charges'])->toEqual(40.0)
        ->and($bill['total_fixed'])->toEqual(0.0)
        ->and($bill['charges_status'])->toBe('pending');
});

it('keeps the room’s fixed parking when the tenant’s vehicles are free', function () {
    ApartmentFixedExpense::create([
        'apartment_id' => $this->apartment->id,
        'expense_name' => 'Parking', 'expense_type' => 'parking',
        'amount' => 40, 'is_active' => true,
    ]);
    // A blank fee records the vehicle without billing it — nothing supersedes
    // the room's own parking cost.
    $this->tenant->vehicles()->create([
        'vehicle_type' => 'motorbike', 'plate_number' => '2A-0014', 'monthly_fee' => 0,
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_income'));
    $bill = collect($response->viewData('tenantBillsAll'))->firstWhere('rental.id', $this->rental->id);

    expect($bill['total_fixed'])->toEqual(40.0);
});

it('flags parking already billed for the month in the add-charge modal', function () {
    $this->tenant->vehicles()->create([
        'vehicle_type' => 'car', 'plate_number' => '2A-0011', 'monthly_fee' => 20,
    ]);
    $this->service->processAll(\App\Models\Apartments::query(), now());

    $response = $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_income'));

    // One room, one parking charge per month — the modal warns rather than
    // silently raising a second row.
    expect($response->viewData('vehicleContext')[$this->rental->id]['billed'])->toBeTrue();
});

it('never double-bills parking on a repeat run', function () {
    $this->tenant->vehicles()->create([
        'vehicle_type' => 'car', 'plate_number' => '2A-0006', 'monthly_fee' => 20,
    ]);
    $date = Carbon::parse('2026-05-10');

    $this->service->processAll(\App\Models\Apartments::query(), $date);
    $second = $this->service->processAll(\App\Models\Apartments::query(), $date);

    expect($second['count'])->toBe(0)
        ->and(Utilities::where('utility_type', 'parking')->count())->toBe(1);
});
