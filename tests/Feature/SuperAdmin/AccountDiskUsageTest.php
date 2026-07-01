<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeSuperadminForUsage(): User
{
    seedRoles();
    $user = User::factory()->create(['name' => 'Platform Owner']);
    $user->assignRole('superadmin');

    return $user;
}

/**
 * A fake uploaded file whose real on-disk content is exactly $bytes long, so the
 * TracksFileSizes hook's filesize() reads a known value (UploadedFile::fake()
 * ->create() only fakes the reported size, leaving an empty file on disk).
 */
function fakeFileOfBytes(string $name, int $bytes, string $folder): string
{
    return UploadedFile::fake()
        ->createWithContent($name, str_repeat('a', $bytes))
        ->store($folder, 'public');
}

it('records a file byte size when its path is set, replaced, or cleared', function () {
    Storage::fake('public');
    $admin = makeAdmin(['status' => 'active']);

    $tenant = makeTenant(null, [
        'account_id' => $admin->id,
        'phone' => '555-1001',
        'photo_path' => fakeFileOfBytes('photo.jpg', 20 * 1024, 'tenants'),
    ]);

    expect($tenant->photo_size)->toBe(20 * 1024)
        ->and($tenant->document_size)->toBeNull();

    // Replacing the file recomputes the size.
    $tenant->update(['photo_path' => fakeFileOfBytes('photo2.jpg', 50 * 1024, 'tenants')]);
    expect($tenant->fresh()->photo_size)->toBe(50 * 1024);

    // Clearing the path clears the size.
    $tenant->update(['photo_path' => null]);
    expect($tenant->fresh()->photo_size)->toBeNull();
});

it('shows each account total uploaded disk usage on the accounts index', function () {
    Storage::fake('public');
    $superadmin = makeSuperadminForUsage();
    $admin = makeAdmin(['status' => 'active', 'name' => 'Acme Rentals']);

    // Create the tenant as the admin so BelongsToAccount stamps its account_id
    // (account_id is not mass-assignable).
    $this->actingAs($admin);
    makeTenant(null, [
        'phone' => '555-2002',
        'photo_path' => fakeFileOfBytes('photo.jpg', 40 * 1024, 'tenants'),
        'document_path' => fakeFileOfBytes('id.pdf', 60 * 1024, 'tenants/id_documents'),
    ]);

    // 40 KB + 60 KB = 100 KB → format_bytes() renders "100.0 KB".
    $this->actingAs($superadmin)
        ->get(route('superadmin.accounts.index'))
        ->assertOk()
        ->assertSee('Acme Rentals')
        ->assertSee('100.0 KB');
});
