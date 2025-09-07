
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\TransactionController;
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

    // Transaction routes
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/summary', [TransactionController::class, 'summary']);
    Route::get('/transactions/export', [TransactionController::class, 'export']);
    Route::get('/transactions/package/{packageId}', [TransactionController::class, 'byPackage']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
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
    Route::get('users/{user}/transactions', [\App\Http\Controllers\Admin\UserController::class, 'transactions']);

    // Transaction management
    Route::get('transactions', [\App\Http\Controllers\Admin\TransactionController::class, 'index']);
    Route::get('transactions/stats', [\App\Http\Controllers\Admin\TransactionController::class, 'stats']);
    Route::get('transactions/{transaction}', [\App\Http\Controllers\Admin\TransactionController::class, 'show']);
    Route::put('transactions/{transaction}/status', [\App\Http\Controllers\Admin\TransactionController::class, 'updateStatus']);
});
