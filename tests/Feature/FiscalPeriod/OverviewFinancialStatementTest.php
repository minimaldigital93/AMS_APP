<?php

use App\Models\Accounts;
use App\Models\MonthlyPeriod;
use Illuminate\Support\Facades\View;

function statementFixture(): array
{
    $admin = makeAdmin();
    test()->actingAs($admin);

    $fp = makeFiscalPeriod($admin, [
        'opening_date' => '2026-01-01', 'closing_date' => '2026-01-31', 'opening_balance' => 1000,
    ]);
    MonthlyPeriod::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $admin->id,
        'name' => 'January 2026', 'month_number' => 1, 'year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        'opening_balance' => 1000, 'closing_balance' => 0,
        'total_income' => 0, 'total_expenses' => 0, 'net_income' => 0,
        'owner_withdrawal' => 150, 'status' => 'open',
    ]);

    foreach ([
        [Accounts::TYPE_INCOME, Accounts::CAT_RENT_INCOME, 'Rent', 1000],
        [Accounts::TYPE_INCOME, Accounts::CAT_DEPOSIT_INCOME, 'Deposit', 200],
        [Accounts::TYPE_EXPENSE, Accounts::CAT_UTILITIES_EXPENSE, 'electricity', 100],
        [Accounts::TYPE_EXPENSE, Accounts::CAT_MAINTENANCE, 'Repairs', 300],
    ] as [$type, $cat, $desc, $amt]) {
        Accounts::create([
            'fiscal_period_id' => $fp->id, 'user_id' => $admin->id,
            'account_type' => $type, 'category' => $cat,
            'description' => $desc, 'amount' => $amt, 'transaction_date' => '2026-01-15',
        ]);
    }

    return [$admin, $fp];
}

/**
 * The printed Overview tab is a summary financial statement: itemised revenue
 * and expenses, a net result, and a cash reconciliation that agrees with the
 * Cash Flow tab (owner's drawings included) — not the screen's summary cards.
 */
it('prints an itemised statement whose closing cash matches the cash flow', function () {
    [, $fp] = statementFixture();

    $response = $this->get(route('admin.fiscalperiod.reports', $fp->id));

    $response->assertOk();
    $pf = $response->viewData('periodFinancials');
    expect($pf['deposit_income'])->toBe(200.0)
        ->and($pf['utility_income'])->toBe(0.0)
        ->and($pf['other_income'])->toBe(200.0)          // unchanged, still the sum
        ->and($pf['operating_expenses'])->toBe(['maintenance' => 300.0]);

    // 1000 opening + 800 net − 150 drawings = 1650, on the statement and the card.
    $response->assertSee(__('messages.fs_title'))
        ->assertSee(__('messages.fs_deposits_received'))
        ->assertSee('($400)', false)
        ->assertSee(__('messages.fs_statement_of_cash_flows'))
        ->assertSee(__('messages.fs_cf_draws'))
        ->assertSee('($150)', false)
        ->assertSee(__('messages.fs_monthly_breakdown'))
        ->assertSee('January 2026')
        ->assertSee('$1,650', false)
        ->assertDontSee('$1,800', false)
        ->assertSee(__('messages.fs_prepared_by'));
});

/*
 * Reports & Exports → Export → Financial statement (PDF): a popup asks for a
 * start and end date and the statement is generated for exactly that range,
 * as a PDF the page shows in its own viewer.
 */
function captureStatementData(): ArrayObject
{
    $captured = new ArrayObject;
    View::composer('admin.fiscalperiod.statement-pdf', function ($view) use ($captured) {
        $captured->exchangeArray($view->getData());
    });

    return $captured;
}

it('offers the date-range popup on the reports page', function () {
    [, $fp] = statementFixture();

    $this->get(route('admin.fiscalperiod.reports', $fp->id))
        ->assertOk()
        ->assertSee(__('messages.fs_pdf_title'))
        ->assertSee(__('messages.fs_menu_pdf'))
        ->assertSee(__('messages.fs_menu_print'))
        ->assertSee(route('admin.fiscalperiod.exportCSV', $fp->id), false)
        ->assertSee(route('admin.fiscalperiod.statement-pdf', $fp->id), false);
});

