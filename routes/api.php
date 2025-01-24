<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::group(['namespace' => 'App\Http\Controllers'], function () {

        Route::get('/user', [UserController::class, 'show'])->name('show');

        Route::get('/bots', [BotController::class, 'index'])->name('bots.index');

        Route::get('/bots/{bot}', [BotController::class, 'show'])->name('bots.show');

        Route::get('/get-bot-user-data/{bot}', [BotController::class, 'getBotUserData'])->name('bots.getBotUserData');

        Route::get('/bot-types', [BotController::class, 'getTypes'])->name('bot.types');

        Route::post('/bots/update/{bot}', [BotController::class, 'update'])->name('bot.update');

        Route::post('/bots/create', [BotController::class, 'create'])->name('bot.create');

        Route::delete('/bots/{bot}', [BotController::class, 'destroy'])->name('bots.destroy');

        Route::get('/users/export', [UsersController::class, 'export'])->name('users.export');

        Route::get('/users', [UsersController::class, 'index'])->name('user.index');

        Route::get('/users/{user}', [UsersController::class, 'show'])->name('user.show');

        Route::get('/users/{user}', [UsersController::class, 'show'])->name('user.show');

        Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('user.destroy');

        Route::get('/admins', [AdminController::class, 'index'])->name('admin.index');

        Route::get('/admins/{botAdmin}', [AdminController::class, 'show'])->name('admin.show');

        Route::delete('/admins/{botAdmin}', [AdminController::class, 'destroy'])->name('admin.destroy');

        Route::post('/admins', [AdminController::class, 'create'])->name('admin.create');

        Route::put('/admins/{botAdmin}', [AdminController::class, 'update'])->name('admin.update');

        Route::get('/managers', [ManagerController::class, 'index'])->name('manager.index');

        Route::get('/managers/{manager}', [ManagerController::class, 'show'])->name('manager.show');

        Route::delete('/managers/{manager}', [ManagerController::class, 'destroy'])->name('manager.destroy');

        Route::post('/managers', [ManagerController::class, 'create'])->name('manager.create');

        Route::put('/managers/{manager}', [ManagerController::class, 'update'])->name('manager.update');

        Route::get('/bot-managers', [BotController::class, 'getManagers'])->name('bot.managers');
    });
});
Route::group(['namespace' => 'App\Http\Controllers'], function () {

    Route::post('/webhook/bot/{bot}', [BotController::class, 'handle'])->name('bot.webhook.handle');

});

Route::group(['middleware' => 'check.api.key', 'namespace' => 'App\Http\Controllers'], function () {
    Route::post('/sync-calories-user', [SyncController::class, 'storeNewUser'])
        ->name('sync-calories-user.store');
});
