<?php

use App\Models\Rentals;
use App\Models\Settings;
use Illuminate\Support\Facades\Storage;

/**
 * Owner information and the default utility prices: configured once in
 * Settings, then printed on every generated rental contract.
 *
 * Assertions run against the print (HTML) route rather than the stored PDF —
 * the PDF byte stream is compressed, so the text is not greppable there. The
 * two paths share ContractGenerator::viewData(), so this covers both.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin);
    $this->vacant = makeApartment(null, ['apartment_number' => 'D-101', 'status' => 'available', 'monthly_rent' => 350]);
    auth()->logout();
});

/** Assign a tenant and return the lease its contract was generated for. */
function leaseWithContract(mixed $test, mixed $apartment, string $phone): Rentals
{
    $test->actingAs($test->admin)->post(
        route('admin.apartments.assignTenant', $apartment),
        contractAssignPayload(['phone' => $phone])
    );

    return Rentals::sole();
}

it('shows the owner and utility groups on the settings page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee(__('messages.owner_information'), false)
        ->assertSee(__('messages.default_utility_prices'), false)
        ->assertSee('settings[owner_id_card]', false)
        ->assertSee('settings[utility_water_price]', false);
});

it('saves owner information and the default utility prices', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.updateBatch'), [
            'settings' => [
                'owner_name' => 'Chan Sophea',
                'owner_gender' => 'female',
                'owner_id_card' => '098765432',
                'owner_phone' => '012345678',
                'owner_address' => 'No. 653, Russey Keo, Phnom Penh',
                'utility_electricity_price' => '0.25',
                'utility_water_price' => '0.50',
                'utility_parking_fee' => '10',
                'utility_internet_fee' => '8',
                'utility_garbage_fee' => '2',
            ],
        ])
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHas('success');

    auth()->login($this->admin);
    expect(Settings::get('owner_name'))->toBe('Chan Sophea')
        ->and(Settings::get('owner_id_card'))->toBe('098765432')
        ->and((float) Settings::get('utility_water_price'))->toBe(0.5);
});

it('rejects a non-numeric utility price and an unknown owner gender', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.updateBatch'), [
            'settings' => [
                'utility_water_price' => 'free',
                'owner_gender' => 'yes',
            ],
        ])
        ->assertSessionHasErrors(['settings.utility_water_price', 'settings.owner_gender']);
});

it('prints the configured owner and utility prices on the contract', function () {
    auth()->login($this->admin);
    settings([
        'owner_name' => 'Chan Sophea',
        'owner_gender' => 'female',
        'owner_id_card' => '098765432',
        'owner_phone' => '012345678',
        'utility_water_price' => '0.50',
        'utility_garbage_fee' => '2',
    ]);
    auth()->logout();

    $rental = leaseWithContract($this, $this->vacant, '0963330001');

    $this->actingAs($this->admin)
        ->get(route('admin.contracts.print', $rental))
        ->assertOk()
        ->assertSee('Chan Sophea', false)
        ->assertSee('098765432', false)
        ->assertSee('ស្រី', false)      // owner gender, Khmer
        ->assertSee('$0.50', false)     // water, from settings
        ->assertSee('$2.00', false);    // garbage, from settings
});

it('lets a lease price override the account default', function () {
    auth()->login($this->admin);
    settings(['utility_water_price' => '0.50']);
    auth()->logout();

    $rental = leaseWithContract($this, $this->vacant, '0963330002');
    $rental->forceFill(['water_price' => 1.25])->save();

    $this->actingAs($this->admin)
        ->get(route('admin.contracts.print', $rental))
        ->assertOk()
        ->assertSee('$1.25', false)
        ->assertDontSee('$0.50', false);
});

it('falls back to the company block when owner fields are blank', function () {
    auth()->login($this->admin);
    settings([
        'company_name' => 'Sokha Residence',
        'company_phone' => '011223344',
        'company_address' => 'Toul Kork, Phnom Penh',
    ]);
    auth()->logout();

    $rental = leaseWithContract($this, $this->vacant, '0963330003');

    $this->actingAs($this->admin)
        ->get(route('admin.contracts.print', $rental))
        ->assertOk()
        ->assertSee('Sokha Residence', false)
        ->assertSee('Toul Kork, Phnom Penh', false);
});