it('serves the whole-period statement inline with the same closing cash as the cash flow', function () {
    [, $fp] = statementFixture();
    $data = captureStatementData();

    $response = $this->get(route('admin.fiscalperiod.statement-pdf', [$fp->id, 'from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        // Inline, not an attachment: the page shows it in its own viewer.
        ->and($response->headers->get('Content-Disposition'))->toBe('inline; filename="financial-statement-2026-01-01-to-2026-01-31.pdf"')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and($data['pf']['total_income'])->toBe(1200.0)
        ->and($data['cash'])->toMatchArray(['opening_balance' => 1000.0, 'total_withdrawals' => 150.0, 'closing_balance' => 1650.0])
        ->and($data['drawsByMonthEnd'])->toBeFalse();
});

it('limits the figures to the chosen dates and carries earlier results into opening cash', function () {
    [, $fp] = statementFixture();
    $data = captureStatementData();

    // Everything was booked on Jan 15, so Jan 16–31 earns nothing but opens with
    // it: 1000 + 800 = 1800. January's draw is dated Jan 31, inside the range.
    $this->get(route('admin.fiscalperiod.statement-pdf', [$fp->id, 'from' => '2026-01-16', 'to' => '2026-01-31']))->assertOk();
    expect($data['pf']['total_income'])->toBe(0.0)
        ->and($data['cash'])->toMatchArray(['opening_balance' => 1800.0, 'total_withdrawals' => 150.0, 'closing_balance' => 1650.0])
        ->and($data['drawsByMonthEnd'])->toBeTrue()
        ->and($data['cash']['months'])->toHaveCount(1)
        ->and($data['cash']['months'][0])->toMatchArray([
            'partial' => true, 'opening_balance' => 1800.0, 'cash_in' => 0.0,
            'owner_withdrawal' => 150.0, 'closing_balance' => 1650.0,
        ]);

    // Jan 1–15 holds the whole result but not the draw (its month ends later).
    $this->get(route('admin.fiscalperiod.statement-pdf', [$fp->id, 'from' => '2026-01-01', 'to' => '2026-01-15']))->assertOk();
    expect($data['pf']['net_income'])->toBe(800.0)
        ->and($data['cash'])->toMatchArray(['opening_balance' => 1000.0, 'total_withdrawals' => 0.0, 'closing_balance' => 1800.0]);
});

/*
 * The monthly breakdown cuts the months at the chosen dates and runs the cash
 * balance through them, so its rows add up to the statement and its last row
 * closes on the statement's closing cash.
 */
it('breaks the chosen dates down by month, adding up to the statement', function () {
    [$admin, $fp] = statementFixture();
    $fp->update(['closing_date' => '2026-02-28']);
    MonthlyPeriod::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $admin->id,
        'name' => 'February 2026', 'month_number' => 2, 'year' => 2026,
        'start_date' => '2026-02-01', 'end_date' => '2026-02-28',
        'opening_balance' => 0, 'closing_balance' => 0,
        'total_income' => 0, 'total_expenses' => 0, 'net_income' => 0,
        'owner_withdrawal' => 100, 'status' => 'open',
    ]);
    foreach (['2026-02-05' => 500, '2026-02-20' => 700] as $date => $amount) {
        Accounts::create([
            'fiscal_period_id' => $fp->id, 'user_id' => $admin->id,
            'account_type' => Accounts::TYPE_INCOME, 'category' => Accounts::CAT_RENT_INCOME,
            'description' => 'Rent', 'amount' => $amount, 'transaction_date' => $date,
        ]);
    }
    $data = captureStatementData();

    // Jan 10 – Feb 10: all of January's result, February's first rent only,
    // January's draw (Jan 31) but not February's (Feb 28 is outside).
    $this->get(route('admin.fiscalperiod.statement-pdf', [$fp->id, 'from' => '2026-01-10', 'to' => '2026-02-10']))->assertOk();

    $months = $data['cash']['months'];
    expect(array_column($months, 'name'))->toBe(['January 2026', 'February 2026'])
        ->and(array_column($months, 'partial'))->toBe([true, true])
        ->and(array_column($months, 'net_cash_flow'))->toBe([800.0, 500.0])
        ->and(array_column($months, 'owner_withdrawal'))->toBe([150.0, 0.0])
        ->and(array_column($months, 'closing_balance'))->toBe([1650.0, 2150.0])
        ->and(array_sum(array_column($months, 'net_cash_flow')))->toBe($data['pf']['net_income'])
        ->and($data['cash']['closing_balance'])->toBe(2150.0);
});

it('refuses dates outside the fiscal period or in the wrong order', function (array $query) {
    [, $fp] = statementFixture();

    $this->from(route('admin.fiscalperiod.reports', $fp->id))
        ->get(route('admin.fiscalperiod.statement-pdf', [$fp->id] + $query))
        ->assertRedirect(route('admin.fiscalperiod.reports', $fp->id))
        ->assertSessionHasErrors(null, null, 'statementPdf');
})->with([
    'before the period' => [['from' => '2025-12-31', 'to' => '2026-01-31']],
    'after the period' => [['from' => '2026-01-01', 'to' => '2026-02-01']],
    'end before start' => [['from' => '2026-01-20', 'to' => '2026-01-10']],
    'missing dates' => [[]],
]);

it('does not hand another account its statement (the account scope hides the period)', function () {
    [, $fp] = statementFixture();
    $this->actingAs(makeAdmin());

    $this->get(route('admin.fiscalperiod.statement-pdf', [$fp->id, 'from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertNotFound();
});
