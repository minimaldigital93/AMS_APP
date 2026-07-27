<?php

use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\MonthlyPeriod;
use App\Models\User;
use App\Services\Dashboard\FiscalPeriodSummaryService;
use App\Services\FiscalPeriod\FiscalPeriodFinancialsService;
use Carbon\Carbon;

/**
 * Rule under test (issue: a deposit booked now for a tenant who moves in next
 * month was inflating the dashboard's current-period figures immediately,
 * because the open fiscal period spans several months and the forward-dated
 * entry falls inside it).
 *
 * The dashboard's live "period so far" summary card recognises a transaction
 * only once its transaction_date has arrived. The FORMAL accounting reports
 * (income statement, balance sheet, close-period math) deliberately stay
 * whole-period — they must count every dated row so the books balance and the
 * monthly breakdown reconciles to the total (see ReportingCrossCheckTest).
 * Explicit month navigation is likewise uncapped so a future month previews.
 */
function futureDatedCapPeriod(User $user): FiscalPeriods
{
    // Open period spanning the current month plus the next (Jun 1 – Dec 31).
    $fp = FiscalPeriods::create([
        'user_id' => $user->id,
        'name' => 'FY Future Cap',
        'opening_date' => '2026-06-01',
        'closing_date' => '2026-12-31',
        'opening_balance' => 0,
        'opening_assets' => 0,
        'opening_liabilities' => 0,
        'opening_equity' => 0,
        'closing_balance' => 0,
        'status' => 'open',
    ]);

    MonthlyPeriod::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'name' => 'August 2026', 'month_number' => 8, 'year' => 2026,
        'start_date' => '2026-08-01', 'end_date' => '2026-08-31',
        'opening_balance' => 0, 'closing_balance' => 0,
        'total_income' => 0, 'total_expenses' => 0, 'net_income' => 0,
        'status' => 'open',
    ]);

    // Realised today: $100 rent income.
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
        'description' => 'Rent', 'amount' => 100, 'transaction_date' => '2026-07-20',
    ]);
    // Forward-dated: $300 deposit for a next-month (Aug 2) move-in.
    Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $user->id,
        'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_DEPOSIT_INCOME,
        'description' => 'Deposit — next month move-in', 'amount' => 300, 'transaction_date' => '2026-08-02',
    ]);

    return $fp;
}

beforeEach(fn () => Carbon::setTestNow('2026-07-27'));
afterEach(fn () => Carbon::setTestNow());

it('excludes a future-dated deposit from the dashboard period summary', function () {
    $user = User::factory()->create();
    $fp = futureDatedCapPeriod($user);

    $summary = (new FiscalPeriodSummaryService($user->id))->build($fp);

    // Only the $100 realised today counts; the $300 Aug deposit is deferred.
    expect($summary['total_income'])->toBe(100.0)
        ->and($summary['net_profit'])->toBe(100.0);
});

it('keeps the future-dated deposit in the whole-period formal financials', function () {
    $user = User::factory()->create();
    $fp = futureDatedCapPeriod($user);

    // Formal report is NOT capped: every dated row in the period is counted so
    // the statement reconciles and the books balance at close.
    $financials = (new FiscalPeriodFinancialsService)->forPeriod($fp);
    expect($financials['total_income'])->toBe(400.0);
});

it('still shows the deposit when its own month is viewed explicitly', function () {
    $user = User::factory()->create();
    $fp = futureDatedCapPeriod($user);
    $aug = $fp->monthlyPeriods()->where('month_number', 8)->first();

    $augFinancials = (new FiscalPeriodFinancialsService)->forMonth($fp, $aug);

    // Explicit August view is uncapped: the $300 deposit appears in its month.
    expect($augFinancials['total_income'])->toBe(300.0);
});

it('counts the deposit in the period summary once its date has arrived', function () {
    Carbon::setTestNow('2026-08-05');
    $user = User::factory()->create();
    $fp = futureDatedCapPeriod($user);

    $summary = (new FiscalPeriodSummaryService($user->id))->build($fp);

    // Now that Aug 2 is in the past, the deposit rolls into the cumulative total.
    expect($summary['total_income'])->toBe(400.0);
});
