
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\UserController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/admin/login', [AuthController::class, 'adminLogin']);
Route::post('/customer/login', [AuthController::class, 'customerLogin']);
Route::get('/packages', [PackageController::class, 'index']); // list packages

// Customer routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [UserController::class, 'profile']);
    Route::get('/my-tree', [UserController::class, 'tree']);
    Route::get('/income-history', [UserController::class, 'incomeHistory']);
    Route::post('/purchase-package', [PackageController::class, 'purchase']);
    Route::post('/distribute-income/{user}', [IncomeController::class, 'distribute']);
});

// Admin routes with prefix
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Dashboard
    Route::get('dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index']);

    // Package management
    Route::get('packages-stats', [\App\Http\Controllers\Admin\PackageController::class, 'stats']);
    Route::apiResource('packages', \App\Http\Controllers\Admin\PackageController::class);

    // User management
    Route::get('users-stats', [\App\Http\Controllers\Admin\UserController::class, 'stats']);
    Route::apiResource('users', \App\Http\Controllers\Admin\UserController::class);
    Route::get('users/{user}/tree', [\App\Http\Controllers\Admin\UserController::class, 'tree']);
    Route::get('users/{user}/incomes', [\App\Http\Controllers\Admin\UserController::class, 'incomes']);
});
