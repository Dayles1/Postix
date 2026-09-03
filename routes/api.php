<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Telegram\DriverCheckExportController;
use App\Http\Controllers\Api\Telegram\OperationUserController;
use App\Http\Controllers\Api\Telegram\ResolvedPhoneController;
use App\Http\Controllers\Api\Telegram\TelegramAccountController;
use App\Http\Controllers\Api\Telegram\TelegramDriverCheckController;
use App\Http\Controllers\Api\Telegram\TelegramDriverController;
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
        Route::post('/manage-failures', [TelegramAccountController::class, 'manageFailures']);
    });
Route::post(
    '/admin/telegram/driver-check/start',
    [TelegramDriverCheckController::class, 'start']
)->name('admin.telegram.driver-check.start');



});

Route::prefix('telegram/driver-check/export')
    ->group(function () {
        Route::get(
            '/operators',
            [DriverCheckExportController::class, 'operators'],
        )->name('telegram.driver-check.export.operators');

        Route::get(
            '/details',
            [DriverCheckExportController::class, 'details'],
        )->name('telegram.driver-check.export.details');
    });