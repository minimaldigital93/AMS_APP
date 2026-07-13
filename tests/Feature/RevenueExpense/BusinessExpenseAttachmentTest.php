<?php

use App\Models\Attachment;
use App\Models\BusinessExpense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Business expenses now support multiple attachments (PDF or photo, up to
 * 10MB each, max 3 files) via the polymorphic `attachments` table instead of
 * the old single `attachment` column.
 */
function storeBusinessExpensePayload(array $overrides = []): array
{
    return array_merge([
        'expense_name' => 'Insurance',
        'category' => 'other',
        'amount' => 300,
        'expense_date' => now()->toDateString(),
    ], $overrides);
}

it('stores multiple attachments on a business expense', function () {
    Storage::fake(\App\Models\Attachment::DISK);
    $admin = makeAdmin();
    $this->actingAs($admin);
    makeFiscalPeriod($admin);

    $this->post(route('admin.revenue_expense.store_business_expense'), storeBusinessExpensePayload([
        'attachments' => [
            UploadedFile::fake()->create('receipt1.pdf', 500, 'application/pdf'),
            UploadedFile::fake()->image('receipt2.jpg', 100, 100),
        ],
    ]))->assertRedirect();

    $expense = BusinessExpense::firstOrFail();
    expect($expense->attachments)->toHaveCount(2);
});

it('rejects an oversized attachment', function () {
    Storage::fake(\App\Models\Attachment::DISK);
    $admin = makeAdmin();
    $this->actingAs($admin);
    makeFiscalPeriod($admin);

    $this->post(route('admin.revenue_expense.store_business_expense'), storeBusinessExpensePayload([
        'attachments' => [UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')],
    ]))->assertSessionHasErrors('attachments.0');

    expect(BusinessExpense::count())->toBe(0);
});

it('rejects more than the max file count', function () {
    Storage::fake(\App\Models\Attachment::DISK);
    $admin = makeAdmin();
    $this->actingAs($admin);
    makeFiscalPeriod($admin);

    $this->post(route('admin.revenue_expense.store_business_expense'), storeBusinessExpensePayload([
        'attachments' => [
            UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('c.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('d.pdf', 100, 'application/pdf'),
        ],
    ]))->assertSessionHasErrors('attachments');
});

it('deletes a single attachment without touching the others', function () {
    Storage::fake(\App\Models\Attachment::DISK);
    $admin = makeAdmin();
    $this->actingAs($admin);
    $fp = makeFiscalPeriod($admin);

    $expense = BusinessExpense::create([
        'user_id' => $admin->id,
        'fiscal_period_id' => $fp->id,
        'expense_name' => 'Insurance',
        'category' => 'insurance',
        'amount' => 300,
        'expense_date' => now()->toDateString(),
        'billing_month' => now()->month,
        'billing_year' => now()->year,
    ]);

    $kept = $expense->attachments()->create([
        'kind' => Attachment::KIND_BUSINESS_EXPENSE,
        'path' => UploadedFile::fake()->create('kept.pdf', 100)->store('business_expenses', \App\Models\Attachment::DISK),
        'original_name' => 'kept.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100 * 1024,
    ]);
    $removed = $expense->attachments()->create([
        'kind' => Attachment::KIND_BUSINESS_EXPENSE,
        'path' => UploadedFile::fake()->create('removed.pdf', 100)->store('business_expenses', \App\Models\Attachment::DISK),
        'original_name' => 'removed.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100 * 1024,
    ]);

    $this->delete(route('admin.revenue_expense.delete_business_expense_attachment', [$expense, $removed]))
        ->assertRedirect();

    Storage::disk(\App\Models\Attachment::DISK)->assertMissing($removed->path);
    Storage::disk(\App\Models\Attachment::DISK)->assertExists($kept->path);
    expect(Attachment::find($removed->id))->toBeNull()
        ->and(Attachment::find($kept->id))->not->toBeNull();
});

it('prevents deleting another accounts attachment', function () {
    Storage::fake(\App\Models\Attachment::DISK);
    $adminA = makeAdmin();
    $this->actingAs($adminA);
    $fpA = makeFiscalPeriod($adminA);

    $expense = BusinessExpense::create([
        'user_id' => $adminA->id,
        'fiscal_period_id' => $fpA->id,
        'expense_name' => 'Insurance',
        'category' => 'insurance',
        'amount' => 300,
        'expense_date' => now()->toDateString(),
        'billing_month' => now()->month,
        'billing_year' => now()->year,
    ]);
    $attachment = $expense->attachments()->create([
        'kind' => Attachment::KIND_BUSINESS_EXPENSE,
        'path' => UploadedFile::fake()->create('receipt.pdf', 100)->store('business_expenses', \App\Models\Attachment::DISK),
        'original_name' => 'receipt.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100 * 1024,
    ]);

    $adminB = makeAdmin(['name' => 'Other Admin']);
    $this->actingAs($adminB);

    $this->delete(route('admin.revenue_expense.delete_business_expense_attachment', [$expense, $attachment]))
        ->assertNotFound();

    // Query without the account scope: acting as adminB, a scoped lookup would
    // return null regardless of whether the row still exists.
    expect(Attachment::withoutAccountScope()->find($attachment->id))->not->toBeNull();
});
