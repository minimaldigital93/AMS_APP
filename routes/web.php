<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\FloorController;
use App\Http\Controllers\Admin\ApartmentController;
use App\Http\Controllers\Supervisor\DashboardController as SupervisorDashboardController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use Illuminate\Support\Facades\Route;
  

Route::get('/', function () {
    return view('auth.login');
});

//Route for dashboard and role-based access control
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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

// Admin User Management Routes
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/users/{user}/permissions', [UserController::class, 'assignPermissions'])->name('admin.users.permissions');
    
    // Property Management Routes
    Route::get('/admin/propertyManagement/floors', [FloorController::class, 'index'])->name('admin.propertymanagement.floors.index');
    Route::post('/admin/propertyManagement/floors', [FloorController::class, 'store'])->name('admin.propertymanagement.floors.store');
    Route::put('/admin/propertyManagement/floors/{floor}', [FloorController::class, 'update'])->name('admin.propertymanagement.floors.update');
    Route::delete('/admin/propertyManagement/floors/{floor}', [FloorController::class, 'destroy'])->name('admin.propertymanagement.floors.destroy');
    Route::get('/admin/propertyManagement/floors/{floor}/apartments', [FloorController::class, 'getApartments'])->name('admin.propertymanagement.floors.apartments');
    
    // Apartment Management Routes
    Route::get('/admin/propertyManagement/apartments', [ApartmentController::class, 'index'])->name('admin.propertymanagement.apartments.index');
    Route::post('/admin/propertyManagement/apartments', [ApartmentController::class, 'store'])->name('admin.propertymanagement.apartments.store');
    Route::put('/admin/propertyManagement/apartments/{apartment}', [ApartmentController::class, 'update'])->name('admin.propertymanagement.apartments.update');
    Route::delete('/admin/propertyManagement/apartments/{apartment}', [ApartmentController::class, 'destroy'])->name('admin.propertymanagement.apartments.destroy');
    
    // Tenant Management Routes
    Route::get('/admin/tenants', [TenantController::class, 'index'])->name('admin.tenants.index');
    Route::get('/admin/tenants/archived', [TenantController::class, 'archived'])->name('admin.tenants.archived');
    Route::get('/admin/tenants/leave/{tenant}', [TenantController::class, 'leave'])->name('admin.tenants.leave');
});

require __DIR__.'/auth.php';
