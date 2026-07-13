<?php

use App\Models\Accounts;
use App\Models\ApartmentFixedExpense;
use App\Models\Apartments;
use App\Models\Attachment;
use App\Models\BalanceSheet;
use App\Models\BusinessExpense;
use App\Models\FiscalPeriods;
use App\Models\Floors;
use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Models\MonthlyPeriod;
use App\Models\Payments;
use App\Models\Property;
use App\Models\Rentals;
use App\Models\Settings;
use App\Models\TenantLeave;
use App\Models\Tenants;
use App\Models\User;
use App\Models\Utilities;
use Illuminate\Support\Facades\Storage;

function makeSuperadminForPurge(): User
{
    seedRoles();
    $user = User::factory()->create(['name' => 'Platform Owner']);
    $user->assignRole('superadmin');

    return $user;
}

/**
 * Build a complete customer account: people, buildings, occupancy, money and
 * uploaded files. Returns everything a purge must erase (and the platform
 * subscription-payment row it must NOT erase).
 */
function makeFullAccount(): array
{
    $admin = makeAdmin();
    auth()->login($admin); // stamp account_id via BelongsToAccount

    $period = makeFiscalPeriod($admin);
    $property = Property::create(['name' => 'Purged Tower']);
    $floor = Floors::create(['property_id' => $property->id, 'floor_name' => 'P1']);
    $room = Apartments::create(['floor_id' => $floor->id, 'apartment_number' => 'P101', 'monthly_rent' => 500, 'status' => 'occupied']);

    $supervisor = makeSupervisor(['account_id' => $admin->id]);
    $property->update(['supervisor_id' => $supervisor->id]);

    $tenantUser = User::factory()->create(['account_id' => $admin->id]);
    $tenantUser->assignRole('tenant');
    $tenant = makeTenant($room, ['user_id' => $tenantUser->id, 'photo_path' => 'tenants/photo-a.jpg']);
    Storage::disk('public')->put('tenants/photo-a.jpg', 'img');

    $rental = makeRental($tenant, $room);

    $payment = Payments::create([
        'rental_id' => $rental->id, 'amount' => 500, 'due_date' => now()->toDateString(),
        'paid_at' => now(), 'payment_status' => 'paid', 'payment_type' => 'rent',
    ]);
    Utilities::create([
        'tenant_id' => $tenant->id, 'rental_id' => $rental->id, 'utility_type' => 'electricity',
        'charge_amount' => 30, 'billing_month' => now()->month, 'billing_year' => now()->year,
    ]);
    TenantLeave::create([
        'tenant_id' => $tenant->id, 'rental_id' => $rental->id, 'apartment_id' => $room->id,
        'leave_date' => now()->toDateString(), 'stay_days' => 30,
        'total_amount_due' => 0, 'balance_due' => 0, 'status' => 'completed',
    ]);
    ApartmentFixedExpense::create([
        'apartment_id' => $room->id, 'expense_name' => 'Internet', 'expense_type' => 'internet', 'amount' => 10,
    ]);
    Accounts::create([
        'fiscal_period_id' => $period->id, 'user_id' => $admin->id, 'account_type' => 'income',
        'category' => 'rent_income', 'amount' => 500, 'transaction_date' => now()->toDateString(),
        'payment_id' => $payment->id,
    ]);
    BalanceSheet::create([
        'fiscal_period_id' => $period->id, 'user_id' => $admin->id, 'item_type' => 'asset',
        'sub_type' => 'cash', 'name' => 'Cash', 'amount' => 100, 'as_of_date' => now()->toDateString(),
    ]);
    MonthlyPeriod::create([
        'fiscal_period_id' => $period->id, 'user_id' => $admin->id, 'name' => 'M1',
        'month_number' => now()->month, 'year' => now()->year,
        'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString(),
    ]);
    BusinessExpense::create([
        'user_id' => $admin->id, 'fiscal_period_id' => $period->id, 'expense_name' => 'Rent office',
        'amount' => 50, 'expense_date' => now()->toDateString(),
        'billing_month' => now()->month, 'billing_year' => now()->year,
    ]);
    Settings::set('company_name', 'Purged Co');
    Settings::set('company_logo', 'logos/logo-a.png');
    Storage::disk('public')->put('logos/logo-a.png', 'png');

    Attachment::create([
        'attachable_type' => Tenants::class, 'attachable_id' => $tenant->id,
        'kind' => 'tenant_document', 'path' => 'tenants/documents/doc-a.pdf',
        'original_name' => 'doc.pdf', 'mime_type' => 'application/pdf', 'size' => 3,
    ]);
    Storage::disk(\App\Models\Attachment::DISK)->put('tenants/documents/doc-a.pdf', 'pdf');

    MerchantPaymentSetting::create(['account_id' => $admin->id, 'khqr_image_path' => 'khqr/merchant-a.png']);
    Storage::disk('public')->put('khqr/merchant-a.png', 'png');

    // Merchant-side tenant payment (must be purged with the account)…
    $tenantQr = KhqrPayment::create([
        'transaction_id' => 'TXN-TENANT-A', 'rental_id' => $rental->id, 'amount' => 500,
        'status' => 'paid', 'settlement_target' => 'merchant', 'checkout_payload' => [],
        'fiscal_period_id' => $period->id, 'user_id' => $admin->id,
    ]);
    // …and the platform's subscription-revenue row (must SURVIVE the purge).
    $platformQr = KhqrPayment::create([
        'transaction_id' => 'TXN-PLATFORM-A', 'subscription_id' => $admin->subscription->id,
        'amount' => 29, 'status' => 'paid', 'settlement_target' => 'platform', 'checkout_payload' => [],
    ]);

    auth()->logout();

    return compact('admin', 'supervisor', 'tenantUser', 'tenant', 'property', 'floor', 'room', 'rental', 'period', 'tenantQr', 'platformQr');
}

