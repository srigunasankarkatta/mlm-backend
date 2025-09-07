
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\Customer\CustomerWalletController;
use App\Http\Controllers\Admin\AdminWalletController;

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

    // Customer Wallet routes
    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [CustomerWalletController::class, 'getBalances']);
        Route::get('/transactions', [CustomerWalletController::class, 'getTransactions']);
        Route::get('/transactions/{id}', [CustomerWalletController::class, 'getTransaction']);
        Route::get('/summary', [CustomerWalletController::class, 'getSummary']);
        Route::get('/dashboard-stats', [CustomerWalletController::class, 'getDashboardStats']);
        Route::get('/monthly-earnings', [CustomerWalletController::class, 'getMonthlyEarnings']);
        Route::get('/activity-feed', [CustomerWalletController::class, 'getActivityFeed']);
        Route::get('/export-transactions', [CustomerWalletController::class, 'exportTransactions']);
        Route::post('/withdraw', [CustomerWalletController::class, 'requestWithdrawal']);
        Route::get('/withdrawals', [CustomerWalletController::class, 'getWithdrawals']);
        Route::get('/withdrawals/{id}', [CustomerWalletController::class, 'getWithdrawal']);
        Route::get('/withdrawal-limits', [CustomerWalletController::class, 'getWithdrawalLimits']);
    });
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

    // Wallet management
    Route::prefix('wallets')->group(function () {
        Route::get('/', [AdminWalletController::class, 'getWallets']);
        Route::get('/statistics', [AdminWalletController::class, 'getStatistics']);
        Route::get('/dashboard', [AdminWalletController::class, 'getDashboard']);
        Route::get('/{id}', [AdminWalletController::class, 'getWallet']);
        Route::post('/credit', [AdminWalletController::class, 'creditWallet']);
        Route::post('/debit', [AdminWalletController::class, 'debitWallet']);
        Route::get('/users/{userId}', [AdminWalletController::class, 'getUserWallets']);
    });

    // Wallet transactions management
    Route::prefix('wallet-transactions')->group(function () {
        Route::get('/', [AdminWalletController::class, 'getTransactions']);
        Route::get('/{id}', [AdminWalletController::class, 'getTransaction']);
        Route::get('/export/csv', [AdminWalletController::class, 'exportTransactions']);
    });

    // Withdrawals management
    Route::prefix('withdrawals')->group(function () {
        Route::get('/', [AdminWalletController::class, 'getWithdrawals']);
        Route::get('/{id}', [AdminWalletController::class, 'getWithdrawal']);
        Route::put('/{id}/process', [AdminWalletController::class, 'processWithdrawal']);
        Route::get('/export/csv', [AdminWalletController::class, 'exportWithdrawals']);
    });
});
