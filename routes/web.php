<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FloorController;
use App\Http\Controllers\Admin\ApartmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\TenantController;
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
    Route::get('/admin/tenants/archived', [TenantController::class, 'archived'])->name('admin.tenants.archived');
    Route::get('/admin/tenants/{tenant}/leave', [TenantController::class, 'leave'])->name('admin.tenants.leave');
});

require __DIR__.'/auth.php';
