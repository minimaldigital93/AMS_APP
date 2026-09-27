<?php

use App\Models\Accounts;
use App\Models\AuditLog;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\TenantLeave;
use App\Models\Tenants;
use App\Models\Utilities;
use Carbon\Carbon;

/**
 * A move-out settles everything the tenant owes, or records what it forgave.
 *
 * The owed set is derived server-side (TenantLeaveProcessor::owed()): unpaid
 * rent for every month before the leave month, and every open bill on any of
 * the tenant's rentals. The form lists it all ticked; anything left out is a
 * write-off and needs a reason, kept on the leave row and in the audit log.
 * The leave month's rent is not charged again when it is already paid.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-20');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);

    $this->apartment = makeApartment(null, ['apartment_number' => 'B-12', 'status' => 'occupied', 'monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment, ['move_in_date' => '2026-06-01', 'deposit' => 0]);
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 500,
        'deposit' => 0,
        'start_date' => '2026-06-01',
    ]);

    // June and August rent were paid; July was not.
    foreach (['2026-06-25', '2026-08-25'] as $paidAt) {
        Payments::create([
            'rental_id' => $this->rental->id, 'amount' => 500, 'due_date' => $paidAt,
            'paid_at' => $paidAt, 'payment_method' => 'cash', 'payment_status' => 'paid',
            'payment_type' => 'rent', 'late_fee' => 0,
        ]);
    }

    $this->bill = Utilities::create([
        'tenant_id' => $this->tenant->id, 'rental_id' => $this->rental->id, 'utility_type' => 'electricity',
        'charge_amount' => 40, 'billing_month' => 8, 'billing_year' => 2026, 'paid_status' => false,
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

function leaveAs($user, Tenants $tenant, array $payload, string $panel = 'admin')
{
    return test()->actingAs($user)->post(route($panel.'.tenants.processLeave', $tenant), $payload + [
        'leave_date' => '2026-09-20',
        'charge_full_month' => false,
    ]);
}

it('refuses a move-out that leaves owed money out without a reason', function () {
    leaveAs($this->admin, $this->tenant, [])
        ->assertSessionHasErrors('write_off_reason');

    expect(Tenants::find($this->tenant->id))->not->toBeNull()
        ->and(TenantLeave::count())->toBe(0)
        ->and(Payments::where('payment_type', 'rent')->count())->toBe(2);
});

it('collects unpaid rent from earlier months into that month', function () {
    leaveAs($this->admin, $this->tenant, [
        'rent_months' => ['2026-07'],
        'charge_ids' => ['utility_'.$this->bill->id],
    ])->assertRedirect(route('admin.tenants.archived'));

    // July's rent is anchored in July, so July reads paid everywhere.
    $july = Payments::where('payment_type', 'rent')
        ->whereYear('paid_at', 2026)->whereMonth('paid_at', 7)->get();
    expect($july)->toHaveCount(1)
        ->and((float) $july->first()->amount)->toBe(500.0);

    // Income is recognised on the leave date, in the open period.
    $income = Accounts::where('payment_id', $july->first()->id)->first();
    expect($income->category)->toBe(Accounts::CAT_RENT_INCOME)
        ->and($income->transaction_date->toDateString())->toBe('2026-09-20');

    $leave = TenantLeave::first();
    expect($leave->arrears_rent)->toBe(500.0)
        ->and($leave->written_off_amount)->toBe(0.0)
        ->and($leave->write_off_reason)->toBeNull()
        ->and($this->bill->fresh()->paid_status)->toBeTruthy()
        // 20 days of September pro-rata + July + the electricity bill.
        ->and($leave->total_amount_due)->toEqualWithDelta(round(20 * 500 / 30, 2) + 500 + 40, 0.001);
});

it('records a write-off with its reason, items and an audit entry', function () {
    leaveAs($this->admin, $this->tenant, [
        'rent_months' => [],
        'charge_ids' => [],
        'write_off_reason' => 'Tenant left the country',
    ])->assertRedirect(route('admin.tenants.archived'));

    $leave = TenantLeave::first();
    expect($leave->written_off_amount)->toBe(540.0)
        ->and($leave->write_off_reason)->toBe('Tenant left the country')
        ->and(collect($leave->written_off_items)->pluck('amount')->sum())->toBe(540)
        ->and(collect($leave->written_off_items)->pluck('kind')->all())->toBe(['rent', 'charge'])
        ->and($leave->arrears_rent)->toBe(0.0);

    // Forgiven money is not booked as income.
    expect(Payments::where('payment_type', 'rent')->whereMonth('paid_at', 7)->count())->toBe(0)
        ->and($this->bill->fresh()->paid_status)->toBeFalsy();

    $audit = AuditLog::where('action', 'tenant.leave.written_off')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->context['amount'])->toEqual(540)
        ->and($audit->context['reason'])->toBe('Tenant left the country');
});

it('does not charge the leave month again when its rent is already paid', function () {
    Payments::create([
        'rental_id' => $this->rental->id, 'amount' => 500, 'due_date' => '2026-09-05',
        'paid_at' => '2026-09-05', 'payment_method' => 'cash', 'payment_status' => 'paid',
        'payment_type' => 'rent', 'late_fee' => 0,
    ]);

    leaveAs($this->admin, $this->tenant, [
        'rent_months' => ['2026-07'],
        'charge_ids' => ['utility_'.$this->bill->id],
        'charge_full_month' => true,
    ])->assertRedirect(route('admin.tenants.archived'));

    expect(TenantLeave::first()->pro_rata_rent)->toBe(0.0)
        ->and(Payments::where('payment_type', 'rent')->whereMonth('paid_at', 9)->count())->toBe(1);
});

it('ignores a charge id that is not this tenant\'s', function () {
    auth()->login($this->admin);
    $otherApartment = makeApartment(null, ['status' => 'occupied']);
    $other = makeTenant($otherApartment);
    $otherRental = makeRental($other, $otherApartment, ['start_date' => '2026-09-01']);
    $otherBill = Utilities::create([
        'tenant_id' => $other->id, 'rental_id' => $otherRental->id, 'utility_type' => 'water',
        'charge_amount' => 15, 'billing_month' => 9, 'billing_year' => 2026, 'paid_status' => false,
    ]);
    auth()->logout();

    leaveAs($this->admin, $this->tenant, [
        'rent_months' => ['2026-07'],
        'charge_ids' => ['utility_'.$this->bill->id, 'utility_'.$otherBill->id],
    ])->assertRedirect(route('admin.tenants.archived'));

    expect($otherBill->fresh()->paid_status)->toBeFalsy();
});

it('does not count months paid on an earlier room as arrears', function () {
    // Room change: the new rental restarts at the original move-in date, but
    // June and August were paid against the OLD rental.
    auth()->login($this->admin);
    $newApartment = makeApartment(null, ['status' => 'occupied', 'monthly_rent' => 500]);
    $this->rental->update(['end_date' => '2026-09-01']);
    Rentals::create([
        'apartment_id' => $newApartment->id, 'tenant_id' => $this->tenant->id,
        'start_date' => '2026-06-01', 'end_date' => null, 'rent_amount' => 500, 'deposit' => 0,
    ]);
    $this->tenant->update(['apartment_id' => $newApartment->id]);
    auth()->logout();

    $owed = app(\App\Services\Tenants\TenantLeaveProcessor::class)->owed(
        $this->tenant->fresh(),
        Rentals::where('apartment_id', $newApartment->id)->first(),
        Carbon::parse('2026-09-20'),
    );

    expect($owed['rent_months']->pluck('key')->all())->toBe(['2026-07'])
        // The old room's open bill is still owed.
        ->and($owed['charges']->pluck('id')->all())->toBe(['utility_'.$this->bill->id]);
});

it('books the arrears the same way from the supervisor panel', function () {
    leaveAs($this->admin, $this->tenant, [
        'rent_months' => ['2026-07'],
        'charge_ids' => ['utility_'.$this->bill->id],
    ], 'supervisor')->assertRedirect(route('supervisor.tenants.archived'));

    expect(Payments::where('payment_type', 'rent')->whereMonth('paid_at', 7)->count())->toBe(1)
        ->and(TenantLeave::first()->arrears_rent)->toBe(500.0);
});

it('lists the unpaid rent and bills on the leave form, all ticked', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.tenants.leave', $this->tenant))->assertOk();

    $response->assertSee(__('messages.leave_unpaid_rent'))
        ->assertSee('"key":"2026-07"', false)
        // Every unpaid month (June and August were paid); the page narrows
        // them to the ones before the picked leave date.
        ->assertSee('selectedMonths: ["2026-07","2026-09"', false)
        ->assertSee('selectedCharges: ["utility_'.$this->bill->id.'"]', false);
});