it('purges every row and file an account owns, keeps platform payment history, and leaves other accounts untouched', function () {
    Storage::fake('public');
    Storage::fake(\App\Models\Attachment::DISK);

    $a = makeFullAccount();

    // A second, unrelated account that must be untouched.
    $otherAdmin = makeAdmin();
    auth()->login($otherAdmin);
    $otherPeriod = makeFiscalPeriod($otherAdmin);
    $otherRoom = makeApartment();
    $otherTenant = makeTenant($otherRoom, ['phone' => '555-8888']);
    auth()->logout();

    $super = makeSuperadminForPurge();
    $this->actingAs($super)
        ->delete(route('superadmin.accounts.destroy', $a['admin']))
        ->assertRedirect(route('superadmin.accounts.index'));

    $id = $a['admin']->id;

    // Every account-owned table is empty for this account (including soft-deleted rows).
    expect(Property::withoutGlobalScopes()->withTrashed()->where('account_id', $id)->count())->toBe(0)
        ->and(Floors::withoutGlobalScopes()->withTrashed()->where('account_id', $id)->count())->toBe(0)
        ->and(Apartments::withoutGlobalScopes()->withTrashed()->where('account_id', $id)->count())->toBe(0)
        ->and(Tenants::withoutGlobalScopes()->withTrashed()->where('account_id', $id)->count())->toBe(0)
        ->and(Rentals::withoutGlobalScopes()->withTrashed()->where('account_id', $id)->count())->toBe(0)
        ->and(Payments::withoutGlobalScopes()->withTrashed()->where('account_id', $id)->count())->toBe(0)
        ->and(Utilities::withoutGlobalScopes()->where('account_id', $id)->count())->toBe(0)
        ->and(TenantLeave::withoutGlobalScopes()->withTrashed()->where('account_id', $id)->count())->toBe(0)
        ->and(ApartmentFixedExpense::withoutGlobalScopes()->where('account_id', $id)->count())->toBe(0)
        ->and(BusinessExpense::withoutGlobalScopes()->where('account_id', $id)->count())->toBe(0)
        ->and(FiscalPeriods::withoutGlobalScopes()->where('account_id', $id)->count())->toBe(0)
        ->and(Accounts::withoutGlobalScopes()->where('user_id', $id)->count())->toBe(0)
        ->and(BalanceSheet::withoutGlobalScopes()->where('user_id', $id)->count())->toBe(0)
        ->and(MonthlyPeriod::withoutGlobalScopes()->where('user_id', $id)->count())->toBe(0)
        ->and(Settings::withoutGlobalScopes()->where('account_id', $id)->count())->toBe(0)
        ->and(Attachment::withoutGlobalScopes()->where('account_id', $id)->count())->toBe(0)
        ->and(MerchantPaymentSetting::where('account_id', $id)->count())->toBe(0);

    // People: owner + members gone.
    expect(User::find($id))->toBeNull()
        ->and(User::find($a['supervisor']->id))->toBeNull()
        ->and(User::find($a['tenantUser']->id))->toBeNull();

    // Merchant-side tenant KHQR row purged; platform subscription revenue kept.
    expect(KhqrPayment::where('transaction_id', 'TXN-TENANT-A')->exists())->toBeFalse()
        ->and(KhqrPayment::where('transaction_id', 'TXN-PLATFORM-A')->exists())->toBeTrue();

    // Uploaded files removed from disk.
    Storage::disk('public')->assertMissing('tenants/photo-a.jpg');
    Storage::disk(\App\Models\Attachment::DISK)->assertMissing('tenants/documents/doc-a.pdf');
    Storage::disk('public')->assertMissing('khqr/merchant-a.png');
    Storage::disk('public')->assertMissing('logos/logo-a.png');

    // The unrelated account is intact.
    expect(User::find($otherAdmin->id))->not->toBeNull()
        ->and(FiscalPeriods::withoutGlobalScopes()->whereKey($otherPeriod->id)->exists())->toBeTrue()
        ->and(Tenants::withoutGlobalScopes()->whereKey($otherTenant->id)->exists())->toBeTrue()
        ->and(Apartments::withoutGlobalScopes()->whereKey($otherRoom->id)->exists())->toBeTrue();

    // The purge left an audit trail.
    $this->assertDatabaseHas('audit_logs', ['action' => 'account.purged']);
});

it('refuses to purge a superadmin or yourself', function () {
    $super = makeSuperadminForPurge();
    $otherSuper = makeSuperadminForPurge();

    $this->actingAs($super);
    $this->delete(route('superadmin.accounts.destroy', $super))->assertRedirect();
    $this->delete(route('superadmin.accounts.destroy', $otherSuper))->assertRedirect();

    expect(User::find($super->id))->not->toBeNull()
        ->and(User::find($otherSuper->id))->not->toBeNull();
});
