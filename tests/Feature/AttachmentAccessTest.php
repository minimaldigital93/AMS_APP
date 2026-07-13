<?php

use App\Models\Apartments;
use App\Models\Attachment;
use App\Models\Floors;
use App\Models\Property;
use App\Models\Tenants;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * 2026-07 audit G2: attachments (tenant ID documents, expense receipts) live
 * on the PRIVATE disk and are only readable through the authenticated
 * attachments.show route — account-scoped, property-scoped for supervisors,
 * own-documents-only for tenants.
 */
function makeTenantDoc(Tenants $tenant, string $path = 'tenants/documents/doc.pdf'): Attachment
{
    Storage::disk(Attachment::DISK)->put($path, '%PDF-1.4 test');

    return Attachment::create([
        'attachable_type' => Tenants::class,
        'attachable_id' => $tenant->id,
        'kind' => Attachment::KIND_TENANT_DOCUMENT,
        'path' => $path,
        'original_name' => 'id-card.pdf',
        'mime_type' => 'application/pdf',
        'size' => 13,
    ]);
}

beforeEach(function () {
    Storage::fake(Attachment::DISK);

    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->property = Property::create(['name' => 'Main']);
    $this->floor = Floors::create(['property_id' => $this->property->id, 'floor_name' => 'F1']);
    $this->room = Apartments::create(['floor_id' => $this->floor->id, 'apartment_number' => 'S-101', 'monthly_rent' => 300, 'status' => 'occupied']);
    $this->tenant = makeTenant($this->room);
    $this->doc = makeTenantDoc($this->tenant);
    auth()->logout();
});

it('requires authentication', function () {
    $this->get(route('attachments.show', $this->doc))->assertRedirect(route('login'));
});

it('streams the file to the account admin', function () {
    $this->actingAs($this->admin)
        ->get(route('attachments.show', $this->doc))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('404s for an admin of another account', function () {
    $otherAdmin = makeAdmin();

    $this->actingAs($otherAdmin)
        ->get(route('attachments.show', $this->doc))
        ->assertNotFound();
});

it('scopes supervisors to their assigned properties', function () {
    $assigned = makeSupervisor(['account_id' => $this->admin->id]);
    $this->property->update(['supervisor_id' => $assigned->id]);

    $unassigned = makeSupervisor(['account_id' => $this->admin->id]);

    $this->actingAs($assigned)->get(route('attachments.show', $this->doc))->assertOk();
    $this->actingAs($unassigned)->get(route('attachments.show', $this->doc))->assertForbidden();
});

it('lets a tenant read only their own documents', function () {
    auth()->login($this->admin);
    $me = User::factory()->create(['account_id' => $this->admin->id]);
    $me->assignRole('tenant');
    $this->tenant->update(['user_id' => $me->id]);

    $otherTenant = makeTenant(null, ['phone' => '555-2222']);
    $otherDoc = makeTenantDoc($otherTenant, 'tenants/documents/other.pdf');
    auth()->logout();

    $this->actingAs($me)->get(route('attachments.show', $this->doc))->assertOk();
    $this->actingAs($me)->get(route('attachments.show', $otherDoc))->assertForbidden();
});

it('stores new uploads on the private disk, unreachable via /storage', function () {
    Storage::fake('public');

    auth()->login($this->admin);
    $service = app(\App\Services\Attachments\AttachmentService::class);
    $file = \Illuminate\Http\UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf');
    $stored = $service->storeMany($this->tenant, [$file], Attachment::KIND_TENANT_DOCUMENT, 'tenants/documents')->first();
    auth()->logout();

    Storage::disk(Attachment::DISK)->assertExists($stored->path);
    Storage::disk('public')->assertMissing($stored->path);
    expect($stored->url())->toContain('/attachments/'.$stored->id);
});
