
<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IncomeController;

Route::post('/register',[AuthController::class,'register']);
Route::post('/login',[AuthController::class,'login']);

Route::middleware('auth:sanctum')->group(function() {
    Route::post('/distribute-income/{user}',[IncomeController::class,'distribute']);
});
