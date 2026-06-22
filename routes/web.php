<?php

use App\Http\Controllers\Admin\ApartmentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FiscalPeriodController;
use App\Http\Controllers\Admin\FloorController;
use App\Http\Controllers\Admin\RevenueExpenseController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\KhqrCallbackController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\AccountsController as SuperAdminAccountsController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\FinanceController as SuperAdminFinanceController;
use App\Http\Controllers\SuperAdmin\PlansController as SuperAdminPlansController;
use App\Http\Controllers\Supervisor\ApartmentController as SupervisorApartmentController;
use App\Http\Controllers\Supervisor\DashboardController as SupervisorDashboardController;
use App\Http\Controllers\Supervisor\RevenueExpenseController as SupervisorRevenueExpenseController;
use App\Http\Controllers\Supervisor\SettingsController as SupervisorSettingsController;
use App\Http\Controllers\Supervisor\TenantController as SupervisorTenantController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

// Public SaaS signup funnel (pricing modal → signup → KHQR checkout → activate).
Route::middleware('guest')->group(function () {
    Route::get('/subscribe', [\App\Http\Controllers\SubscriptionController::class, 'create'])->name('subscribe.create');
    Route::post('/subscribe', [\App\Http\Controllers\SubscriptionController::class, 'store'])->name('subscribe.store');
    Route::get('/subscribe/checkout/{token}', [\App\Http\Controllers\SubscriptionController::class, 'checkout'])->name('subscribe.checkout');
    Route::get('/subscribe/checkout/{token}/status', [\App\Http\Controllers\SubscriptionController::class, 'status'])->name('subscribe.checkout.status');
});

// KHQRPay webhook (signature-authenticated, CSRF-exempt — see bootstrap/app.php)
Route::post('/khqr/callback', KhqrCallbackController::class)
    ->middleware('throttle:60,1')
    ->name('khqr.callback');

// Language Switch Route
Route::post('/language/switch', function (\Illuminate\Http\Request $request) {
    $locale = $request->input('locale');
    if (in_array($locale, ['en', 'km'])) {
        session(['locale' => $locale]);
        \App\Models\Settings::set('app_locale', $locale);
    }

    return redirect()->back()->with('success', __('messages.language_changed'));
})->name('language.switch')->middleware('auth');

// Route for dashboard - redirects to role-appropriate dashboard
Route::get('/dashboard', function () {
    /** @var \App\Models\User $user */
    $user = Auth::user();
    if ($user->hasRole('superadmin')) {
        return redirect()->route('superadmin.dashboard');
    } elseif ($user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    } elseif ($user->hasRole('supervisor')) {
        return redirect()->route('supervisor.dashboard');
    } elseif ($user->hasRole('tenant')) {
        return redirect()->route('tenant.dashboard');
    }

    return redirect('/');
})->middleware(['auth'])->name('dashboard');

