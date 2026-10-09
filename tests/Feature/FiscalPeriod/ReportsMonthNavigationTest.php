<?php

use App\Models\Accounts;
use App\Models\MonthlyPeriod;

/*
 * Reports & Exports steps month by month like the break-even page. A selected
 * month narrows the overview, income statement, cash flow, monthly breakdown
 * and CSV to that month; "All months" (?month=all) is the whole period.
 */
function reportsMonthFixture(): array
{
    $admin = makeAdmin();
    test()->actingAs($admin);

    $fp = makeFiscalPeriod($admin, [
        'opening_date' => '2026-01-01', 'closing_date' => '2026-02-28', 'opening_balance' => 1000,
    ]);

    foreach ([[1, 'January 2026', '2026-01-01', '2026-01-31', 100], [2, 'February 2026', '2026-02-01', '2026-02-28', 0]] as [$n, $name, $start, $end, $draw]) {
        MonthlyPeriod::create([
            'fiscal_period_id' => $fp->id, 'user_id' => $admin->id,
            'name' => $name, 'month_number' => $n, 'year' => 2026,
            'start_date' => $start, 'end_date' => $end,
            'opening_balance' => 0, 'closing_balance' => 0,
            'total_income' => 0, 'total_expenses' => 0, 'net_income' => 0,
            'owner_withdrawal' => $draw, 'status' => 'open',
        ]);
    }

    foreach ([['2026-01-15', 500], ['2026-02-15', 300]] as [$date, $amount]) {
        Accounts::create([
            'fiscal_period_id' => $fp->id, 'user_id' => $admin->id,
            'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
            'description' => 'Rent', 'amount' => $amount, 'transaction_date' => $date,
        ]);
    }

    return [$admin, $fp];
}

it('shows the whole period when no month is chosen', function () {
    [, $fp] = reportsMonthFixture();

    $response = $this->get(route('admin.fiscalperiod.reports', $fp->id))->assertOk();

    expect($response->viewData('reportMonth'))->toBeNull()
        ->and($response->viewData('periodFinancials')['total_income'])->toBe(800.0)
        ->and($response->viewData('incomeStatement')['months'])->toHaveCount(2)
        ->and($response->viewData('nextReportMonth')->name)->toBe('January 2026');
});

it('narrows the reports to the selected month, carrying the running cash in', function () {
    [, $fp] = reportsMonthFixture();

    $response = $this->get(route('admin.fiscalperiod.reports', [$fp->id, 'month' => 2, 'year' => 2026]))->assertOk();

    expect($response->viewData('reportMonth')->name)->toBe('February 2026')
        ->and($response->viewData('prevReportMonth')->name)->toBe('January 2026')
        ->and($response->viewData('nextReportMonth'))->toBeNull()
        ->and($response->viewData('periodFinancials')['total_income'])->toBe(300.0)
        ->and($response->viewData('incomeStatement')['months'])->toHaveCount(1)
        ->and($response->viewData('incomeStatement')['totals']['total_income'])->toEqual(300)
        ->and($response->viewData('monthlyData'))->toHaveCount(1);

    // 1000 opening + 500 January − 100 January draw = 1400 into February.
    $cash = $response->viewData('cashFlow');
    expect($cash['opening_balance'])->toEqual(1400)
        ->and($cash['closing_balance'])->toEqual(1700)
        ->and($cash['months'])->toHaveCount(1);
});

it('clamps a month outside the period to its nearest end', function () {
    [, $fp] = reportsMonthFixture();

    $late = $this->get(route('admin.fiscalperiod.reports', [$fp->id, 'month' => 6, 'year' => 2030]));
    $early = $this->get(route('admin.fiscalperiod.reports', [$fp->id, 'month' => 1, 'year' => 2020]));

    expect($late->viewData('reportMonth')->name)->toBe('February 2026')
        ->and($early->viewData('reportMonth')->name)->toBe('January 2026');
});

it('opens on the remembered working month, and ?month=all overrides it', function () {
    [, $fp] = reportsMonthFixture();

    // Navigating anywhere with ?month= records the working month.
    $this->get(route('admin.fiscalperiod.reports', [$fp->id, 'month' => 2, 'year' => 2026]));

    expect($this->get(route('admin.fiscalperiod.reports', $fp->id))->viewData('reportMonth')->name)
        ->toBe('February 2026')
        ->and($this->get(route('admin.fiscalperiod.reports', [$fp->id, 'month' => 'all']))->viewData('reportMonth'))
        ->toBeNull();
});

it('exports the CSV for the selected month, and the whole period from a bare link', function () {
    [, $fp] = reportsMonthFixture();

    $month = $this->get(route('admin.fiscalperiod.exportCSV', [$fp->id, 'month' => 2, 'year' => 2026]))->streamedContent();
    expect($month)->toContain('Month: February 2026')->toContain('"Total Revenue",300.00');

    $whole = $this->get(route('admin.fiscalperiod.exportCSV', $fp->id))->streamedContent();
    expect($whole)->toContain('Month: All months')->toContain('"Total Revenue",800.00');
});
