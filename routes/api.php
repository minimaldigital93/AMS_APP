<?php

use App\Http\Controllers\Api\AuthController;

// Admin Controllers
use App\Http\Controllers\Api\Admin\AccountController;
use App\Http\Controllers\Api\Admin\ApartmentController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\FiscalPeriodController;
use App\Http\Controllers\Api\Admin\FloorController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\RentalController;
use App\Http\Controllers\Api\Admin\TenantController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\UtilityController;

// Supervisor Controllers
use App\Http\Controllers\Api\Supervisor\ApartmentController as SupervisorApartmentController;
use App\Http\Controllers\Api\Supervisor\DashboardController as SupervisorDashboardController;
use App\Http\Controllers\Api\Supervisor\PaymentController as SupervisorPaymentController;
use App\Http\Controllers\Api\Supervisor\RentalController as SupervisorRentalController;
use App\Http\Controllers\Api\Supervisor\TenantController as SupervisorTenantController;
use App\Http\Controllers\Api\Supervisor\UtilityController as SupervisorUtilityController;

// Tenant Controllers
use App\Http\Controllers\Api\Tenant\PaymentController as TenantPaymentController;
use App\Http\Controllers\Api\Tenant\ProfileController as TenantProfileController;
use App\Http\Controllers\Api\Tenant\RentalController as TenantRentalController;
use App\Http\Controllers\Api\Tenant\UtilityController as TenantUtilityController;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ============================================================================
// PUBLIC ROUTES
// ============================================================================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// ============================================================================
// AUTHENTICATED ROUTES (All Roles)
// ============================================================================
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes for all authenticated users
    Route::prefix('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    // ========================================================================
    // ADMIN ROUTES
    // ========================================================================
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        // Dashboard
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);
        Route::get('/dashboard/recent-activities', [AdminDashboardController::class, 'recentActivities']);

        // User Management (Admin Only)
        Route::get('/users/supervisors', [AdminUserController::class, 'supervisors']);
        Route::get('/users/role/{role}', [AdminUserController::class, 'byRole']);
        Route::apiResource('users', AdminUserController::class);

        // Floors (Full CRUD)
        Route::apiResource('floors', FloorController::class);

        // Apartments (Full CRUD)
        Route::get('apartments/available', [ApartmentController::class, 'available']);
        Route::apiResource('apartments', ApartmentController::class);

        // Tenants (Full CRUD)
        Route::get('tenants/active', [TenantController::class, 'active']);
        Route::post('tenants/{tenant}/archive', [TenantController::class, 'archive']);
        Route::apiResource('tenants', TenantController::class);

        // Rentals (Full CRUD)
        Route::get('rentals/active', [RentalController::class, 'active']);
        Route::apiResource('rentals', RentalController::class);

        // Payments (Full CRUD)
        Route::get('payments/overdue', [PaymentController::class, 'overdue']);
        Route::get('payments/statistics', [PaymentController::class, 'statistics']);
        Route::post('payments/{payment}/mark-paid', [PaymentController::class, 'markPaid']);
        Route::apiResource('payments', PaymentController::class);

        // Utilities (Full CRUD)
        Route::get('utilities/unpaid', [UtilityController::class, 'unpaid']);
        Route::post('utilities/{utility}/mark-paid', [UtilityController::class, 'markPaid']);
        Route::apiResource('utilities', UtilityController::class);

        // Accounts (Full CRUD)
        Route::get('accounts/summary', [AccountController::class, 'summary']);
        Route::apiResource('accounts', AccountController::class);

        // Fiscal Periods (Full CRUD)
        Route::get('fiscal-periods/current', [FiscalPeriodController::class, 'current']);
        Route::post('fiscal-periods/{fiscal_period}/close', [FiscalPeriodController::class, 'close']);
        Route::apiResource('fiscal-periods', FiscalPeriodController::class);
    });

    // ========================================================================
    // SUPERVISOR ROUTES
    // ========================================================================
    Route::prefix('supervisor')->middleware('role:supervisor')->group(function () {
        // Dashboard
        Route::get('/dashboard/stats', [SupervisorDashboardController::class, 'stats']);

        // Apartments (Managed by supervisor - limited access)
        Route::get('apartments/available', [SupervisorApartmentController::class, 'available']);
        Route::get('apartments', [SupervisorApartmentController::class, 'index']);
        Route::get('apartments/{apartment}', [SupervisorApartmentController::class, 'show']);
        Route::put('apartments/{apartment}', [SupervisorApartmentController::class, 'update']);
        Route::patch('apartments/{apartment}', [SupervisorApartmentController::class, 'update']);

        // Tenants (In managed apartments)
        Route::get('tenants/active', [SupervisorTenantController::class, 'active']);
        Route::post('tenants/{tenant}/archive', [SupervisorTenantController::class, 'archive']);
        Route::get('tenants', [SupervisorTenantController::class, 'index']);
        Route::post('tenants', [SupervisorTenantController::class, 'store']);
        Route::get('tenants/{tenant}', [SupervisorTenantController::class, 'show']);
        Route::put('tenants/{tenant}', [SupervisorTenantController::class, 'update']);
        Route::patch('tenants/{tenant}', [SupervisorTenantController::class, 'update']);

        // Rentals (In managed apartments)
        Route::get('rentals/active', [SupervisorRentalController::class, 'active']);
        Route::post('rentals/{rental}/end', [SupervisorRentalController::class, 'endRental']);
        Route::get('rentals', [SupervisorRentalController::class, 'index']);
        Route::post('rentals', [SupervisorRentalController::class, 'store']);
        Route::get('rentals/{rental}', [SupervisorRentalController::class, 'show']);
        Route::put('rentals/{rental}', [SupervisorRentalController::class, 'update']);
        Route::patch('rentals/{rental}', [SupervisorRentalController::class, 'update']);

        // Payments (In managed apartments)
        Route::get('payments/overdue', [SupervisorPaymentController::class, 'overdue']);
        Route::get('payments/statistics', [SupervisorPaymentController::class, 'statistics']);
        Route::post('payments/{payment}/mark-paid', [SupervisorPaymentController::class, 'markPaid']);
        Route::get('payments', [SupervisorPaymentController::class, 'index']);
        Route::post('payments', [SupervisorPaymentController::class, 'store']);
        Route::get('payments/{payment}', [SupervisorPaymentController::class, 'show']);
        Route::put('payments/{payment}', [SupervisorPaymentController::class, 'update']);
        Route::patch('payments/{payment}', [SupervisorPaymentController::class, 'update']);

        // Utilities (In managed apartments)
        Route::get('utilities/unpaid', [SupervisorUtilityController::class, 'unpaid']);
        Route::post('utilities/{utility}/mark-paid', [SupervisorUtilityController::class, 'markPaid']);
        Route::get('utilities', [SupervisorUtilityController::class, 'index']);
        Route::post('utilities', [SupervisorUtilityController::class, 'store']);
        Route::get('utilities/{utility}', [SupervisorUtilityController::class, 'show']);
        Route::put('utilities/{utility}', [SupervisorUtilityController::class, 'update']);
        Route::patch('utilities/{utility}', [SupervisorUtilityController::class, 'update']);
    });

    // ========================================================================
    // TENANT ROUTES
    // ========================================================================
    Route::prefix('tenant')->middleware('role:tenant')->group(function () {
        // Profile & Dashboard
        Route::get('/dashboard', [TenantProfileController::class, 'dashboard']);
        Route::get('/profile', [TenantProfileController::class, 'show']);
        Route::put('/profile', [TenantProfileController::class, 'update']);
        Route::patch('/profile', [TenantProfileController::class, 'update']);
        Route::post('/profile/password', [TenantProfileController::class, 'updatePassword']);

        // Rentals (Read-only, own rentals only)
        Route::get('rentals/current', [TenantRentalController::class, 'current']);
        Route::get('rentals', [TenantRentalController::class, 'index']);
        Route::get('rentals/{rental}', [TenantRentalController::class, 'show']);

        // Payments (Read-only, own payments only)
        Route::get('payments/pending', [TenantPaymentController::class, 'pending']);
        Route::get('payments/overdue', [TenantPaymentController::class, 'overdue']);
        Route::get('payments/history', [TenantPaymentController::class, 'history']);
        Route::get('payments/summary', [TenantPaymentController::class, 'summary']);
        Route::get('payments', [TenantPaymentController::class, 'index']);
        Route::get('payments/{payment}', [TenantPaymentController::class, 'show']);

        // Utilities (Read-only, own utilities only)
        Route::get('utilities/unpaid', [TenantUtilityController::class, 'unpaid']);
        Route::get('utilities/summary', [TenantUtilityController::class, 'summary']);
        Route::get('utilities', [TenantUtilityController::class, 'index']);
        Route::get('utilities/{utility}', [TenantUtilityController::class, 'show']);
    });
});
