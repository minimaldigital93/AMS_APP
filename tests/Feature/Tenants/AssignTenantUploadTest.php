<?php

use App\Models\Attachment;
use App\Models\Property;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\User;
use App\Services\Attachments\AttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Assign-tenant upload hardening: strict per-field type/size validation,
 * account-scoped tenant_id, full rollback (rows AND files) on any mid-flight
 * failure, and the shared-service parity between the admin and supervisor
 * panels.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake(Attachment::DISK);

    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin);
    $this->vacant = makeApartment(null, ['apartment_number' => 'U-101', 'status' => 'available', 'monthly_rent' => 400]);
    auth()->logout();
});

function uploadPayload(array $overrides = []): array
{
    return array_merge([
        'tenant_option' => 'new',
        'name' => 'Upload Tenant',
        'phone' => '096'.random_int(1000000, 9999999),
        'move_in_date' => now()->toDateString(),
        'deposit' => 50,
    ], $overrides);
}

it('stores a valid photo and ID document and links both to the new tenant', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), uploadPayload([
            'phone' => '0961112223',
            'attached_photo' => UploadedFile::fake()->image('portrait.jpg', 640, 480),
            'id_pdf' => UploadedFile::fake()->create('national-id.pdf', 200, 'application/pdf'),
        ]))
        ->assertRedirect(route('admin.floors.index'));

    $tenant = Tenants::where('phone', '0961112223')->sole();

    expect($tenant->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($tenant->photo_path);

    $document = $tenant->attachments()->where('kind', Attachment::KIND_TENANT_DOCUMENT)->sole();
    expect($document->original_name)->toBe('national-id.pdf');
    Storage::disk(Attachment::DISK)->assertExists($document->path);
});

it('rejects an oversized photo (over 5 MB) with a field error and creates nothing', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.floors.index'))
        ->post(route('admin.apartments.assignTenant', $this->vacant), uploadPayload([
            'attached_photo' => UploadedFile::fake()->image('huge.jpg')->size(6000),
        ]))
        ->assertRedirect(route('admin.floors.index'))
        ->assertSessionHasErrors('attached_photo');

    expect(Tenants::count())->toBe(0)
        ->and($this->vacant->refresh()->status)->toBe('available')
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('accepts a document between 5 and 10 MB (documents get the higher cap)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), uploadPayload([
            'phone' => '0961112224',
            'id_pdf' => UploadedFile::fake()->create('lease-scan.pdf', 8000, 'application/pdf'),
        ]))
        ->assertRedirect(route('admin.floors.index'))
        ->assertSessionHasNoErrors();
});

it('rejects files whose content is not an allowed type (disguised executable)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), uploadPayload([
            'attached_photo' => UploadedFile::fake()->create('malware.php', 10, 'text/x-php'),
        ]))
        ->assertSessionHasErrors('attached_photo');

    // A PDF is a document, not a profile photo.
    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), uploadPayload([
            'attached_photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('attached_photo');

    // Real image content but a disallowed client extension (double extension).
    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), uploadPayload([
            'id_pdf' => UploadedFile::fake()->create('doc.pdf.exe', 10, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('id_pdf');

    expect(Tenants::count())->toBe(0);
});

it('rejects a cross-account tenant_id at validation instead of crashing mid-assignment', function () {
    $otherAdmin = makeAdmin();
    auth()->login($otherAdmin);
    $foreignTenant = makeTenant(null, ['apartment_id' => null, 'phone' => '555-8888']);
    auth()->logout();

    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), [
            'tenant_option' => 'existing',
            'tenant_id' => $foreignTenant->id,
            'move_in_date' => now()->toDateString(),
            'deposit' => 0,
        ])
        ->assertSessionHasErrors('tenant_id');

    expect($this->vacant->refresh()->status)->toBe('available');
});

it('rejects an archived tenant_id at validation', function () {
    auth()->login($this->admin);
    $archived = makeTenant(null, ['apartment_id' => null, 'phone' => '555-9999', 'archived_at' => now()]);
    auth()->logout();

    $this->actingAs($this->admin)
        ->post(route('admin.apartments.assignTenant', $this->vacant), [
            'tenant_option' => 'existing',
            'tenant_id' => $archived->id,
            'move_in_date' => now()->toDateString(),
            'deposit' => 0,
        ])
        ->assertSessionHasErrors('tenant_id');
});

