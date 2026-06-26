<?php

use App\Models\Accounts;
use App\Models\BusinessExpense;
use App\Models\Property;
use App\Services\RevenueExpense\ExpenseRecordingService;

/**
 * Business (overhead) expenses are part of a property's P&L: each one belongs to
 * the property it was recorded under, and an account-wide one (null property_id,
 * recorded under "All properties") stays visible under every property — mirroring
 * the Accounts ledger convention.
 */
it('stamps the active property on a recorded business expense and its mirror ledger row', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    $fp = makeFiscalPeriod($admin);
    $propA = Property::create(['name' => 'A']);

    $service = new ExpenseRecordingService(userId: $admin->id, period: $fp, propertyId: $propA->id);
    $expense = $service->recordBusinessExpense([
        'expense_name' => 'Insurance',
        'category' => 'insurance',
        'amount' => 300,
        'expense_date' => now()->toDateString(),
    ]);

    expect($expense->property_id)->toBe($propA->id);

    // The mirror ledger row carries the same property so the dashboard agrees.
    $mirror = Accounts::where('description', '[Business] Insurance')->first();
    expect($mirror)->not->toBeNull()
        ->and($mirror->property_id)->toBe($propA->id)
        ->and($mirror->category)->toBe(Accounts::CAT_BUSINESS_VARIABLE);
});

it('scopes business expenses by property, keeping account-wide ones visible everywhere', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    $fp = makeFiscalPeriod($admin);
    $propA = Property::create(['name' => 'A']);
    $propB = Property::create(['name' => 'B']);

    $make = fn (?Property $p, string $name, float $amount) => BusinessExpense::create([
        'user_id' => $admin->id, 'fiscal_period_id' => $fp->id, 'property_id' => $p?->id,
        'expense_name' => $name, 'category' => 'salary', 'amount' => $amount,
        'expense_date' => now()->toDateString(), 'billing_month' => now()->month, 'billing_year' => now()->year,
    ]);

    $make($propA, 'A salary', 100);
    $make($propB, 'B salary', 200);
    $make(null, 'Shared software', 50); // account-wide

    // Each property: its own overhead + the account-wide row.
    expect((float) BusinessExpense::forProperty($propA->id)->sum('amount'))->toBe(150.0)
        ->and((float) BusinessExpense::forProperty($propB->id)->sum('amount'))->toBe(250.0);

    // A supervisor across A + B: A + B + account-wide, never another property's.
    expect((float) BusinessExpense::forProperties([$propA->id, $propB->id])->sum('amount'))->toBe(350.0);

    // A null/empty scope is a no-op (consolidated = everything).
    expect((float) BusinessExpense::query()->forProperty(null)->sum('amount'))->toBe(350.0)
        ->and((float) BusinessExpense::query()->forProperties([])->sum('amount'))->toBe(350.0);
});
