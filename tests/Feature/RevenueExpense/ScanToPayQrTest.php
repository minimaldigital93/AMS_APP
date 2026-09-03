<?php

use App\Models\MerchantPaymentSetting;
use App\Models\Payments;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The account's static KHQR is uploaded once on System Settings and printed
 * under the tenant's bill so they can scan and pay from the paper.
 *
 * It rides the column the manual checkout channel already uses
 * (merchant_payment_settings.khqr_image_path) — there is no second place a
 * scan-to-pay QR lives, and AccountPurgeService already deletes that file.
 *
 * It prints only where there is still money to collect: a receipt is money
 * already received, so it never carries one. The account name typed beside it
 * rides bank_account_name — the same field the manual channel signs its KHQR
 * payload with — and prints under the QR so the tenant knows whose account
 * they are paying into before they scan.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-08-20');
    Storage::fake('public');

    $this->admin = makeAdmin();
    auth()->login($this->admin);
    makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 300]);
    $this->tenant = makeTenant($this->apartment, ['move_in_date' => '2026-08-01']);
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 300,
        'start_date' => '2026-08-01',
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

/** The System Settings form saves in one submit — the QR rides along with it. */
function saveQrSettings(array $payload = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->admin)->put(route('admin.settings.updateBatch'), array_merge([
        'settings' => ['company_name' => 'Acme Rentals'],
    ], $payload));
}

function storedQrPath(): ?string
{
    return MerchantPaymentSetting::forAccount(test()->admin->id)?->khqr_image_path;
}

function billSummary(): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->admin)->get(route('admin.revenue_expense.print_receipt', [
        'rental' => test()->rental->id,
        'month' => 8,
        'year' => 2026,
    ]));
}

it('renders the upload card on the system settings page', function () {
    test()->actingAs($this->admin)->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee(__('messages.payment_qr_code'))
        ->assertSee('name="khqr_image"', false)
        ->assertSee('name="khqr_account_name"', false);
});

it('shows the stored QR back on the settings page', function () {
    saveQrSettings(['khqr_image' => UploadedFile::fake()->image('khqr.png')]);

    test()->actingAs($this->admin)->get(route('admin.settings.index'))
        ->assertOk()
        // @json() escapes the slashes, so the file name is what to look for.
        ->assertSee(basename(storedQrPath()), false);
});

it('stores the account name typed beside the QR', function () {
    saveQrSettings([
        'khqr_image' => UploadedFile::fake()->image('khqr.png'),
        'khqr_account_name' => '  SOK DARA  ',
    ]);

    expect(MerchantPaymentSetting::forAccount($this->admin->id)->bank_account_name)->toBe('SOK DARA');
});

it('clears the account name when the field is submitted empty', function () {
    saveQrSettings(['khqr_account_name' => 'SOK DARA']);
    saveQrSettings(['khqr_account_name' => '']);

    expect(MerchantPaymentSetting::forAccount($this->admin->id)->bank_account_name)->toBeNull();
});

it('leaves the account name alone when a form does not carry the field', function () {
    saveQrSettings(['khqr_account_name' => 'SOK DARA']);

    // The Payment Settings form has no account-name field — saving it must not
    // blank what was typed on System Settings.
    test()->actingAs($this->admin)->put(route('admin.settings.payment.update'), ['currency' => 'USD']);
    saveQrSettings(['khqr_image' => UploadedFile::fake()->image('khqr.png')]);

    expect(MerchantPaymentSetting::forAccount($this->admin->id)->bank_account_name)->toBe('SOK DARA');
});

it('prints the account name under the QR on an unpaid bill', function () {
    saveQrSettings([
        'khqr_image' => UploadedFile::fake()->image('khqr.png'),
        'khqr_account_name' => 'SOK DARA',
    ]);

    billSummary()->assertOk()->assertSeeInOrder([
        __('messages.scan_to_pay'),
        'SOK DARA',
    ], false);
});

it('still prints a QR that has no account name', function () {
    saveQrSettings(['khqr_image' => UploadedFile::fake()->image('khqr.png')]);

    billSummary()->assertOk()->assertSee(__('messages.scan_to_pay'));
});

it('stores the uploaded QR on the account merchant row', function () {
    saveQrSettings(['khqr_image' => UploadedFile::fake()->image('khqr.png')])
        ->assertRedirect(route('admin.settings.index'));

    $path = storedQrPath();

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('keeps the stored QR when the form is saved with no new file', function () {
    saveQrSettings(['khqr_image' => UploadedFile::fake()->image('khqr.png')]);
    $path = storedQrPath();

    saveQrSettings(['settings' => ['company_name' => 'Acme Rentals', 'company_phone' => '012345678']]);

    expect(storedQrPath())->toBe($path);
    Storage::disk('public')->assertExists($path);
});

it('replaces the previous file on re-upload rather than leaving it behind', function () {
    saveQrSettings(['khqr_image' => UploadedFile::fake()->image('one.png')]);
    $first = storedQrPath();

    saveQrSettings(['khqr_image' => UploadedFile::fake()->image('two.png')]);

    expect(storedQrPath())->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists(storedQrPath());
});

it('clears the QR and deletes the file when removal is ticked', function () {
    saveQrSettings(['khqr_image' => UploadedFile::fake()->image('khqr.png')]);
    $path = storedQrPath();

    saveQrSettings(['remove_khqr_image' => '1']);

    expect(storedQrPath())->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rejects a non-image upload', function () {
    saveQrSettings(['khqr_image' => UploadedFile::fake()->create('qr.pdf', 20, 'application/pdf')])
        ->assertSessionHasErrors('khqr_image');

    expect(storedQrPath())->toBeNull();
});

it('prints the QR under a bill that still owes money', function () {
    saveQrSettings(['khqr_image' => UploadedFile::fake()->image('khqr.png')]);

    billSummary()
        ->assertOk()
        ->assertSee('storage/'.storedQrPath(), false)
        ->assertSee(__('messages.scan_to_pay'));
});

it('prints nothing when no QR has been uploaded', function () {
    billSummary()->assertOk()->assertDontSee(__('messages.scan_to_pay'));
});

it('leaves the QR off a receipt for money already received', function () {
    saveQrSettings(['khqr_image' => UploadedFile::fake()->image('khqr.png')]);

    $payment = Payments::create([
        'rental_id' => $this->rental->id,
        'amount' => 300,
        'late_fee' => 0,
        'payment_type' => 'rent',
        'due_date' => Carbon::parse('2026-08-05'),
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'paid_at' => Carbon::parse('2026-08-05'),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.print_receipt', [
            'rental' => $this->rental->id,
            'month' => 8,
            'year' => 2026,
            'payment' => $payment->id,
        ]))
        ->assertOk()
        ->assertDontSee(__('messages.scan_to_pay'));
});
