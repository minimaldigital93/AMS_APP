<?php

use App\Models\Accounts;
use App\Models\Property;
use App\Services\Property\PropertyContext;

/**
 * Reports & exports follow the global top-bar property selector (PropertyContext),
 * not a per-page ?property= query param — the whole app shares one active-property
 * context. The selection comes from the session; a stray ?property= is ignored.
 */
function reportsFixture(): array
{
    $admin = makeAdmin();
    test()->actingAs($admin);

    $fp = makeFiscalPeriod($admin);
    $propA = Property::create(['name' => 'A']);
    $propB = Property::create(['name' => 'B']);

    foreach ([[$propA, 1000.0], [$propB, 600.0]] as [$p, $amt]) {
        Accounts::create([
            'fiscal_period_id' => $fp->id, 'user_id' => $admin->id, 'property_id' => $p->id,
            'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
            'description' => 'Rent', 'amount' => $amt, 'transaction_date' => now()->toDateString(),
        ]);
    }

    return compact('admin', 'fp', 'propA', 'propB');
}

it('scopes reports to the globally selected property and ignores a stray ?property= param', function () {
    ['fp' => $fp, 'propA' => $propA, 'propB' => $propB] = reportsFixture();

    // Globally select Property A, then request the report while *also* passing a
    // stray ?property=B — the global selection must win.
    $response = $this->withSession([PropertyContext::SESSION_KEY => $propA->id])
        ->get(route('admin.fiscalperiod.reports', [$fp->id, 'property' => $propB->id]));

    $response->assertOk();
    expect($response->viewData('selectedPropertyId'))->toBe($propA->id)
        ->and($response->viewData('periodFinancials')['total_income'])->toBe(1000.0);
});

it('renders a consolidated report when all properties are selected', function () {
    ['fp' => $fp] = reportsFixture();

    $response = $this->withSession([PropertyContext::SESSION_KEY => PropertyContext::ALL_PROPERTIES])
        ->get(route('admin.fiscalperiod.reports', $fp->id));

    $response->assertOk();
    expect($response->viewData('selectedPropertyId'))->toBeNull()
        ->and($response->viewData('periodFinancials')['total_income'])->toBe(1600.0);
});