Route::get('/admin/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'role:admin|superadmin', 'subscription.active'])
    ->name('admin.dashboard');

Route::get('/supervisor/dashboard', [SupervisorDashboardController::class, 'index'])
    ->middleware(['auth', 'role:supervisor|admin|superadmin'])
    ->name('supervisor.dashboard');

Route::get('/tenant/dashboard', [TenantDashboardController::class, 'index'])
    ->middleware(['auth', 'role:tenant'])
    ->name('tenant.dashboard');

// SuperAdmin Platform Panel (SaaS layer) — reads across all accounts.
Route::middleware(['auth', 'role:superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

    // Customer accounts
    Route::get('/accounts', [SuperAdminAccountsController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/create', [SuperAdminAccountsController::class, 'create'])->name('accounts.create');
    Route::post('/accounts', [SuperAdminAccountsController::class, 'store'])->name('accounts.store');
    Route::post('/accounts/{account}/suspend', [SuperAdminAccountsController::class, 'toggleSuspend'])->name('accounts.suspend');
    Route::post('/accounts/{account}/activate', [SuperAdminAccountsController::class, 'activate'])->name('accounts.activate');
    Route::post('/accounts/{account}/plan', [SuperAdminAccountsController::class, 'changePlan'])->name('accounts.plan');
    Route::delete('/accounts/{account}', [SuperAdminAccountsController::class, 'destroy'])->name('accounts.destroy');
    Route::get('/accounts/{account}', [SuperAdminAccountsController::class, 'show'])->name('accounts.show');
    Route::post('/accounts/{account}/reset-password', [SuperAdminAccountsController::class, 'resetPassword'])->name('accounts.reset-password');

    // Platform payment settings (bank + KHQRPay credentials for subscription payments)
    Route::get('/settings/payment', [\App\Http\Controllers\SuperAdmin\PlatformPaymentSettingsController::class, 'edit'])->name('settings.payment');
    Route::put('/settings/payment', [\App\Http\Controllers\SuperAdmin\PlatformPaymentSettingsController::class, 'update'])->name('settings.payment.update');

    // Platform payments console: subscription transactions, webhooks, refunds
    Route::get('/payments', [\App\Http\Controllers\SuperAdmin\PaymentsController::class, 'index'])->name('payments.index');
    Route::post('/payments/{payment}/refund', [\App\Http\Controllers\SuperAdmin\PaymentsController::class, 'refund'])->name('payments.refund');

    // Plans
    Route::get('/plans', [SuperAdminPlansController::class, 'index'])->name('plans.index');
    Route::post('/plans', [SuperAdminPlansController::class, 'store'])->name('plans.store');
    Route::put('/plans/{plan}', [SuperAdminPlansController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}', [SuperAdminPlansController::class, 'destroy'])->name('plans.destroy');

    // Platform finance (profit & loss: subscription revenue vs platform expenses)
    Route::get('/finance', [SuperAdminFinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/period/{period}/statement', [SuperAdminFinanceController::class, 'statement'])->name('finance.statement');
    Route::post('/finance/expenses', [SuperAdminFinanceController::class, 'store'])->name('finance.expenses.store');
    Route::delete('/finance/expenses/{expense}', [SuperAdminFinanceController::class, 'destroy'])->name('finance.expenses.destroy');
    Route::post('/finance/period', [SuperAdminFinanceController::class, 'storePeriod'])->name('finance.period.store');
    Route::put('/finance/period/{period}', [SuperAdminFinanceController::class, 'updatePeriod'])->name('finance.period.update');
    Route::delete('/finance/period/{period}', [SuperAdminFinanceController::class, 'destroyPeriod'])->name('finance.period.destroy');
    Route::post('/finance/period/{period}/close', [SuperAdminFinanceController::class, 'closePeriod'])->name('finance.period.close');
    Route::post('/finance/period/{period}/reopen', [SuperAdminFinanceController::class, 'reopenPeriod'])->name('finance.period.reopen');
    Route::post('/finance/period/{period}/withdraw', [SuperAdminFinanceController::class, 'storeWithdrawal'])->name('finance.withdrawals.store');
    Route::delete('/finance/withdrawals/{withdrawal}', [SuperAdminFinanceController::class, 'destroyWithdrawal'])->name('finance.withdrawals.destroy');
    Route::post('/finance/period/{period}/months/close', [SuperAdminFinanceController::class, 'closeMonth'])->name('finance.months.close');
    Route::post('/finance/period/{period}/months/reopen', [SuperAdminFinanceController::class, 'reopenMonth'])->name('finance.months.reopen');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Management Routes
Route::middleware(['auth', 'role:admin|superadmin', 'subscription.active'])->group(function () {
    // Billing & Subscription (exempt from the subscription gate inside the middleware)
    Route::get('/admin/billing', [\App\Http\Controllers\Admin\BillingController::class, 'index'])->name('admin.billing.index');
    Route::post('/admin/billing/renew', [\App\Http\Controllers\Admin\BillingController::class, 'renew'])->name('admin.billing.renew');
    Route::post('/admin/billing/cancel', [\App\Http\Controllers\Admin\BillingController::class, 'cancel'])->name('admin.billing.cancel');
    Route::get('/admin/billing/checkout/{token}', [\App\Http\Controllers\Admin\BillingController::class, 'checkout'])->name('admin.billing.checkout');
    Route::get('/admin/billing/checkout/{token}/status', [\App\Http\Controllers\Admin\BillingController::class, 'status'])->name('admin.billing.status');

    // Property Management Routes
    Route::get('/admin/properties', [\App\Http\Controllers\Admin\PropertyController::class, 'index'])->name('admin.properties.index');
    Route::get('/admin/properties/create', [\App\Http\Controllers\Admin\PropertyController::class, 'create'])->name('admin.properties.create');
    Route::get('/admin/properties/{property}/edit', [\App\Http\Controllers\Admin\PropertyController::class, 'edit'])->name('admin.properties.edit');
    Route::post('/admin/properties', [\App\Http\Controllers\Admin\PropertyController::class, 'store'])->name('admin.properties.store');
    Route::put('/admin/properties/{property}', [\App\Http\Controllers\Admin\PropertyController::class, 'update'])->name('admin.properties.update');
    Route::delete('/admin/properties/{property}', [\App\Http\Controllers\Admin\PropertyController::class, 'destroy'])->name('admin.properties.destroy');

    // Floor Management Routes
    Route::get('/admin/floors', [FloorController::class, 'index'])->name('admin.floors.index');
    Route::get('/admin/floors-3d', [FloorController::class, 'plan3d'])->name('admin.floors.plan3d');
    Route::get('/admin/floors/create', [FloorController::class, 'create'])->name('admin.floors.create');
    Route::get('/admin/floors/{floor}/edit', [FloorController::class, 'edit'])->name('admin.floors.edit');
    Route::post('/admin/floors', [FloorController::class, 'store'])->name('admin.floors.store');
    Route::put('/admin/floors/{floor}', [FloorController::class, 'update'])->name('admin.floors.update');
    Route::delete('/admin/floors/{floor}', [FloorController::class, 'destroy'])->name('admin.floors.destroy');
    Route::get('/admin/floors/{floor}/apartments', [FloorController::class, 'getApartments'])->name('admin.floors.apartments');

    // Apartment Management Routes
    Route::get('/admin/apartments', [ApartmentController::class, 'index'])->name('admin.apartments.index');
    Route::get('/admin/apartments/create', [ApartmentController::class, 'create'])->name('admin.apartments.create');
    Route::get('/admin/apartments/{apartment}', [ApartmentController::class, 'show'])->name('admin.apartments.show');
    Route::get('/admin/apartments/{apartment}/edit', [ApartmentController::class, 'edit'])->name('admin.apartments.edit');
    Route::post('/admin/apartments', [ApartmentController::class, 'store'])->name('admin.apartments.store');
    Route::put('/admin/apartments/{apartment}', [ApartmentController::class, 'update'])->name('admin.apartments.update');
    Route::post('/admin/apartments/{apartment}/assign-tenant', [ApartmentController::class, 'assignTenant'])->name('admin.apartments.assignTenant');
    Route::delete('/admin/apartments/{apartment}', [ApartmentController::class, 'destroy'])->name('admin.apartments.destroy');

    // User Management Routes
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::post('/admin/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
    Route::patch('/admin/users/{user}/role', [UserController::class, 'updateRole'])->name('admin.users.updateRole');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/users/{user}/permissions', [UserController::class, 'assignPermissions'])->name('admin.users.permissions');

    // Tenant Management Routes
    Route::get('/admin/tenants', [TenantController::class, 'index'])->name('admin.tenants.index');
    Route::get('/admin/tenants/create', [TenantController::class, 'create'])->name('admin.tenants.create');
    Route::post('/admin/tenants', [TenantController::class, 'store'])->name('admin.tenants.store');
    Route::get('/admin/tenants/archived', [TenantController::class, 'archived'])->name('admin.tenants.archived');
    Route::get('/admin/tenants/{tenant}/leave', [TenantController::class, 'leave'])->name('admin.tenants.leave');
    Route::post('/admin/tenants/{tenant}/process-leave', [TenantController::class, 'processLeave'])->name('admin.tenants.processLeave');
    Route::get('/admin/tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('admin.tenants.edit');
    Route::put('/admin/tenants/{tenant}', [TenantController::class, 'update'])->name('admin.tenants.update');
    Route::get('/admin/tenants/{tenant}', [TenantController::class, 'show'])->name('admin.tenants.show');

    // Fiscal Period Management Routes
    Route::get('/admin/fiscalperiod', [FiscalPeriodController::class, 'index'])->name('admin.fiscalperiod.index');
    Route::get('/admin/fiscalperiod/create', [FiscalPeriodController::class, 'create'])->name('admin.fiscalperiod.create');
    Route::post('/admin/fiscalperiod', [FiscalPeriodController::class, 'store'])->name('admin.fiscalperiod.store');
    Route::get('/admin/fiscalperiod/{fiscalperiod}', [FiscalPeriodController::class, 'show'])->name('admin.fiscalperiod.show');
    Route::get('/admin/fiscalperiod/{fiscalperiod}/edit', [FiscalPeriodController::class, 'edit'])->name('admin.fiscalperiod.edit');
    Route::put('/admin/fiscalperiod/{fiscalperiod}', [FiscalPeriodController::class, 'update'])->name('admin.fiscalperiod.update');
    Route::delete('/admin/fiscalperiod/{fiscalperiod}', [FiscalPeriodController::class, 'destroy'])->name('admin.fiscalperiod.destroy');

    // Balance Sheet Management Routes
    Route::get('/admin/fiscalperiod/{fiscalperiod}/balance-sheet', [FiscalPeriodController::class, 'balanceSheet'])->name('admin.fiscalperiod.balance-sheet');
    Route::post('/admin/fiscalperiod/{fiscalperiod}/balance-sheet', [FiscalPeriodController::class, 'storeBalanceItem'])->name('admin.fiscalperiod.storeBalanceItem');
    Route::delete('/admin/fiscalperiod/{fiscalperiod}/balance-sheet/{balanceSheet}', [FiscalPeriodController::class, 'deleteBalanceItem'])->name('admin.fiscalperiod.deleteBalanceItem');

    // Close the fiscal period
    Route::post('/admin/fiscalperiod/{fiscalperiod}/close', [FiscalPeriodController::class, 'closeperiod'])->name('admin.fiscalperiod.closeperiod');

    // Monthly Period Management Routes (managed from the period dashboard / show page)
    Route::get('/admin/fiscalperiod/{fiscalperiod}/monthly-period/{monthlyPeriod}', [FiscalPeriodController::class, 'showMonth'])->name('admin.fiscalperiod.monthly-period.show');
    Route::post('/admin/fiscalperiod/{fiscalperiod}/monthly-period/{monthlyPeriod}/close', [FiscalPeriodController::class, 'closeMonth'])->name('admin.fiscalperiod.monthly-period.close');
    Route::post('/admin/fiscalperiod/{fiscalperiod}/monthly-period/{monthlyPeriod}/reopen', [FiscalPeriodController::class, 'reopenMonth'])->name('admin.fiscalperiod.monthly-period.reopen');
    Route::post('/admin/fiscalperiod/{fiscalperiod}/recalculate-balances', [FiscalPeriodController::class, 'recalculateBalances'])->name('admin.fiscalperiod.recalculate-balances');

    // Reports & Export Routes
    Route::get('/admin/fiscalperiod/{fiscalperiod}/reports', [FiscalPeriodController::class, 'reports'])->name('admin.fiscalperiod.reports');
    Route::get('/admin/fiscalperiod/{fiscalperiod}/export-pdf', [FiscalPeriodController::class, 'exportPDF'])->name('admin.fiscalperiod.exportPDF');
    Route::get('/admin/fiscalperiod/{fiscalperiod}/export-csv', [FiscalPeriodController::class, 'exportCSV'])->name('admin.fiscalperiod.exportCSV');
    Route::get('/admin/fiscalperiod/{fiscalperiod}/monthly-period/{monthlyPeriod}/print', [FiscalPeriodController::class, 'printMonthlyPDF'])->name('admin.fiscalperiod.monthly-period.print');

    // Merchant Payment Settings (bank details, static KHQR, optional KHQRPay API)
    Route::get('/admin/settings/payment', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'edit'])->name('admin.settings.payment');
    Route::put('/admin/settings/payment', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'update'])->name('admin.settings.payment.update');

    // System Settings Routes
    Route::get('/admin/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
    Route::put('/admin/settings/batch', [SettingsController::class, 'updateBatch'])->name('admin.settings.updateBatch');
    Route::put('/admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    Route::delete('/admin/settings/reset', [SettingsController::class, 'reset'])->name('admin.settings.reset');
    Route::delete('/admin/settings', [SettingsController::class, 'destroy'])->name('admin.settings.destroy');
    Route::get('/admin/settings/{key}', [SettingsController::class, 'get'])->name('admin.settings.get');

    // Revenue & Expense Management Routes (requires active fiscal period)
    Route::middleware(['fiscal.period'])->group(function () {
        Route::get('/admin/revenue-expense', [RevenueExpenseController::class, 'index'])->name('admin.revenue_expense.index');
        Route::get('/admin/revenue-expense/break-even', [RevenueExpenseController::class, 'breakEvenPoint'])->name('admin.revenue_expense.break_even');
        Route::get('/admin/revenue-expense/record-income', [RevenueExpenseController::class, 'recordIncome'])->name('admin.revenue_expense.record_income');
        Route::post('/admin/revenue-expense/record-income', [RevenueExpenseController::class, 'storeIncome'])->name('admin.revenue_expense.store_income');
        Route::post('/admin/revenue-expense/record-income-bulk', [RevenueExpenseController::class, 'storeBulkIncome'])->name('admin.revenue_expense.store_income_bulk');

        // Tenant Billing Actions
        Route::post('/admin/revenue-expense/add-charge', [RevenueExpenseController::class, 'addTenantCharge'])->name('admin.revenue_expense.add_charge');
        Route::delete('/admin/revenue-expense/remove-charge/{charge}', [RevenueExpenseController::class, 'removeTenantCharge'])->name('admin.revenue_expense.remove_charge');
        Route::delete('/admin/revenue-expense/clear-charges/{rental}', [RevenueExpenseController::class, 'clearTenantCharges'])->name('admin.revenue_expense.clear_charges');
        Route::post('/admin/revenue-expense/checkout', [RevenueExpenseController::class, 'checkoutTenant'])->name('admin.revenue_expense.checkout');
        // KHQR (KHQRPay) dynamic-QR payment
        Route::post('/admin/revenue-expense/khqr/generate', [RevenueExpenseController::class, 'khqrGenerate'])->name('admin.revenue_expense.khqr_generate');
        Route::get('/admin/revenue-expense/khqr/status/{transaction}', [RevenueExpenseController::class, 'khqrStatus'])->name('admin.revenue_expense.khqr_status');
        Route::post('/admin/revenue-expense/khqr/confirm/{transaction}', [RevenueExpenseController::class, 'khqrConfirm'])->name('admin.revenue_expense.khqr_confirm');
        Route::post('/admin/revenue-expense/khqr/reject/{transaction}', [RevenueExpenseController::class, 'khqrReject'])->name('admin.revenue_expense.khqr_reject');
        Route::get('/admin/revenue-expense/print-bill/{rental}', [RevenueExpenseController::class, 'printTenantBill'])->name('admin.revenue_expense.print_bill');

        Route::get('/admin/revenue-expense/record-expense', [RevenueExpenseController::class, 'recordExpense'])->name('admin.revenue_expense.record_expense');
        Route::post('/admin/revenue-expense/record-expense', [RevenueExpenseController::class, 'storeExpense'])->name('admin.revenue_expense.store_expense');

        // Other Expense Allocation
        Route::post('/admin/revenue-expense/other-expense', [RevenueExpenseController::class, 'storeOtherExpense'])->name('admin.revenue_expense.store_other_expense');
        Route::delete('/admin/revenue-expense/other-expense/{expense}', [RevenueExpenseController::class, 'deleteOtherExpense'])->name('admin.revenue_expense.delete_other_expense');

        // Business Fixed & Variable Expenses
        Route::post('/admin/revenue-expense/business-expense', [RevenueExpenseController::class, 'storeBusinessExpense'])->name('admin.revenue_expense.store_business_expense');
        Route::delete('/admin/revenue-expense/business-expense/{businessExpense}', [RevenueExpenseController::class, 'deleteBusinessExpense'])->name('admin.revenue_expense.delete_business_expense');

        // Fixed Expense Management
        Route::get('/admin/revenue-expense/fixed-expenses', [RevenueExpenseController::class, 'fixedExpenses'])->name('admin.revenue_expense.fixed_expenses');
        Route::post('/admin/revenue-expense/fixed-expenses', [RevenueExpenseController::class, 'storeFixedExpense'])->name('admin.revenue_expense.store_fixed_expense');
        Route::patch('/admin/revenue-expense/fixed-expenses/{fixedExpense}/toggle', [RevenueExpenseController::class, 'toggleFixedExpense'])->name('admin.revenue_expense.toggle_fixed_expense');
        Route::delete('/admin/revenue-expense/fixed-expenses/{fixedExpense}', [RevenueExpenseController::class, 'deleteFixedExpense'])->name('admin.revenue_expense.delete_fixed_expense');

        // Monthly Bill Generation
        Route::get('/admin/revenue-expense/generate-bills', [RevenueExpenseController::class, 'generateMonthlyBills'])->name('admin.revenue_expense.generate_bills');
        Route::post('/admin/revenue-expense/generate-bills', [RevenueExpenseController::class, 'processMonthlyBills'])->name('admin.revenue_expense.process_bills');
        // Quick auto-process action (triggered by small icon)
        Route::post('/admin/revenue-expense/generate-bills/auto', [RevenueExpenseController::class, 'autoProcessMonthlyBills'])->name('admin.revenue_expense.process_bills_auto');
        // Export per-apartment summary as PDF
        Route::get('/admin/revenue-expense/apartment-summary-pdf', [RevenueExpenseController::class, 'apartmentSummaryPdf'])->name('admin.revenue_expense.apartment_summary_pdf');
        // HTML preview of apartment summary (show first, allow export)
        Route::get('/admin/revenue-expense/apartment-summary-preview', [RevenueExpenseController::class, 'apartmentSummaryPreview'])->name('admin.revenue_expense.apartment_summary_preview');

        // Monthly Calendar View
        Route::get('/admin/revenue-expense/monthly-calendar', [RevenueExpenseController::class, 'monthlyCalendar'])->name('admin.revenue_expense.monthly_calendar');

        // Income Statement (P&L Report)
        Route::get('/admin/revenue-expense/income-statement', [RevenueExpenseController::class, 'incomeStatement'])->name('admin.revenue_expense.income_statement');
    });
});

// Supervisor Management Routes
// Gated to supervisor, but admin/superadmin (the supervisor's account owner and the
// platform owner) may also enter the supervisor panel to view/preview it.
Route::middleware(['auth', 'role:supervisor|admin|superadmin', 'subscription.active'])->prefix('supervisor')->group(function () {
    // Tenant Management
    Route::get('/tenants', [SupervisorTenantController::class, 'index'])->name('supervisor.tenants.index');
    Route::get('/tenants/create', [SupervisorTenantController::class, 'create'])->name('supervisor.tenants.create');
    Route::post('/tenants', [SupervisorTenantController::class, 'store'])->name('supervisor.tenants.store');
    Route::get('/tenants/archived', [SupervisorTenantController::class, 'archived'])->name('supervisor.tenants.archived');
    Route::get('/tenants/{tenant}/edit', [SupervisorTenantController::class, 'edit'])->name('supervisor.tenants.edit');
    Route::put('/tenants/{tenant}', [SupervisorTenantController::class, 'update'])->name('supervisor.tenants.update');
    Route::delete('/tenants/{tenant}', [SupervisorTenantController::class, 'destroy'])->name('supervisor.tenants.destroy');
    Route::get('/tenants/{tenant}/leave', [SupervisorTenantController::class, 'leave'])->name('supervisor.tenants.leave');
    Route::post('/tenants/{tenant}/process-leave', [SupervisorTenantController::class, 'processLeave'])->name('supervisor.tenants.processLeave');
    Route::get('/tenants/{tenant}', [SupervisorTenantController::class, 'show'])->name('supervisor.tenants.show');

    // Apartment Management
    Route::get('/floors-3d', [SupervisorApartmentController::class, 'plan3d'])->name('supervisor.floors.plan3d');
    Route::get('/apartments', [SupervisorApartmentController::class, 'index'])->name('supervisor.apartments.index');
    Route::get('/apartments/{apartment}', [SupervisorApartmentController::class, 'show'])->name('supervisor.apartments.show');
    Route::post('/apartments/{apartment}/assign-tenant', [SupervisorApartmentController::class, 'assignTenant'])->name('supervisor.apartments.assignTenant');

    // Revenue & Expense (requires an admin to have an open fiscal period)
    Route::middleware(['fiscal.period'])->group(function () {
        Route::get('/revenue-expense', [SupervisorRevenueExpenseController::class, 'index'])->name('supervisor.revenue_expense.index');
        Route::get('/revenue-expense/record-income', [SupervisorRevenueExpenseController::class, 'recordIncome'])->name('supervisor.revenue_expense.record_income');
        Route::post('/revenue-expense/record-income', [SupervisorRevenueExpenseController::class, 'storeIncome'])->name('supervisor.revenue_expense.store_income');
        Route::post('/revenue-expense/record-income-bulk', [SupervisorRevenueExpenseController::class, 'storeBulkIncome'])->name('supervisor.revenue_expense.store_income_bulk');
        Route::post('/revenue-expense/add-charge', [SupervisorRevenueExpenseController::class, 'addTenantCharge'])->name('supervisor.revenue_expense.add_charge');
        Route::delete('/revenue-expense/remove-charge/{charge}', [SupervisorRevenueExpenseController::class, 'removeTenantCharge'])->name('supervisor.revenue_expense.remove_charge');
        Route::delete('/revenue-expense/clear-charges/{rental}', [SupervisorRevenueExpenseController::class, 'clearTenantCharges'])->name('supervisor.revenue_expense.clear_charges');
        Route::post('/revenue-expense/checkout', [SupervisorRevenueExpenseController::class, 'checkoutTenant'])->name('supervisor.revenue_expense.checkout');
        // KHQR (KHQRPay) dynamic-QR payment
        Route::post('/revenue-expense/khqr/generate', [SupervisorRevenueExpenseController::class, 'khqrGenerate'])->name('supervisor.revenue_expense.khqr_generate');
        Route::get('/revenue-expense/khqr/status/{transaction}', [SupervisorRevenueExpenseController::class, 'khqrStatus'])->name('supervisor.revenue_expense.khqr_status');
        Route::post('/revenue-expense/khqr/confirm/{transaction}', [SupervisorRevenueExpenseController::class, 'khqrConfirm'])->name('supervisor.revenue_expense.khqr_confirm');
        Route::post('/revenue-expense/khqr/reject/{transaction}', [SupervisorRevenueExpenseController::class, 'khqrReject'])->name('supervisor.revenue_expense.khqr_reject');
        Route::get('/revenue-expense/print-bill/{rental}', [SupervisorRevenueExpenseController::class, 'printTenantBill'])->name('supervisor.revenue_expense.print_bill');
        Route::get('/revenue-expense/record-expense', [SupervisorRevenueExpenseController::class, 'recordExpense'])->name('supervisor.revenue_expense.record_expense');
        Route::post('/revenue-expense/record-expense', [SupervisorRevenueExpenseController::class, 'storeExpense'])->name('supervisor.revenue_expense.store_expense');
        Route::post('/revenue-expense/other-expense', [SupervisorRevenueExpenseController::class, 'storeOtherExpense'])->name('supervisor.revenue_expense.store_other_expense');
        Route::delete('/revenue-expense/other-expense/{expense}', [SupervisorRevenueExpenseController::class, 'deleteOtherExpense'])->name('supervisor.revenue_expense.delete_other_expense');
        Route::post('/revenue-expense/business-expense', [SupervisorRevenueExpenseController::class, 'storeBusinessExpense'])->name('supervisor.revenue_expense.store_business_expense');
        Route::delete('/revenue-expense/business-expense/{businessExpense}', [SupervisorRevenueExpenseController::class, 'deleteBusinessExpense'])->name('supervisor.revenue_expense.delete_business_expense');
        Route::get('/revenue-expense/fixed-expenses', [SupervisorRevenueExpenseController::class, 'fixedExpenses'])->name('supervisor.revenue_expense.fixed_expenses');
        Route::post('/revenue-expense/fixed-expenses', [SupervisorRevenueExpenseController::class, 'storeFixedExpense'])->name('supervisor.revenue_expense.store_fixed_expense');
        Route::patch('/revenue-expense/fixed-expenses/{fixedExpense}/toggle', [SupervisorRevenueExpenseController::class, 'toggleFixedExpense'])->name('supervisor.revenue_expense.toggle_fixed_expense');
        Route::delete('/revenue-expense/fixed-expenses/{fixedExpense}', [SupervisorRevenueExpenseController::class, 'deleteFixedExpense'])->name('supervisor.revenue_expense.delete_fixed_expense');
        Route::get('/revenue-expense/generate-bills', [SupervisorRevenueExpenseController::class, 'generateMonthlyBills'])->name('supervisor.revenue_expense.generate_bills');
        Route::post('/revenue-expense/generate-bills', [SupervisorRevenueExpenseController::class, 'processMonthlyBills'])->name('supervisor.revenue_expense.process_bills');
        Route::post('/revenue-expense/generate-bills/auto', [SupervisorRevenueExpenseController::class, 'autoProcessMonthlyBills'])->name('supervisor.revenue_expense.process_bills_auto');
        Route::get('/revenue-expense/apartment-summary-pdf', [SupervisorRevenueExpenseController::class, 'apartmentSummaryPdf'])->name('supervisor.revenue_expense.apartment_summary_pdf');
        Route::get('/revenue-expense/apartment-summary-preview', [SupervisorRevenueExpenseController::class, 'apartmentSummaryPreview'])->name('supervisor.revenue_expense.apartment_summary_preview');
        Route::get('/revenue-expense/monthly-calendar', [SupervisorRevenueExpenseController::class, 'monthlyCalendar'])->name('supervisor.revenue_expense.monthly_calendar');
        Route::get('/revenue-expense/income-statement', [SupervisorRevenueExpenseController::class, 'incomeStatement'])->name('supervisor.revenue_expense.income_statement');
        Route::get('/revenue-expense/break-even', [SupervisorRevenueExpenseController::class, 'breakEvenPoint'])->name('supervisor.revenue_expense.break_even');
    });

    // System Settings
    Route::get('/settings', [SupervisorSettingsController::class, 'index'])->name('supervisor.settings.index');
    Route::put('/settings/batch', [SupervisorSettingsController::class, 'updateBatch'])->name('supervisor.settings.updateBatch');
    Route::delete('/settings/reset', [SupervisorSettingsController::class, 'reset'])->name('supervisor.settings.reset');
});

require __DIR__.'/auth.php';
