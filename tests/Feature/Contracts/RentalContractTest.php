<?php

use App\Models\Property;
use App\Models\Rentals;
use App\Services\Contracts\ContractGenerator;
use Illuminate\Support\Facades\Storage;

/**
 * Rental contract auto-generation and the admin PDF actions
 * (preview / download / print / regenerate).
 *
 * The contract PDF lives on the private `local` disk; these tests fake it so a
 * real mPDF render is exercised end to end without touching real storage.
 *
 * The contract is the one document rendered by mPDF rather than the app-wide
 * Dompdf — Dompdf has no OpenType shaper and mangles Khmer. See App\Services\Pdf\KhmerPdf.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin);
    $this->vacant = makeApartment(null, ['apartment_number' => 'C-101', 'status' => 'available', 'monthly_rent' => 350]);
    auth()->logout();
});

function contractAssignPayload(array $overrides = []): array
{
    return array_merge([
        'tenant_option' => 'new',
        'name' => 'Contract Tenant',
        'phone' => '096'.random_int(1000000, 9999999),
        'gender' => 'male',
        'id_card_number' => '012345678',
        'move_in_date' => now()->toDateString(),
        'deposit' => 100,
    ], $overrides);
}

it('auto-generates a stored contract with a number and creator when a tenant is assigned', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), contractAssignPayload(['phone' => '0962220001']))
        ->assertRedirect(route('admin.floors.index'));

    $rental = Rentals::sole();

    expect($rental->contract_number)->toStartWith('CTR-'.now()->year.'-')
        ->and($rental->contract_path)->not->toBeNull()
        ->and($rental->created_by)->toBe($this->admin->id)
        ->and($rental->payment_due_day)->toBe((int) now()->day);

    Storage::disk(ContractGenerator::DISK)->assertExists($rental->contract_path);
});

it('gives every lease a unique contract number', function () {
    $second = makeApartment(null, ['apartment_number' => 'C-102', 'status' => 'available', 'monthly_rent' => 400]);

    $this->actingAs($this->admin)->post(route('admin.apartments.assignTenant', $this->vacant), contractAssignPayload(['phone' => '0962220002']));
    $this->actingAs($this->admin)->post(route('admin.apartments.assignTenant', $second), contractAssignPayload(['phone' => '0962220003']));

    $numbers = Rentals::pluck('contract_number');

    expect($numbers)->toHaveCount(2)
        ->and($numbers->unique())->toHaveCount(2)
        ->and($numbers->filter())->toHaveCount(2);
});

it('lets an admin preview the contract inline as a PDF', function () {
    $this->actingAs($this->admin)->post(route('admin.apartments.assignTenant', $this->vacant), contractAssignPayload(['phone' => '0962220004']));
    $rental = Rentals::sole();

    $res = $this->actingAs($this->admin)->get(route('admin.contracts.preview', $rental));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    expect($res->headers->get('content-disposition'))->toContain('inline');
});

it('lets an admin download the contract as a PDF attachment', function () {
    $this->actingAs($this->admin)->post(route('admin.apartments.assignTenant', $this->vacant), contractAssignPayload(['phone' => '0962220005']));
    $rental = Rentals::sole();

    $res = $this->actingAs($this->admin)->get(route('admin.contracts.download', $rental));

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('attachment');
    expect($res->headers->get('content-disposition'))->toContain($rental->contract_number);
});

it('renders the contract viewer that loads the justified PDF for preview and print', function () {
    $this->actingAs($this->admin)->post(route('admin.apartments.assignTenant', $this->vacant), contractAssignPayload(['phone' => '0962220006']));
    $rental = Rentals::sole();

    // The viewer renders the mPDF-produced PDF (which carries the correctly-
    // justified Khmer) with pdf.js onto <canvas>, so the preview shows and prints
    // on every device — including iOS/PWA where an <iframe> won't render a PDF —
    // and the contract is never printed as browser HTML (no browser can justify
    // spaceless Khmer).
    $this->actingAs($this->admin)
        ->get(route('admin.contracts.view', $rental))
        ->assertOk()
        ->assertSee('កិច្ចសន្យាជួល', false)                                 // Rental contract title (Khmer)
        ->assertSee($rental->contract_number, false)
        ->assertSee(route('admin.contracts.preview', $rental), false)       // PDF source pdf.js loads
        ->assertSee('pdf.min.js', false);                                   // pdf.js renderer
});

it('regenerates the PDF while keeping the same contract number', function () {
    $this->actingAs($this->admin)->post(route('admin.apartments.assignTenant', $this->vacant), contractAssignPayload(['phone' => '0962220007']));
    $rental = Rentals::sole();
    $originalNumber = $rental->contract_number;

    // Wipe the stored file to prove regenerate rewrites it.
    Storage::disk(ContractGenerator::DISK)->delete($rental->contract_path);

    $this->actingAs($this->admin)
        ->from(route('admin.tenants.show', $rental->tenant_id))
        ->post(route('admin.contracts.regenerate', $rental))
        ->assertRedirect(route('admin.tenants.show', $rental->tenant_id))
        ->assertSessionHas('success');

    $rental->refresh();
    expect($rental->contract_number)->toBe($originalNumber);
    Storage::disk(ContractGenerator::DISK)->assertExists($rental->contract_path);
});

it('renews the fixed term and regenerates when renew_months is posted', function () {
    // 6-month lease starting today → ends in 6 months. Renewing by 6 extends the
    // total term to 12 months while keeping the same contract number and file.
    $this->actingAs($this->admin)->post(route('admin.apartments.assignTenant', $this->vacant), contractAssignPayload([
        'phone' => '0962220010',
        'contract_term_months' => 6,
    ]));
    $rental = Rentals::sole();
    $originalNumber = $rental->contract_number;

    $this->actingAs($this->admin)
        ->from(route('admin.tenants.show', $rental->tenant_id))
        ->post(route('admin.contracts.regenerate', $rental), ['renew_months' => 6])
        ->assertRedirect(route('admin.tenants.show', $rental->tenant_id))
        ->assertSessionHas('success');

    $rental->refresh();
    expect($rental->contract_term_months)->toBe(12)
        ->and($rental->contract_number)->toBe($originalNumber);
    Storage::disk(ContractGenerator::DISK)->assertExists($rental->contract_path);
});

it('rejects a renew term that is not 3, 6 or 12', function () {
    $this->actingAs($this->admin)->post(route('admin.apartments.assignTenant', $this->vacant), contractAssignPayload([
        'phone' => '0962220011',
        'contract_term_months' => 6,
    ]));
    $rental = Rentals::sole();

    $this->actingAs($this->admin)
        ->from(route('admin.tenants.show', $rental->tenant_id))
        ->post(route('admin.contracts.regenerate', $rental), ['renew_months' => 5])
        ->assertSessionHasErrors('renew_months');

    expect($rental->fresh()->contract_term_months)->toBe(6); // unchanged
});

it('embeds the Khmer fonts in the generated PDF', function () {
    // Regression guard for the engine swap. Dompdf produced a PDF that *looked*
    // fine to assertOk() while rendering Khmer with the coeng unstacked and the
    // vowels unreordered, because it ignores GSUB. mPDF subsets and embeds the
    // real faces, so their presence in the byte stream is the cheap proof that
    // the shaping engine — not the fallback — did the work.
    $this->actingAs($this->admin)->post(route('admin.apartments.assignTenant', $this->vacant), contractAssignPayload(['phone' => '0962220009']));
    $rental = Rentals::sole();

    $pdf = Storage::disk(ContractGenerator::DISK)->get($rental->contract_path);

    expect($pdf)->toStartWith('%PDF-')
        ->and($pdf)->toContain('KhmerOSSiemreap')  // body face
        ->and($pdf)->toContain('KhmerOSMuolLight'); // letterhead / title face
});

it('forbids a tenant from accessing contract actions', function () {
    $this->actingAs($this->admin)->post(route('admin.apartments.assignTenant', $this->vacant), contractAssignPayload(['phone' => '0962220008']));
    $rental = Rentals::sole();

    $tenantUser = $rental->tenant->user; // the portal login created during assignment

    $this->actingAs($tenantUser)
        ->get(route('admin.contracts.preview', $rental))
        ->assertForbidden();
});

it('also generates a contract when a supervisor assigns a tenant (panel-agnostic)', function () {
    auth()->login($this->admin);
    $supervisor = makeSupervisor(['account_id' => $this->admin->id, 'phone' => '0967778889']);
    $property = Property::create(['name' => 'Sup Block', 'supervisor_id' => $supervisor->id]);
    $floor = makeFloor('Sup Floor');
    $floor->update(['property_id' => $property->id]);
    $room = makeApartment($floor, ['apartment_number' => 'S-201', 'status' => 'available', 'monthly_rent' => 300]);
    auth()->logout();

    $this->actingAs($supervisor)
        ->post(route('supervisor.apartments.assignTenant', $room), contractAssignPayload(['phone' => '0964445556']))
        ->assertRedirect(route('supervisor.apartments.index'));

    $rental = Rentals::withoutAccountScope()->sole();
    expect($rental->contract_number)->not->toBeNull();
    Storage::disk(ContractGenerator::DISK)->assertExists($rental->contract_path);
});
