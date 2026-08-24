<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Telegram\TelegramAccountController;
use App\Http\Controllers\Api\Telegram\TelegramDriverCheckController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

Route::get('/test', [TestController::class, 'test']);

// Auth
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('telegram/accounts')->group(function () {
        Route::get('/', [TelegramAccountController::class, 'index']);
        Route::post('/auth', [TelegramAccountController::class, 'auth']);
        Route::post('/verify-code', [TelegramAccountController::class, 'verifyCode']);
        Route::post('/logout', [TelegramAccountController::class, 'logout']);
        Route::get('/{id}', [TelegramAccountController::class, 'show']);
    });
Route::post(
    '/admin/telegram/driver-check/start',
    [TelegramDriverCheckController::class, 'start']
)->name('admin.telegram.driver-check.start');
});
