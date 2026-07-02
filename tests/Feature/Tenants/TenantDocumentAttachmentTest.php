<?php

use App\Models\Attachment;
use App\Models\Tenants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Tenant ID documents (front/back of ID card, etc.) support multiple files
 * (PDF or photo, up to 10MB each, max 4 files) via the polymorphic
 * `attachments` table. The old `document_path` column never had upload UI
 * wired to it and has been dropped.
 */
function storeTenantPayload(array $overrides = []): array
{
    $apartment = makeApartment();

    return array_merge([
        'apartment_id' => $apartment->id,
        'name' => 'Test Tenant',
        'phone' => '012345678',
        'move_in_date' => now()->toDateString(),
        'status' => 'active',
        'deposit' => 0,
    ], $overrides);
}

it('stores multiple documents when creating a tenant', function () {
    Storage::fake('public');
    $admin = makeAdmin();
    $this->actingAs($admin);

    $this->post(route('admin.tenants.store'), storeTenantPayload([
        'documents' => [
            UploadedFile::fake()->create('id-front.pdf', 200, 'application/pdf'),
            UploadedFile::fake()->image('id-back.jpg', 100, 100),
        ],
    ]))->assertRedirect();

    $tenant = Tenants::firstOrFail();
    expect($tenant->attachments)->toHaveCount(2);
});

it('rejects an oversized document', function () {
    Storage::fake('public');
    $admin = makeAdmin();
    $this->actingAs($admin);

    $this->post(route('admin.tenants.store'), storeTenantPayload([
        'documents' => [UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')],
    ]))->assertSessionHasErrors('documents.0');

    expect(Tenants::count())->toBe(0);
});

it('rejects more than the max document count', function () {
    Storage::fake('public');
    $admin = makeAdmin();
    $this->actingAs($admin);

    $this->post(route('admin.tenants.store'), storeTenantPayload([
        'documents' => [
            UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('c.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('d.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('e.pdf', 100, 'application/pdf'),
        ],
    ]))->assertSessionHasErrors('documents');
});

it('keeps existing documents when uploading a new one on update', function () {
    Storage::fake('public');
    $admin = makeAdmin();
    $this->actingAs($admin);

    $tenant = makeTenant(null, ['phone' => '012345678']);
    $tenant->attachments()->create([
        'kind' => Attachment::KIND_TENANT_DOCUMENT,
        'path' => UploadedFile::fake()->create('existing.pdf', 100)->store('tenants/documents', 'public'),
        'original_name' => 'existing.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100 * 1024,
    ]);

    $this->put(route('admin.tenants.update', $tenant), storeTenantPayload([
        'apartment_id' => $tenant->apartment_id,
        'phone' => $tenant->phone,
        'documents' => [UploadedFile::fake()->create('new.pdf', 100, 'application/pdf')],
    ]))->assertRedirect();

    expect($tenant->fresh()->attachments)->toHaveCount(2);
});

it('deletes a single document without touching the others', function () {
    Storage::fake('public');
    $admin = makeAdmin();
    $this->actingAs($admin);

    $tenant = makeTenant();
    $kept = $tenant->attachments()->create([
        'kind' => Attachment::KIND_TENANT_DOCUMENT,
        'path' => UploadedFile::fake()->create('kept.pdf', 100)->store('tenants/documents', 'public'),
        'original_name' => 'kept.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100 * 1024,
    ]);
    $removed = $tenant->attachments()->create([
        'kind' => Attachment::KIND_TENANT_DOCUMENT,
        'path' => UploadedFile::fake()->create('removed.pdf', 100)->store('tenants/documents', 'public'),
        'original_name' => 'removed.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100 * 1024,
    ]);

    $this->delete(route('admin.tenants.destroy_document', [$tenant, $removed]))->assertRedirect();

    Storage::disk('public')->assertMissing($removed->path);
    Storage::disk('public')->assertExists($kept->path);
    expect(Attachment::find($removed->id))->toBeNull()
        ->and(Attachment::find($kept->id))->not->toBeNull();
});

it('prevents deleting another accounts tenant document', function () {
    Storage::fake('public');
    $adminA = makeAdmin();
    $this->actingAs($adminA);

    $tenant = makeTenant();
    $attachment = $tenant->attachments()->create([
        'kind' => Attachment::KIND_TENANT_DOCUMENT,
        'path' => UploadedFile::fake()->create('id.pdf', 100)->store('tenants/documents', 'public'),
        'original_name' => 'id.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100 * 1024,
    ]);

    $adminB = makeAdmin(['name' => 'Other Admin']);
    $this->actingAs($adminB);

    $this->delete(route('admin.tenants.destroy_document', [$tenant, $attachment]))->assertNotFound();

    // Query without the account scope: acting as adminB, a scoped lookup would
    // return null regardless of whether the row still exists.
    expect(Attachment::withoutAccountScope()->find($attachment->id))->not->toBeNull();
});
