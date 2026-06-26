<?php

use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\Property;
use App\Services\RevenueExpense\RevenueExpenseQueryService;

/**
 * A supervisor's consolidated ("All properties") revenue/expense view must stay
 * within the buildings they are assigned to — it must never spill into another
 * of the admin's properties. RevenueExpenseQueryService enforces this through the
 * propertyId (a single selection) / propertyIds (a bounded set) it is handed:
 * the admin passes the active property, the supervisor additionally passes their
 * assigned-property ids so the consolidated view can't reach property C.
 */
function ledgerScopeFixture(): array
{
    $admin = makeAdmin();

    $fp = makeFiscalPeriod($admin);
    $propA = Property::create(['name' => 'A']);
    $propB = Property::create(['name' => 'B']);
    $propC = Property::create(['name' => 'C']);

    $income = function (?Property $p, float $amount, string $cat = Accounts::CAT_RENT_INCOME) use ($admin, $fp) {
        Accounts::create([
            'fiscal_period_id' => $fp->id, 'user_id' => $admin->id, 'property_id' => $p?->id,
            'account_type' => Accounts::TYPE_INCOME, 'category' => $cat,
            'description' => 'Income', 'amount' => $amount, 'transaction_date' => now()->toDateString(),
        ]);
    };

    $income($propA, 1000);
    $income($propB, 600);
    $income($propC, 300);
    $income(null, 100, Accounts::CAT_OTHER_INCOME); // account-wide — visible everywhere

    // A property-C expense that must never reach an A/B supervisor.
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $admin->id, 'property_id' => $propC->id,
        'account_type' => Accounts::TYPE_EXPENSE, 'category' => Accounts::CAT_UTILITIES_EXPENSE,
        'description' => 'C water', 'amount' => 250, 'transaction_date' => now()->toDateString(),
    ]);

    return compact('admin', 'fp', 'propA', 'propB', 'propC');
}

function ledgerService(array $f, ?int $propertyId, ?array $propertyIds): RevenueExpenseQueryService
{
    return new RevenueExpenseQueryService(
        userId: $f['admin']->id,
        period: $f['fp'],
        apartmentsScope: Apartments::query(),
        propertyId: $propertyId,
        propertyIds: $propertyIds,
    );
}

it('bounds a supervisor consolidated ledger to their assigned properties', function () {
    $f = ledgerScopeFixture();

    // "All properties" for a supervisor assigned to A + B: A + B + account-wide,
    // excluding C entirely.
    $income = ledgerService($f, null, [$f['propA']->id, $f['propB']->id])->calculateIncome();
    expect($income['total_income'])->toBe(1700.0);
});

it('narrows the ledger to a single selected property plus account-wide rows', function () {
    $f = ledgerScopeFixture();

    $income = ledgerService($f, $f['propA']->id, [$f['propA']->id, $f['propB']->id])->calculateIncome();
    expect($income['total_income'])->toBe(1100.0); // A (1000) + account-wide (100)
});

it('aggregates every property for an admin with no property bounds', function () {
    $f = ledgerScopeFixture();

    $income = ledgerService($f, null, null)->calculateIncome();
    expect($income['total_income'])->toBe(2000.0); // A + B + C + account-wide

    // A property-C expense never leaks into an A/B consolidated view.
    $expenses = ledgerService($f, null, [$f['propA']->id, $f['propB']->id])->calculateExpenses();
    expect($expenses['total_expenses'])->toBe(0.0);
});