it('rolls back all rows AND deletes the stored photo when a later step fails', function () {
    $this->mock(AttachmentService::class)
        ->shouldReceive('storeMany')
        ->andThrow(new RuntimeException('disk full'));

    $this->actingAs($this->admin)
        ->from(route('admin.floors.index'))
        ->post(route('admin.apartments.assignTenant', $this->vacant), uploadPayload([
            'phone' => '0961112225',
            'attached_photo' => UploadedFile::fake()->image('portrait.jpg', 640, 480),
            'id_pdf' => UploadedFile::fake()->create('national-id.pdf', 200, 'application/pdf'),
        ]))
        ->assertRedirect(route('admin.floors.index'))
        ->assertSessionHas('error');

    // No partial data: no tenant, no login, no rental, room still free…
    expect(Tenants::count())->toBe(0)
        ->and(User::where('phone', '0961112225')->count())->toBe(0)
        ->and(Rentals::count())->toBe(0)
        ->and(Attachment::count())->toBe(0)
        ->and($this->vacant->refresh()->status)->toBe('available')
        // …and the already-written photo was cleaned off the disk.
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('lets a supervisor assign into an assigned property, booking the deposit into the ADMIN ledger', function () {
    auth()->login($this->admin);
    $supervisor = makeSupervisor(['account_id' => $this->admin->id, 'phone' => '0967778889']);
    $property = Property::create(['name' => 'Sup Block', 'supervisor_id' => $supervisor->id]);
    $floor = makeFloor('Sup Floor');
    $floor->update(['property_id' => $property->id]);
    $room = makeApartment($floor, ['apartment_number' => 'S-201', 'status' => 'available', 'monthly_rent' => 300]);
    auth()->logout();

    $this->actingAs($supervisor)
        ->post(route('supervisor.apartments.assignTenant', $room), uploadPayload([
            'phone' => '0964445556',
            'deposit' => 75,
            'attached_photo' => UploadedFile::fake()->image('face.png', 320, 320),
        ]))
        ->assertRedirect(route('supervisor.apartments.index'));

    $tenant = Tenants::withoutAccountScope()->where('phone', '0964445556')->sole();
    expect($room->refresh()->status)->toBe('occupied')
        ->and($tenant->photo_path)->not->toBeNull();

    // One-ledger invariant: the deposit row belongs to the admin, not the supervisor.
    $deposit = \App\Models\Accounts::withoutAccountScope()->where('category', \App\Models\Accounts::CAT_DEPOSIT_INCOME)->sole();
    expect($deposit->user_id)->toBe($this->admin->id)
        ->and($deposit->fiscal_period_id)->toBe($this->period->id);
});

it('blocks a supervisor from assigning into a property that is not theirs (403, before validation leaks anything)', function () {
    auth()->login($this->admin);
    $supervisor = makeSupervisor(['account_id' => $this->admin->id, 'phone' => '0967778890']);
    // $this->vacant's floor has no property, so it is not assigned to this supervisor.
    auth()->logout();

    $this->actingAs($supervisor)
        ->post(route('supervisor.apartments.assignTenant', $this->vacant), uploadPayload())
        ->assertForbidden();

    expect(Tenants::count())->toBe(0)
        ->and($this->vacant->refresh()->status)->toBe('available');
});

it('renders a friendly 413 (not the framework error page) when the post body exceeds the server limit', function () {
    \Illuminate\Support\Facades\Route::post('/_test/too-large-bare', function () {
        throw new \Illuminate\Http\Exceptions\PostTooLargeException;
    });

    $this->post('/_test/too-large-bare')
        ->assertStatus(413)
        ->assertSee(__('Upload too large'));
});

it('bounces back with an error flash when the oversized post happens on a session-aware route', function () {
    \Illuminate\Support\Facades\Route::post('/_test/too-large-web', function () {
        throw new \Illuminate\Http\Exceptions\PostTooLargeException;
    })->middleware('web');

    $this->from(route('login'))
        ->post('/_test/too-large-web')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');
});
