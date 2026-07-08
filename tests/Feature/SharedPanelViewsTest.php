<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Property;

/**
 * Regression net for the shared-view refactor: the Admin and Supervisor panels
 * render the same Blade files (resources/views/shared/*) parameterized by
 * $panel. Every shared page must render for BOTH panels — a missing $panel or
 * a panel-specific route name inside a shared view would 500 here.
 */
function sharedViewsFixture(): array
{
    $admin = makeAdmin();
    auth()->login($admin);
    makeFiscalPeriod($admin);

    $property = Property::create(['name' => 'Main']);
    $floor = Floors::create(['property_id' => $property->id, 'floor_name' => 'F1']);
    $room = Apartments::create(['floor_id' => $floor->id, 'apartment_number' => 'F101', 'monthly_rent' => 500, 'status' => 'occupied']);
    $tenant = makeTenant($room);
    $rental = makeRental($tenant, $room);

    $sup = makeSupervisor(['account_id' => $admin->id]);
    $property->update(['supervisor_id' => $sup->id]);

    return compact('admin', 'sup', 'room', 'tenant', 'rental');
}

$revenueExpensePages = [
    'index', 'record_income', 'record_expense', 'generate_bills',
    'fixed_expenses', 'monthly_calendar', 'income_statement', 'break_even',
    'apartment_summary_preview',
];

foreach ($revenueExpensePages as $page) {
    it("renders shared revenue_expense {$page} in the admin panel", function () use ($page) {
        $f = sharedViewsFixture();
        $this->actingAs($f['admin'])
            ->get(route("admin.revenue_expense.{$page}"))
            ->assertOk();
    });

    it("renders shared revenue_expense {$page} in the supervisor panel", function () use ($page) {
        $f = sharedViewsFixture();
        $this->actingAs($f['sup'])
            ->get(route("supervisor.revenue_expense.{$page}"))
            ->assertOk();
    });
}

it('renders the shared tenant pages in the admin panel', function () {
    $f = sharedViewsFixture();
    $this->actingAs($f['admin']);

    $this->get(route('admin.tenants.create'))->assertOk();
    $this->get(route('admin.tenants.archived'))->assertOk();
    $this->get(route('admin.tenants.show', $f['tenant']))->assertOk();
    $this->get(route('admin.tenants.leave', $f['tenant']))->assertOk();
});

it('renders the shared tenant pages in the supervisor panel', function () {
    $f = sharedViewsFixture();
    $this->actingAs($f['sup']);

    $this->get(route('supervisor.tenants.create'))->assertOk();
    $this->get(route('supervisor.tenants.archived'))->assertOk();
    $this->get(route('supervisor.tenants.show', $f['tenant']))->assertOk();
    $this->get(route('supervisor.tenants.leave', $f['tenant']))->assertOk();
});

it('renders the panel-specific tenant index and edit pages', function () {
    $f = sharedViewsFixture();

    $this->actingAs($f['admin']);
    $this->get(route('admin.tenants.index'))->assertOk();
    $this->get(route('admin.tenants.edit', $f['tenant']))->assertOk();

    $this->actingAs($f['sup']);
    $this->get(route('supervisor.tenants.index'))->assertOk();
    $this->get(route('supervisor.tenants.edit', $f['tenant']))->assertOk();
});

it('renders the shared apartment pages in both panels', function () {
    $f = sharedViewsFixture();

    $this->actingAs($f['admin']);
    $this->get(route('admin.floors.plan3d'))->assertOk();
    $this->get(route('admin.apartments.show', $f['room']))->assertOk();

    $this->actingAs($f['sup']);
    $this->get(route('supervisor.floors.plan3d'))->assertOk();
    $this->get(route('supervisor.apartments.show', $f['room']))->assertOk();
});

it('still records a tenant gender, email and id card from both panels', function () {
    $f = sharedViewsFixture();

    // The create form is shared now; the full field set (email + gender +
    // id_card_number) must validate and persist on both panels.
    $vacant = Apartments::create(['floor_id' => $f['room']->floor_id, 'apartment_number' => 'F102', 'monthly_rent' => 400, 'status' => 'available']);

    $payload = [
        'apartment_id' => $vacant->id,
        'name' => 'Full Field Tenant',
        'gender' => 'female',
        'email' => 'full.field@example.test',
        'id_card_number' => 'ID-778899',
        'phone' => '012999888',
        'move_in_date' => now()->toDateString(),
        'status' => 'active',
        'deposit' => 100,
    ];

    $this->actingAs($f['sup'])
        ->post(route('supervisor.tenants.store'), $payload)
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('tenants', [
        'name' => 'Full Field Tenant',
        'gender' => 'female',
        'email' => 'full.field@example.test',
        'id_card_number' => 'ID-778899',
    ]);
});
