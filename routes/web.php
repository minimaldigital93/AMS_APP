<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FloorController;
use App\Http\Controllers\Admin\ApartmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\FiscalPeriodController;
use App\Http\Controllers\Admin\RevenueExpenseController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Supervisor\DashboardController as SupervisorDashboardController;
use App\Http\Controllers\Supervisor\TenantController as SupervisorTenantController;
use App\Http\Controllers\Supervisor\ApartmentController as SupervisorApartmentController;
use App\Http\Controllers\Supervisor\PaymentController as SupervisorPaymentController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

  

Route::get('/', function () {
    return view('auth.login');
});

// Language Switch Route
Route::post('/language/switch', function (\Illuminate\Http\Request $request) {
    $locale = $request->input('locale');
    if (in_array($locale, ['en', 'km'])) {
        session(['locale' => $locale]);
        \App\Models\Settings::set('app_locale', $locale);
    }
    return redirect()->back()->with('success', __('messages.language_changed'));
})->name('language.switch')->middleware('auth');

//Route for dashboard - redirects to role-appropriate dashboard
Route::get('/dashboard', function () {
    /** @var \App\Models\User $user */
    $user = Auth::user();
    if ($user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    } elseif ($user->hasRole('supervisor')) {
        return redirect()->route('supervisor.dashboard');
    } elseif ($user->hasRole('tenant')) {
        return redirect()->route('tenant.dashboard');
    }
    return redirect('/');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/admin/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:admin'])
    ->name('admin.dashboard');

Route::get('/supervisor/dashboard', [SupervisorDashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:supervisor'])
    ->name('supervisor.dashboard');

Route::get('/tenant/dashboard', [TenantDashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:tenant'])
    ->name('tenant.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Management Routes
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Floor Management Routes
    Route::get('/admin/floors', [FloorController::class, 'index'])->name('admin.floors.index');
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
    
    // Opening/Closing Balances Routes
    Route::get('/admin/fiscalperiod/{fiscalperiod}/open-close-balances', [FiscalPeriodController::class, 'openCloseBalances'])->name('admin.fiscalperiod.open-close-balances');
    Route::post('/admin/fiscalperiod/{fiscalperiod}/close', [FiscalPeriodController::class, 'closeperiod'])->name('admin.fiscalperiod.closeperiod');
    
    // Monthly Period Management Routes
    Route::get('/admin/fiscalperiod/{fiscalperiod}/monthly-periods', [FiscalPeriodController::class, 'monthlyPeriods'])->name('admin.fiscalperiod.monthly-periods');
    Route::get('/admin/fiscalperiod/{fiscalperiod}/monthly-period/{monthlyPeriod}', [FiscalPeriodController::class, 'showMonth'])->name('admin.fiscalperiod.monthly-period.show');
    Route::post('/admin/fiscalperiod/{fiscalperiod}/monthly-period/{monthlyPeriod}/close', [FiscalPeriodController::class, 'closeMonth'])->name('admin.fiscalperiod.monthly-period.close');
    Route::post('/admin/fiscalperiod/{fiscalperiod}/monthly-period/{monthlyPeriod}/reopen', [FiscalPeriodController::class, 'reopenMonth'])->name('admin.fiscalperiod.monthly-period.reopen');
    Route::post('/admin/fiscalperiod/{fiscalperiod}/recalculate-balances', [FiscalPeriodController::class, 'recalculateBalances'])->name('admin.fiscalperiod.recalculate-balances');

    // Reports & Export Routes
    Route::get('/admin/fiscalperiod/{fiscalperiod}/reports', [FiscalPeriodController::class, 'reports'])->name('admin.fiscalperiod.reports');
    Route::get('/admin/fiscalperiod/{fiscalperiod}/export-pdf', [FiscalPeriodController::class, 'exportPDF'])->name('admin.fiscalperiod.exportPDF');
    Route::get('/admin/fiscalperiod/{fiscalperiod}/export-csv', [FiscalPeriodController::class, 'exportCSV'])->name('admin.fiscalperiod.exportCSV');
    Route::get('/admin/fiscalperiod/{fiscalperiod}/monthly-period/{monthlyPeriod}/print', [FiscalPeriodController::class, 'printMonthlyPDF'])->name('admin.fiscalperiod.monthly-period.print');
    
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
Route::middleware(['auth', 'role:supervisor'])->prefix('supervisor')->group(function () {
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
    Route::get('/apartments', [SupervisorApartmentController::class, 'index'])->name('supervisor.apartments.index');
    Route::get('/apartments/{apartment}', [SupervisorApartmentController::class, 'show'])->name('supervisor.apartments.show');
    Route::post('/apartments/{apartment}/assign-tenant', [SupervisorApartmentController::class, 'assignTenant'])->name('supervisor.apartments.assignTenant');

    // Payment Management
    Route::get('/payments', [SupervisorPaymentController::class, 'index'])->name('supervisor.payments.index');
    Route::get('/payments/create', [SupervisorPaymentController::class, 'create'])->name('supervisor.payments.create');
    Route::post('/payments', [SupervisorPaymentController::class, 'store'])->name('supervisor.payments.store');
    Route::get('/payments/{payment}', [SupervisorPaymentController::class, 'show'])->name('supervisor.payments.show');
    // Donate view
    Route::view('/donate', 'supervisor.donate')->name('supervisor.donate');
});

require __DIR__.'/auth.php';

// DEV-ONLY: Temporary unauthenticated endpoint to generate apartment summary PDF for local testing.
// Remove this route after verification.
Route::get('/dev/generate-apartment-summary-pdf', [RevenueExpenseController::class, 'apartmentSummaryPdf']);

// DEV-ONLY: Simple test form to verify global submit spinner (no CSRF middleware)
Route::view('/dev/test-form', 'dev.test-form');
Route::post('/dev/test-submit', function (\Illuminate\Http\Request $request) {
    // simulate short processing
    sleep(1);
    return redirect('/dev/test-form')->with('success', 'Form submitted');
})->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
