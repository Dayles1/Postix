<?php

use App\Http\Controllers\Api\Telegram\OperationUserController;
use App\Http\Controllers\Api\Telegram\ResolvedPhoneController;
use App\Http\Controllers\Api\Telegram\TelegramDriverController;
use App\Http\Controllers\ExternalApi\WareHouseController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\View\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\View\Admin\SuperAdminUserController;
use App\Http\Controllers\View\Admin\UserController as AdminUserController;
use App\Http\Controllers\View\AuthController;
use App\Http\Controllers\View\BanController;
use App\Http\Controllers\View\CatalogController;
use App\Http\Controllers\View\Department\DepartmentController;
use App\Http\Controllers\View\Department\UserDepartmenIndexController;
use App\Http\Controllers\View\Department\UserDepartmentController;
use App\Http\Controllers\View\DriverCheck\DriverCheckController;
use App\Http\Controllers\View\MessageGroupController;
use App\Http\Controllers\View\Messages\ShowController;
use App\Http\Controllers\View\ProfileController;
use App\Http\Controllers\View\TelegramController;
use App\Http\Controllers\View\UserController;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/methods/web.php';

Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'authenticate']);

Route::middleware(['auth', 'check.ban'])->group(function () {

    Route::middleware('role:superadmin')->group(function () {
        Route::get('/export', [WareHouseController::class, 'checkedQozoqPage'])->name('warehouse.checkedQozoqPage')->middleware('permission:nav:export'); //export
        Route::get('/checked-qozoq/export', [WareHouseController::class, 'chekedQozoqExport'])->name('warehouse.chekedQozoqExport');
        Route::get('/import', [WareHouseController::class, 'importQozoqPage'])->name('warehouse.importQozoqPage')->middleware('permission:nav:import');
        Route::get('/import-qozoq/export', [WareHouseController::class, 'importQozoqExport'])->name('warehouse.importQozoqExport');
        Route::get('/turkey', [WareHouseController::class, 'turkey'])->name('warehouse.turkey')->middleware('permission:nav:turkey');

    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DepartmentController::class, 'index'])->name('departments.index');

    Route::middleware('role:superadmin')->group(function () {
        Route::get('departments/deleted', [DepartmentController::class, 'deleted'])->name('departments.deleted');
        Route::get('/free', [DepartmentController::class, 'free'])->name('departments.free');
        Route::get('/free-banned', [DepartmentController::class, 'freeBanned'])->name('departments.free-banned');

        // Route::get('/free-users', [DepartmentController::class, 'freeUsers'])->name('departments.free-users');
        // Route::get('/free-users-banned', [DepartmentController::class, 'freeUsersBanned'])->name('departments.free-users-banned');

        // Route::get('/pro-users', [DepartmentController::class, 'proUsers'])->name('departments.pro-users');
        Route::get('/pro-users', [UserDepartmenIndexController::class, 'proUsers'])->name('departments.pro-users');
        
        Route::get('departments/create', [DepartmentController::class, 'create'])->name('departments.create');

        Route::get('/free-users', [UserDepartmenIndexController::class, 'freeUsers'])->name('departments.free-users');
        Route::get('/free-users-banned', [UserDepartmenIndexController::class, 'freeUsersBanned'])->name('departments.free-users-banned');

        Route::get('user-departments/create', [UserDepartmentController::class, 'create'])->name('user.departments.create');
        Route::post('user-departments/store', [UserDepartmentController::class, 'store'])->name('user.departments.store');

        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::post('/departments/{department}/upgrade', [DepartmentController::class, 'upgrade'])->name('departments.upgrade');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::put('/departments/restore/{department}', [DepartmentController::class, 'update'])->name('departments.restore');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
        Route::get('/logs/{type}', [LogController::class, 'index'])->name('logs.index')->middleware('permission:nav:logs');
        Route::get('/logs/show/{audit}', [LogController::class, 'show'])->name('logs.show')->middleware('permission:nav:logs');
    });
    Route::get('/departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
    Route::get('/departments/{id}/history', [DepartmentController::class, 'history'])->name('departments.history');
    Route::get('/departments/{id}/users', [DepartmentController::class, 'users'])->name('departments.users');
    Route::get('/departments/{department}/pending', [MessageGroupController::class, 'getPending'])->name('departments.getPending');
    Route::get('/departments/{id}/send-messages', [MessageGroupController::class, 'sendForm'])->name('telegram.send-form');
    Route::post('/send-messages', [MessageGroupController::class, 'sendMassMessage'])->name('telegram.send-messages');
    Route::put('/message-groups/{id}/update', [MessageGroupController::class, 'update'])->name('message-groups.update');


    // Route::get('/operations/{MessageGroup}', [MessageGroupController::class, 'show'])->name('operations.show');

    Route::get('/operations/{messageGroup}', [ShowController::class, 'show'])->name('operations.show');
    Route::get('/operations/{messageGroup}/peers', [ShowController::class, 'peers'])->name('operations.peers');
    Route::get('/operations/{messageGroup}/peer-messages', [ShowController::class, 'peerMessages'])->name('operations.peer-messages');
    Route::post('/catalogs/remove-peer', [ShowController::class, 'removePeer'])->name('admin.catalogs.remove-peer');
    Route::post('/catalogs/remove-peers', [ShowController::class, 'removePeers'])->name('admin.catalogs.remove-peers');

    Route::get('/departments/{department}/catalogs', [CatalogController::class, 'index'])->name('catalogs.index');
    Route::post('/departments/{department}/catalogs', [CatalogController::class, 'store'])->name('catalogs.store');
    Route::get('/departments/{department}/catalogs/{catalog}', [CatalogController::class, 'show'])->name('catalogs.show');
    Route::put('/departments/{department}/catalogs/{catalog}', [CatalogController::class, 'update'])->name('catalogs.update');
    Route::delete('/departments/{department}/catalogs/{catalog}', [CatalogController::class, 'destroy'])->name('catalogs.destroy');
    Route::middleware('role:admin,user')->group(function () {
        Route::get('/new-telegram-users', [AdminUserController::class, 'newTelegramUsers'])->name('user.telegram.new-users');
        Route::get('/users', [DepartmentController::class, 'users'])->name('departments.users.global');
        Route::get('/catalogs', [CatalogController::class, 'index'])->name('catalogs.index.global');
        Route::get('/send-messages', [MessageGroupController::class, 'sendForm'])->name('telegram.send-form.global');
        Route::get('/history', [DepartmentController::class, 'history'])->name('departments.history.global');
    });
    Route::get('/profile/{user}', [ProfileController::class, 'profile'])->name('users.profile');
    Route::post('/profile/{user}', [ProfileController::class, 'update'])->name('users.profile.update');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::get('/departments/{department}/new-telegram-users', [AdminUserController::class, 'newTelegramUsers'])->name('admin.telegram.new-users');
    Route::post('/admin/telegram/create-user/{department}', [AdminUserController::class, 'createTelegramUser'])->name('admin.telegram.create_user');
    Route::get('phones/', [TelegramController::class, 'showLoginForm'])->name('telegram.login');
    Route::post('phones/send', [TelegramController::class, 'sendPhone'])->name('telegram.sendPhone');
    Route::post('phones/verify', [TelegramController::class, 'sendCode'])->name('telegram.sendCode');
    Route::post('tg/password', [TelegramController::class, 'completeLogin'])->name('telegram.password');
    Route::post('/telegram/logout', [TelegramController::class, 'logout'])->name('telegram.logout');
    Route::post('tg/status', [TelegramController::class, 'status'])->name('telegram.status');
    Route::post('/message-groups/{group}/cancel', [TelegramController::class, 'cancel'])->name('message-groups.cancel');
    Route::middleware('role:superadmin,admin')->group(function () {
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/admin/ban-unban', [BanController::class, 'banUnban'])->name('admin.ban-unban');
    });
    Route::prefix('/admin')->group(function () {
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::post('/users/{user}/destroy', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::post('/telegram/phone', [AdminUserController::class, 'sendPhone'])->name('admin.telegram.send');
        Route::post('/telegram/login', [AdminUserController::class, 'storeUserWithTelegram'])->name('admin.telegram.login');
        Route::post('/telegram/user-logout', [AdminUserController::class, 'logoutUserFromTelegram'])->name('admin.telegram.user-logout');
    });
    Route::prefix('superadmin')->middleware(['role:superadmin', 'permission:nav:users'])->group(function () {
        Route::get('/', [SuperAdminUserController::class, 'index'])->name('superadmin.index');
        Route::post('/', [SuperAdminUserController::class, 'store'])->name('superadmin.store');
        Route::get('/{user}', [SuperAdminUserController::class, 'show'])->name('superadmin.show');
        Route::put('/{user}', [SuperAdminUserController::class, 'update'])->name('superadmin.update');
        Route::delete('/{user}', [SuperAdminUserController::class, 'destroy'])->name('superadmin.destroy');
    });

});
Route::prefix('admin')->middleware(['auth', 'permission:nav:catalogs'])->group(function () {
    Route::get('/catalogs', [AdminCatalogController::class, 'index'])->name('admin.catalogs.index');
    Route::post('/catalogs', [AdminCatalogController::class, 'store'])->name('admin.catalogs.store');
    Route::get('/catalogs/{catalog}', [AdminCatalogController::class, 'show'])->name('admin.catalogs.show');
    Route::put('/catalogs/{catalog}', [AdminCatalogController::class, 'update'])->name('admin.catalogs.update');
    Route::delete('/catalogs/{catalog}', [AdminCatalogController::class, 'destroy'])->name('admin.catalogs.destroy');
});


Route::middleware(['auth'])
    ->prefix('driver-check')
    ->group(function () {

        /*
         * /driver-check
         * Redirect to the first page.
         */
        Route::get('/', function () {
            return redirect()->route(
                'driver-check.operation-users'
            );
        })->name('driver-check.dashboard');

        /*
         * Operation Users
         */
        Route::get(
            '/operation-users',
            [DriverCheckController::class, 'operationUsers']
        )->name('driver-check.operation-users');

        Route::get(
            '/operation-users/{operationUser}',
            [DriverCheckController::class, 'operationUser']
        )->name('driver-check.operation-user');
        /*
         * Drivers
         */
        Route::get(
            '/drivers',
            [DriverCheckController::class, 'drivers']
        )->name('driver-check.drivers');

        /*
         * Resolved Phones
         */
        Route::get(
            '/resolved-phones',
            [DriverCheckController::class, 'resolvedPhones']
        )->name('driver-check.resolved-phones');
    });


/*
|--------------------------------------------------------------------------
| Driver Check Data API
|--------------------------------------------------------------------------
|
| Pages fetch data from these endpoints.
|
*/

Route::middleware(['auth'])
    ->prefix('api/telegram')
    ->group(function () {

        /*
         * Operation Users
         */
        Route::get(
            '/operation-users',
            [OperationUserController::class, 'index']
        )->name('api.telegram.operation-users');

        Route::get(
            '/operation-users/{operationUser}',
            [OperationUserController::class, 'show']
        )->name('api.telegram.operation-users.show');
        Route::get(
    '/operation-users/{operationUser}/drivers',
    [OperationUserController::class, 'drivers']
)->name('api.telegram.operation-users.drivers');
        /*
         * Drivers
         */
        Route::get(
            '/drivers',
            [TelegramDriverController::class, 'index']
        )->name('api.telegram.drivers');

        /*
         * Resolved Phones
         */
        Route::get(
            '/resolved-phones',
            [ResolvedPhoneController::class, 'index']
        )->name('api.telegram.resolved-phones');
    });
    