<?php

use App\Models\Accounts;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Property;
use App\Services\Dashboard\DashboardStatsService;
use Carbon\Carbon;

/** A Property owned by $admin's account (account_id is guarded, set as attribute). */
function makeOwnedProperty(\App\Models\User $admin, string $name): Property
{
    $property = new Property(['name' => $name]);
    $property->account_id = $admin->id;
    $property->save();

    return $property;
}

it('derives a ledger row\'s property from its linked payment when not set', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    $property = makeOwnedProperty($admin, 'Home1');
    $floor = Floors::create(['floor_name' => 'F1', 'property_id' => $property->id]);
    $apartment = makeApartment($floor, ['status' => 'occupied']);
    $tenant = makeTenant($apartment);
    $rental = makeRental($tenant, $apartment);
    $payment = Payments::create([
        'rental_id' => $rental->id,
        'amount' => 500,
        'due_date' => now(),
        'paid_at' => now(),
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'payment_type' => 'rent',
        'late_fee' => 0,
    ]);

    // No property_id supplied — the creating hook must derive it from the payment.
    $account = Accounts::create([
        'fiscal_period_id' => makeFiscalPeriod($admin)->id,
        'payment_id' => $payment->id,
        'user_id' => $admin->id,
        'account_type' => Accounts::TYPE_INCOME,
        'category' => Accounts::CAT_RENT_INCOME,
        'description' => 'Rent',
        'amount' => 500,
        'transaction_date' => now()->toDateString(),
    ]);

    expect($account->property_id)->toBe($property->id)
        ->and($account->fresh()->property_id)->toBe($property->id);
});

it('keeps a property\'s deposit income out of another property\'s dashboard', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    $home1 = makeOwnedProperty($admin, 'Home1');
    $home2 = makeOwnedProperty($admin, 'Home2');
    $floor1 = Floors::create(['floor_name' => 'F1', 'property_id' => $home1->id]);
    $floor2 = Floors::create(['floor_name' => 'F2', 'property_id' => $home2->id]);
    $apt1 = makeApartment($floor1, ['status' => 'occupied']);
    makeApartment($floor2, ['status' => 'occupied']);

    // A deposit income (payment-less) booked against Home1's apartment.
    Accounts::create([
        'fiscal_period_id' => makeFiscalPeriod($admin)->id,
        'property_id' => $apt1->property_id ?? $floor1->property_id,
        'payment_id' => null,
        'user_id' => $admin->id,
        'account_type' => Accounts::TYPE_INCOME,
        'category' => Accounts::CAT_DEPOSIT_INCOME,
        'description' => 'Security deposit — Apt 1',
        'amount' => 250,
        'transaction_date' => now()->toDateString(),
        'reference_number' => 'deposit:test:home1',
    ]);

    $start = Carbon::now()->startOfYear();
    $end = Carbon::now()->endOfYear();
    $ref = Carbon::now();

    $s1 = (new DashboardStatsService($admin->id, null, $home1->id))->build($start->copy(), $end->copy(), $ref->copy());
    $s2 = (new DashboardStatsService($admin->id, null, $home2->id))->build($start->copy(), $end->copy(), $ref->copy());

    expect($s1['revenue']['by_type']['deposit'])->toBe(250.0)
        ->and($s2['revenue']['by_type']['deposit'])->toBe(0.0)
        ->and($s1['apartments']['occupied'])->toBe(1)
        ->and($s2['apartments']['occupied'])->toBe(1); // each property has its own occupied unit
});
