<?php

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\MonthlyPeriod;
use App\Models\Property;
use App\Models\User;
use App\Services\FiscalPeriod\FiscalPeriodFinancialsService;
use App\Services\FiscalPeriod\FiscalPeriodReportsService;

/**
 * Reports & exports can be scoped to a single property. The income statement,
 * cash flow and overview read the Accounts ledger filtered by property_id;
 * passing null aggregates every property (consolidated). Rows with a null
 * property_id (account-wide) stay visible under every property selection.
 */
function propertyFilterPeriod(User $user): array
{
    $fp = FiscalPeriods::create([
        'user_id' => $user->id,
        'name' => 'FY Property Filter',
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-01-31',
        'opening_balance' => 1000,
        'opening_assets' => 1000,
        'opening_liabilities' => 0,
        'opening_equity' => 1000,
        'closing_balance' => 0,
        'status' => 'open',
    ]);

    MonthlyPeriod::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'name' => 'January 2026', 'month_number' => 1, 'year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        'opening_balance' => 1000, 'closing_balance' => 0,
        'total_income' => 0, 'total_expenses' => 0, 'net_income' => 0,
        'status' => 'open',
    ]);

    $propA = Property::create(['name' => 'Property A']);
    $propB = Property::create(['name' => 'Property B']);

    // Property A: 1000 rent.
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id, 'property_id' => $propA->id,
        'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
        'description' => 'Rent A', 'amount' => 1000, 'transaction_date' => '2026-01-10',
    ]);
    // Property B: 600 rent.
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id, 'property_id' => $propB->id,
        'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
        'description' => 'Rent B', 'amount' => 600, 'transaction_date' => '2026-01-12',
    ]);
    // Account-wide (null property) income of 100 — visible under every property.
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id, 'property_id' => null,
        'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_OTHER_INCOME,
        'description' => 'Account-wide income', 'amount' => 100, 'transaction_date' => '2026-01-15',
    ]);
    // Property A expense of 200.
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id, 'property_id' => $propA->id,
        'account_type' => Accounts::TYPE_EXPENSE, 'category' => Accounts::CAT_UTILITIES_EXPENSE,
        'description' => 'Water A', 'amount' => 200, 'transaction_date' => '2026-01-20',
    ]);

    return [$fp, $propA, $propB];
}

it('scopes period revenue to the selected property plus account-wide rows', function () {
    $user = User::factory()->create();
    [$fp, $propA, $propB] = propertyFilterPeriod($user);

    $financials = app(FiscalPeriodFinancialsService::class);

    // Property A: 1000 rent + 100 account-wide = 1100 income; 200 expense; net 900.
    $a = $financials->forPeriod($fp, $propA->id);
    expect($a['total_income'])->toBe(1100.0)
        ->and($a['total_expenses'])->toBe(200.0)
        ->and($a['net_income'])->toBe(900.0);

    // Property B: 600 rent + 100 account-wide = 700 income; 0 expense; net 700.
    $b = $financials->forPeriod($fp, $propB->id);
    expect($b['total_income'])->toBe(700.0)
        ->and($b['total_expenses'])->toBe(0.0)
        ->and($b['net_income'])->toBe(700.0);
});

it('aggregates every property when no property is selected', function () {
    $user = User::factory()->create();
    [$fp] = propertyFilterPeriod($user);

    // Consolidated: 1000 + 600 + 100 = 1700 income; 200 expense; net 1500.
    $all = app(FiscalPeriodFinancialsService::class)->forPeriod($fp, null);
    expect($all['total_income'])->toBe(1700.0)
        ->and($all['total_expenses'])->toBe(200.0)
        ->and($all['net_income'])->toBe(1500.0);
});

it('filters the income statement and zeroes account-level cash flow figures per property', function () {
    $user = User::factory()->create();
    [$fp, $propA] = propertyFilterPeriod($user);

    $reports = app(FiscalPeriodReportsService::class);
    $months = $fp->monthlyPeriods()->orderBy('start_date')->get();

    $statement = $reports->incomeStatement($fp, $months, $propA->id);
    expect($statement['totals']['total_income'])->toBe(1100.0)
        ->and($statement['totals']['net_income'])->toBe(900.0);

    // Per-property cash flow excludes account-level opening balance & draws.
    $cash = $reports->cashFlow($fp, $months, $propA->id);
    expect($cash['is_consolidated'])->toBeFalse()
        ->and($cash['opening_balance'])->toBe(0.0)
        ->and($cash['total_cash_in'])->toBe(1100.0);

    // Consolidated keeps the period's opening balance.
    $consolidated = $reports->cashFlow($fp, $months, null);
    expect($consolidated['is_consolidated'])->toBeTrue()
        ->and($consolidated['opening_balance'])->toBe(1000.0);
});
