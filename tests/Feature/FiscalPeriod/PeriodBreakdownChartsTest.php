<?php

use App\Models\Accounts;
use App\Services\Property\PropertyContext;
use Carbon\Carbon;

/**
 * The period page's income/expense doughnuts itemise money the same way the
 * Revenue & Expense dashboard does — deposits, late fees and each expense
 * type get their own slice instead of one "Other" / "Fixed" line.
 */
it('itemises the period income and expenses by type', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);
    $fp = makeFiscalPeriod($admin);

    $date = Carbon::create(now()->year, 1, 15)->toDateString();
    $row = fn (string $type, string $category, float $amount, string $description = 'x') => Accounts::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $admin->id,
        'account_type' => $type, 'category' => $category,
        'description' => $description, 'amount' => $amount, 'transaction_date' => $date,
    ]);

    $row(Accounts::TYPE_INCOME, Accounts::CAT_RENT_INCOME, 1000);
    $row(Accounts::TYPE_INCOME, Accounts::CAT_DEPOSIT_INCOME, 300);
    $row(Accounts::TYPE_INCOME, Accounts::CAT_LATE_FEE_INCOME, 20);
    $row(Accounts::TYPE_EXPENSE, Accounts::CAT_DEPOSIT_EXPENSE, 150);
    $row(Accounts::TYPE_EXPENSE, Accounts::CAT_MAINTENANCE, 80);

    $response = $this->withSession([PropertyContext::SESSION_KEY => PropertyContext::ALL_PROPERTIES])
        ->get(route('admin.fiscalperiod.show', $fp->id));

    $response->assertOk()
        ->assertSee('fpIncomeChart', false)
        ->assertSee('fpExpenseChart', false);

    $income = $response->viewData('incomeBreakdown');
    expect($income['rent_income'])->toBe(1000.0)
        ->and($income['deposit_income'])->toBe(300.0)
        ->and($income['late_fees'])->toBe(20.0);

    $slices = collect($response->viewData('expenseBreakdown')['breakdown'])->pluck('amount', 'key');
    expect($slices['deposit'])->toBe(150.0)
        ->and($slices['other:maintenance'])->toBe(80.0);
});
