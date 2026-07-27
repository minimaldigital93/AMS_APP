<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The tenant edit page replaces the photo through the "+" badge on the photo.
 * Phone photos regularly exceed PHP's default upload_max_filesize (2M), in
 * which case PHP drops the file and Laravel reports a bare "failed to upload".
 * That message is overridden with one that says what actually went wrong.
 */
function updateTenantPayload(\App\Models\Tenants $tenant, array $overrides = []): array
{
    return array_merge([
        'apartment_id' => $tenant->apartment_id,
        'name' => $tenant->name,
        'phone' => $tenant->phone,
        'move_in_date' => now()->toDateString(),
        'status' => 'active',
        'deposit' => 0,
    ], $overrides);
}

it('replaces the tenant photo and deletes the old file', function () {
    Storage::fake('public');
    $this->actingAs(makeAdmin());

    $tenant = makeTenant(null, ['phone' => '012345678']);
    $tenant->update(['photo_path' => UploadedFile::fake()->image('old.jpg')->store('tenants', 'public')]);
    $oldPath = $tenant->photo_path;

    $this->put(route('admin.tenants.update', $tenant), updateTenantPayload($tenant, [
        'photo' => UploadedFile::fake()->image('new.jpg', 400, 400),
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $newPath = $tenant->fresh()->photo_path;
    expect($newPath)->not->toBeNull()->and($newPath)->not->toBe($oldPath);
    Storage::disk('public')->assertExists($newPath);
    Storage::disk('public')->assertMissing($oldPath);
});

it('explains a photo that PHP refused to upload instead of a bare failure', function () {
    Storage::fake('public');
    $this->actingAs(makeAdmin());

    $tenant = makeTenant(null, ['phone' => '012345678']);

    // How a file over PHP's upload_max_filesize arrives: no temp file, error set.
    $dropped = new UploadedFile(
        UploadedFile::fake()->image('huge.jpg')->getPathname(),
        'huge.jpg',
        'image/jpeg',
        UPLOAD_ERR_INI_SIZE,
        true
    );

    $this->put(route('admin.tenants.update', $tenant), updateTenantPayload($tenant, ['photo' => $dropped]))
        ->assertSessionHasErrors(['photo' => __('messages.validation_photo_upload_failed', ['max' => '10 MB'])]);

    expect($tenant->fresh()->photo_path)->toBeNull();
});
