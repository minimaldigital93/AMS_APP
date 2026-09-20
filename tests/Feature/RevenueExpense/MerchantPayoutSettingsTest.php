<?php

use App\Models\MerchantPaymentSetting;
use Illuminate\Support\Facades\Http;

/**
 * Settings → Payment Settings, the landlord's side.
 *
 * The khqr.cc credential fields that were this whole page are gone. What
 * replaced them is what the surviving channel actually needs — where rent lands
 * — and until now there was nowhere in the UI to set any of it: the bank
 * columns existed on the model and were never writable, and bakong_account_id,
 * which is what makes a per-tenant exact-amount QR possible at all, had no
 * field either.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
});

it('lets a landlord say where rent should land', function () {
    $this->actingAs($this->admin)->get(route('admin.settings.payment'))->assertOk();

    $this->actingAs($this->admin)
        ->put(route('admin.settings.payment.update'), [
            'bakong_account_id' => 'landlord@aclb',
            'bank_name' => 'ACLEDA',
            'bank_account_name' => 'Landlord One',
            'bank_account_number' => '000-111-222',
            'currency' => 'USD',
        ])
        ->assertRedirect(route('admin.settings.payment'));

    $row = MerchantPaymentSetting::forAccount($this->admin->id);

    expect($row->bakong_account_id)->toBe('landlord@aclb')
        ->and($row->bank_name)->toBe('ACLEDA')
        ->and($row->bank_account_number)->toBe('000-111-222')
        ->and($row->currency)->toBe('USD');
});

it('offers no khqr.cc credential fields, and never calls out to save', function () {
    Http::fake();

    $page = $this->actingAs($this->admin)->get(route('admin.settings.payment'))->assertOk();

    $page->assertDontSee('khqrpay_profile_id')
        ->assertDontSee('khqrpay_secret')
        ->assertDontSee('khqrpay_enabled')
        // The workflow is stated rather than left to be discovered: nobody but
        // the landlord can see rent arrive in their own bank, so nobody but the
        // landlord can confirm it.
        ->assertSee(__('messages.rent_manual_confirm_title'));

    $this->actingAs($this->admin)->put(route('admin.settings.payment.update'), [
        'bakong_account_id' => 'landlord@aclb',
        'currency' => 'USD',
    ])->assertRedirect();

    Http::assertNothingSent();
});

it('keeps each account\'s payout details to itself', function () {
    $other = makeAdmin();

    $this->actingAs($this->admin)->put(route('admin.settings.payment.update'), [
        'bakong_account_id' => 'first@aclb', 'currency' => 'USD',
    ])->assertRedirect();

    $this->actingAs($other)->put(route('admin.settings.payment.update'), [
        'bakong_account_id' => 'second@aclb', 'currency' => 'KHR',
    ])->assertRedirect();

    // Two landlords, two payout accounts. A leak here would send one
    // building's rent into another's bank.
    expect(MerchantPaymentSetting::forAccount($this->admin->id)->bakong_account_id)->toBe('first@aclb')
        ->and(MerchantPaymentSetting::forAccount($other->id)->bakong_account_id)->toBe('second@aclb');
});

it('offers KHQR at checkout only once the account can actually collect by it', function () {
    $period = makeFiscalPeriod($this->admin);
    $apartment = makeApartment(null, ['apartment_number' => 'C-1', 'monthly_rent' => 400]);
    $tenant = makeTenant($apartment);
    makeRental($tenant, $apartment, ['rent_amount' => 400])
        ->forceFill(['account_id' => $this->admin->id])->save();

    // Nothing configured: the collector must not be able to pick KHQR, wait for
    // a QR, and get a 502 about missing settings — with cash sitting right
    // beside it the whole time.
    $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income'))
        ->assertOk()
        ->assertDontSee('value="khqr"', false);

    $settings = new MerchantPaymentSetting(['bakong_account_id' => 'landlord@aclb', 'currency' => 'USD']);
    $settings->account_id = $this->admin->id;
    $settings->save();

    $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income'))
        ->assertOk()
        ->assertSee('value="khqr"', false);
});
