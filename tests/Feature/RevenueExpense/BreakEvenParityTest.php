<?php

use App\Models\Accounts;
use App\Services\RevenueExpense\ExpenseRecordingService;

/**
 * The break-even page must state the same month the Revenue & Expense page
 * does: same money in, same money out, and the business overhead it treats as
 * fixed cost must be the business slice of that same total — not a second
 * query, and not a "Variable expenses" slice in the cost-structure donut.
 */
it('agrees with the Revenue & Expense page for the same month', function () {
    $admin = makeAdmin();
    $period = makeFiscalPeriod($admin);
    $this->actingAs($admin);

    $floor = makeFloor('Floor 1');
    $apartment = makeApartment($floor, ['apartment_number' => 'A-101', 'status' => 'occupied']);
    $tenant = makeTenant($apartment);
    $rental = makeRental($tenant, $apartment, [
        'start_date' => now()->subMonth()->toDateString(),
        'rent_amount' => 500,
    ]);

    $today = now()->toDateString();
    $service = new ExpenseRecordingService($admin->id, $period);
    $service->recordBusinessExpense(['expense_name' => 'Staff', 'category' => 'salaries', 'amount' => 300, 'expense_date' => $today]);
    $service->recordUtilityExpense($rental, ['utility_type' => 'electricity', 'charge_amount' => 40, 'transaction_date' => $today]);
    $service->recordOtherExpense(['category' => 'maintenance', 'description' => 'Pipe', 'amount' => 25, 'transaction_date' => $today]);

    foreach ([[Accounts::CAT_RENT_INCOME, 500], [Accounts::CAT_UTILITY_INCOME, 60]] as [$category, $amount]) {
        Accounts::create([
            'fiscal_period_id' => $period->id, 'user_id' => $admin->id,
            'account_type' => Accounts::TYPE_INCOME, 'category' => $category,
            'description' => $category, 'amount' => $amount, 'transaction_date' => $today,
        ]);
    }

    $month = ['month' => now()->month, 'year' => now()->year];
    $re = $this->get(route('admin.revenue_expense.index', $month))->assertOk();
    $be = $this->get(route('admin.revenue_expense.break_even', $month))->assertOk();

    expect($be->viewData('current_revenue'))->toEqual($re->viewData('income')['total_income'])
        ->and($be->viewData('total_expenses'))->toEqual($re->viewData('expenses')['total_expenses'])
        ->and($be->viewData('business_expenses'))->toEqual(300.0);

    $expenseMix = $be->viewData('health')['expense_mix'];
    expect($expenseMix)->toHaveKey('business_expenses', 300.0)
        ->not->toHaveKey('variable_expenses')
        ->and(array_sum($expenseMix))->toEqual($re->viewData('expenses')['total_expenses']);
});
