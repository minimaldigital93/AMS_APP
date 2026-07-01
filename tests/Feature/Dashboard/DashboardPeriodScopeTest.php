<?php

use App\Models\Accounts;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\FiscalPeriod\FiscalPeriodFinancialsService;
use Carbon\Carbon;

/**
 * The dashboard's monthly revenue/expense card reads the Accounts ledger by
 * user + date range. It must ALSO scope to the active fiscal period so its
 * figures line up with the consolidated monthly close
 * (FiscalPeriodFinancialsService::forMonth), which filters by fiscal_period_id.
 *
 * Without the period filter, a ledger row from a *different* period whose
 * transaction_date lands in the same month would leak into the dashboard and
 * make it disagree with the month-close numbers.
 */
function dashPeriodFixture(): array
{
    $admin = makeAdmin();
    $day = Carbon::create(now()->year, now()->month, 15);

    // The active (open) period the dashboard shows.
    $active = makeFiscalPeriod($admin);
    // A second period whose dates overlap the same month (e.g. a closed one).
    $other = makeFiscalPeriod($admin, ['name' => 'Other Period', 'status' => 'closed']);

    $ledger = function ($fp, string $type, string $cat, float $amount) use ($admin, $day) {
        Accounts::create([
            'fiscal_period_id' => $fp->id, 'user_id' => $admin->id, 'property_id' => null,
            'account_type' => $type, 'category' => $cat,
            'description' => 'X', 'amount' => $amount, 'transaction_date' => $day->toDateString(),
        ]);
    };

    // Active period: 1000 income + 300 expense.
    $ledger($active, Accounts::TYPE_INCOME, Accounts::CAT_RENT_INCOME, 1000);
    $ledger($active, Accounts::TYPE_EXPENSE, Accounts::CAT_UTILITIES_EXPENSE, 300);
    // Other period, same month — must NOT be counted by the dashboard.
    $ledger($other, Accounts::TYPE_INCOME, Accounts::CAT_RENT_INCOME, 500);
    $ledger($other, Accounts::TYPE_EXPENSE, Accounts::CAT_UTILITIES_EXPENSE, 200);

    return compact('admin', 'active', 'other', 'day');
}

it('scopes dashboard monthly figures to the active fiscal period', function () {
    $f = dashPeriodFixture();
    $start = $f['day']->copy()->startOfMonth();
    $end = $f['day']->copy()->endOfMonth();

    $stats = (new DashboardStatsService($f['admin']->id, null, null, $f['active']->id))
        ->build($start, $end, $start->copy());

    // Only the active period's rows count — the other period's same-month rows
    // are excluded.
    expect($stats['revenue']['total_monthly'])->toBe(1000.0);
    expect($stats['expenses']['monthly_total'])->toBe(300.0);
});

it('matches the consolidated monthly close for the same month', function () {
    $f = dashPeriodFixture();
    $start = $f['day']->copy()->startOfMonth();
    $end = $f['day']->copy()->endOfMonth();

    $stats = (new DashboardStatsService($f['admin']->id, null, null, $f['active']->id))
        ->build($start, $end, $start->copy());

    $close = (new FiscalPeriodFinancialsService)->forRange($f['active'], $start, $end);

    expect($stats['revenue']['total_monthly'])->toBe($close['total_income']);
    expect($stats['expenses']['monthly_total'])->toBe($close['total_expenses']);
});

it('previously leaked other-period rows without the period filter', function () {
    $f = dashPeriodFixture();
    $start = $f['day']->copy()->startOfMonth();
    $end = $f['day']->copy()->endOfMonth();

    // No fiscal-period id (the old behaviour) sums both periods' same-month rows.
    $stats = (new DashboardStatsService($f['admin']->id, null, null, null))
        ->build($start, $end, $start->copy());

    expect($stats['revenue']['total_monthly'])->toBe(1500.0);
    expect($stats['expenses']['monthly_total'])->toBe(500.0);
});
