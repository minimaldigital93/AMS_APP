<?php

use App\Models\Apartments;
use App\Models\Attachment;
use App\Models\BusinessExpense;
use App\Models\Floors;
use App\Models\Property;
use App\Services\Attachments\AttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Attachment hardening: the supervisor property guard on receipt deletion, and
 * the failure ordering of AttachmentService (nothing half-saved, no dangling
 * row). Runs on Laravel's fake `local` disk.
 */
beforeEach(function () {
    Storage::fake('local');

    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->property = Property::create(['name' => 'Main']);
    $this->floor = Floors::create(['property_id' => $this->property->id, 'floor_name' => 'F1']);
    $this->room = Apartments::create(['floor_id' => $this->floor->id, 'apartment_number' => 'N-101', 'monthly_rent' => 300, 'status' => 'occupied']);
    $this->tenant = makeTenant($this->room);
    $this->service = app(AttachmentService::class);
});

function hardeningPdf(string $name = 'id-card.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 10, 'application/pdf');
}

function hardeningDoc($tenant, string $path = 'tenants/documents/a.pdf'): Attachment
{
    Storage::disk('local')->put($path, '%PDF-1.4 test');

    return $tenant->attachments()->create([
        'kind' => Attachment::KIND_TENANT_DOCUMENT,
        'path' => $path,
        'original_name' => 'id-card.pdf',
        'mime_type' => 'application/pdf',
        'size' => 13,
    ]);
}

it('stores files and rows together', function () {
    $stored = $this->service->storeMany($this->tenant, [hardeningPdf(), hardeningPdf('b.pdf')], Attachment::KIND_TENANT_DOCUMENT, 'tenants/documents');

    expect($stored)->toHaveCount(2);
    $stored->each(fn ($a) => Storage::disk('local')->assertExists($a->path));
});

it('removes the first file and creates no rows when a later insert fails', function () {
    $calls = 0;
    Attachment::creating(function () use (&$calls) {
        if (++$calls === 2) {
            throw new RuntimeException('simulated database failure');
        }
    });

    expect(fn () => $this->service->storeMany($this->tenant, [hardeningPdf(), hardeningPdf('b.pdf')], Attachment::KIND_TENANT_DOCUMENT, 'tenants/documents'))
        ->toThrow(RuntimeException::class);

    expect(Storage::disk('local')->allFiles('tenants/documents'))->toBe([])
        ->and(Attachment::withoutAccountScope()->count())->toBe(0);
});

it('deletes the row and then the file', function () {
    $doc = hardeningDoc($this->tenant);

    $this->service->delete($doc);

    Storage::disk('local')->assertMissing('tenants/documents/a.pdf');
    expect(Attachment::withoutAccountScope()->find($doc->id))->toBeNull();
});

it('does not delete a file when the row delete itself fails', function () {
    $doc = hardeningDoc($this->tenant, 'tenants/documents/safe.pdf');
    Attachment::deleting(function () {
        throw new RuntimeException('simulated database failure');
    });

    expect(fn () => $this->service->delete($doc))->toThrow(RuntimeException::class);

    Storage::disk('local')->assertExists('tenants/documents/safe.pdf');
    expect(Attachment::withoutAccountScope()->find($doc->id))->not->toBeNull();
});

it('stops a supervisor deleting a receipt on a property that is not theirs, but allows assigned and account-wide ones', function () {
    $this->actingAs($this->admin);
    $period = makeFiscalPeriod($this->admin);
    $supervisor = makeSupervisor(['account_id' => $this->admin->id]);
    $mine = Property::create(['name' => 'Mine', 'supervisor_id' => $supervisor->id]);

    $makeExpense = fn (?int $propertyId) => BusinessExpense::create([
        'user_id' => $this->admin->id,
        'fiscal_period_id' => $period->id,
        'property_id' => $propertyId,
        'expense_name' => 'Repair',
        'category' => 'repairs',
        'amount' => 50,
        'expense_date' => now()->toDateString(),
        'billing_month' => now()->month,
        'billing_year' => now()->year,
    ]);
    $makeReceipt = function (BusinessExpense $expense) {
        Storage::disk('local')->put($path = 'business_expenses/'.uniqid().'.pdf', 'x');

        return $expense->attachments()->create([
            'kind' => Attachment::KIND_BUSINESS_EXPENSE,
            'path' => $path,
            'original_name' => 'r.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1,
        ]);
    };

    $foreign = $makeReceipt($makeExpense($this->property->id)); // property the supervisor is NOT on
    $own = $makeReceipt($makeExpense($mine->id));
    $wide = $makeReceipt($makeExpense(null));

    $this->actingAs($supervisor)
        ->delete(route('supervisor.revenue_expense.delete_business_expense_attachment', [$foreign->attachable_id, $foreign->id]))
        ->assertForbidden();
    expect(Attachment::withoutAccountScope()->find($foreign->id))->not->toBeNull();
    Storage::disk('local')->assertExists($foreign->path);

    $this->actingAs($supervisor)
        ->delete(route('supervisor.revenue_expense.delete_business_expense_attachment', [$own->attachable_id, $own->id]))
        ->assertRedirect();
    $this->actingAs($supervisor)
        ->delete(route('supervisor.revenue_expense.delete_business_expense_attachment', [$wide->attachable_id, $wide->id]))
        ->assertRedirect();

    expect(Attachment::withoutAccountScope()->find($own->id))->toBeNull()
        ->and(Attachment::withoutAccountScope()->find($wide->id))->toBeNull();
});
