
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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [UserController::class, 'profile']);
    Route::get('/my-tree', [UserController::class, 'tree']);
    Route::get('/income-history', [UserController::class, 'incomeHistory']);
    Route::post('/purchase-package', [PackageController::class, 'purchase']);
    Route::post('/distribute-income/{user}', [IncomeController::class, 'distribute']);
});
