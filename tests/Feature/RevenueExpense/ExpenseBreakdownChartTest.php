<?php

use App\Models\Accounts;
use App\Services\RevenueExpense\ExpenseRecordingService;

it('breaks the expense donut down by each expense type, not four buckets', function () {
    $admin = makeAdmin();
    $period = makeFiscalPeriod($admin);
    $this->actingAs($admin);

    $floor = makeFloor('Floor 1');
    $apartment = makeApartment($floor, ['apartment_number' => 'A-101', 'status' => 'occupied']);
    $tenant = makeTenant($apartment);
    $rental = makeRental($tenant, $apartment, ['start_date' => now()->subMonth()->toDateString()]);

    $service = new ExpenseRecordingService($admin->id, $period);
    $today = now()->toDateString();

    $service->recordBusinessExpense(['expense_name' => 'Guard', 'category' => 'security', 'amount' => 100, 'expense_date' => $today]);
    $service->recordBusinessExpense(['expense_name' => 'Staff', 'category' => 'salaries', 'amount' => 300, 'expense_date' => $today]);
    $service->recordBusinessExpense(['expense_name' => 'Guard 2', 'category' => 'security', 'amount' => 50, 'expense_date' => $today]);
    $service->recordUtilityExpense($rental, ['utility_type' => 'electricity', 'charge_amount' => 40, 'transaction_date' => $today]);
    $service->recordUtilityExpense($rental, ['utility_type' => 'water', 'charge_amount' => 10, 'transaction_date' => $today]);
    $service->recordOtherExpense(['category' => 'maintenance', 'description' => 'Pipe', 'amount' => 25, 'transaction_date' => $today]);
    Accounts::create([
        'fiscal_period_id' => $period->id, 'user_id' => $admin->id,
        'account_type' => Accounts::TYPE_EXPENSE, 'category' => Accounts::CAT_DEPOSIT_EXPENSE,
        'description' => 'Deposit refund', 'amount' => 60, 'transaction_date' => $today,
    ]);

    $response = $this->get(route('admin.revenue_expense.index'))->assertOk();

    $breakdown = collect($response->viewData('expenses')['breakdown'])->pluck('amount', 'key')->all();

    expect($breakdown)->toBe([
        'business:salaries' => 300.0,
        'business:security' => 150.0,
        'deposit' => 60.0,
        'utility:electricity' => 40.0,
        'other:maintenance' => 25.0,
        'utility:water' => 10.0,
    ]);
    expect(array_sum($breakdown))->toEqual($response->viewData('expenses')['total_expenses']);
});
