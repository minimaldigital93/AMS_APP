<?php

use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
  

Route::get('/', function () {
    return view('auth.login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/admin/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:admin'])
    ->name('admin.dashboard');

Route::get('/supervisor/dashboard', function () {
    return view('supervisor.dashboard');
})->middleware(['auth', 'verified', 'role:supervisor'])->name('supervisor.dashboard');

Route::get('/tenant/dashboard', function () {
    return view('tenant.dashboard');
})->middleware(['auth', 'verified', 'role:tenant'])->name('tenant.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin User Management Routes
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/users/{user}/permissions', [UserController::class, 'assignPermissions'])->name('admin.users.permissions');
});

require __DIR__.'/auth.php';
