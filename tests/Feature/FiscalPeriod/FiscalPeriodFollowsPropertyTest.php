<?php

use App\Models\Accounts;
use App\Models\Property;
use App\Services\Property\PropertyContext;
use Carbon\Carbon;

/**
 * The fiscal-period display pages (period dashboard + monthly detail) follow the
 * global top-bar property selector, exactly like the reports page:
 *
 *  - a single property selected  → income/expenses/net are scoped to that
 *    property and the balance flow is a LIVE running total (opening = the sum of
 *    the property's earlier-month net income, starting at zero). The account-wide
 *    month-close is not offered here ($consolidated === false).
 *  - "All properties" selected   → the consolidated account-wide view, reading
 *    the stored carry-forward and offering the real month-close.
 */
function fpScopeFixture(): array
{
    $admin = makeAdmin();
    test()->actingAs($admin);

    $fp = makeFiscalPeriod($admin);
    app(App\Services\FiscalPeriod\MonthlyPeriodManager::class)->generateForFiscalPeriod($fp);

    $propA = Property::create(['name' => 'Alpha']);
    $propB = Property::create(['name' => 'Beta']);

    $year = now()->year;
    $ledger = function (Property $p, string $date, float $amount) use ($admin, $fp) {
        Accounts::create([
            'fiscal_period_id' => $fp->id, 'user_id' => $admin->id, 'property_id' => $p->id,
            'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
            'description' => 'Rent', 'amount' => $amount, 'transaction_date' => $date,
        ]);
    };

    // Alpha: 1000 in January, 500 in February. Beta: 999 in January only.
    $ledger($propA, Carbon::create($year, 1, 15)->toDateString(), 1000);
    $ledger($propA, Carbon::create($year, 2, 15)->toDateString(), 500);
    $ledger($propB, Carbon::create($year, 1, 15)->toDateString(), 999);

    return compact('admin', 'fp', 'propA', 'propB');
}

it('scopes the period dashboard to the selected property', function () {
    ['fp' => $fp, 'propA' => $propA] = fpScopeFixture();

    $response = $this->withSession([PropertyContext::SESSION_KEY => $propA->id])
        ->get(route('admin.fiscalperiod.show', $fp->id));

    $response->assertOk();
    expect($response->viewData('consolidated'))->toBeFalse()
        ->and($response->viewData('selectedProperty')->id)->toBe($propA->id)
        // Alpha only: 1000 (Jan) + 500 (Feb).
        ->and($response->viewData('financialData')['total_income'])->toBe(1500.0)
        // No per-property cash seed — the running balance starts at zero.
        ->and($response->viewData('periodOpening'))->toBe(0.0);
});

it('consolidates the period dashboard when all properties are selected', function () {
    ['fp' => $fp] = fpScopeFixture();

    $response = $this->withSession([PropertyContext::SESSION_KEY => PropertyContext::ALL_PROPERTIES])
        ->get(route('admin.fiscalperiod.show', $fp->id));

    $response->assertOk();
    expect($response->viewData('consolidated'))->toBeTrue()
        ->and($response->viewData('showingAll'))->toBeTrue()
        ->and($response->viewData('selectedProperty'))->toBeNull()
        // Alpha 1500 + Beta 999.
        ->and($response->viewData('financialData')['total_income'])->toBe(2499.0);
});

it('builds a live per-property running balance on the monthly detail page', function () {
    ['fp' => $fp, 'propA' => $propA] = fpScopeFixture();

    $february = $fp->monthlyPeriods()->where('month_number', 2)->firstOrFail();

    $response = $this->withSession([PropertyContext::SESSION_KEY => $propA->id])
        ->get(route('admin.fiscalperiod.monthly-period.show', [$fp->id, $february->id]));

    $response->assertOk();
    expect($response->viewData('consolidated'))->toBeFalse()
        // February income for Alpha only.
        ->and($response->viewData('financials')['total_income'])->toBe(500.0)
        // Opening = Alpha's earlier-month (January) net income, Beta excluded.
        ->and($response->viewData('openingBalance'))->toBe(1000.0)
        // Closing = opening + this month's net, live (never firm in per-property view).
        ->and($response->viewData('closingBalance'))->toBe(1500.0)
        ->and($response->viewData('closingIsFirm'))->toBeFalse();
});
