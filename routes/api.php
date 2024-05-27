<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BotController;
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

        Route::get('/bot-types', [BotController::class, 'getTypes'])->name('bot.types');

        Route::post('/bots/update/{bot}', [BotController::class, 'update'])->name('bot.update');

        Route::post('/bots/create', [BotController::class, 'create'])->name('bot.create');

        Route::delete('/bots/{bot}', [BotController::class, 'destroy'])->name('bots.destroy');

        Route::get('/users', [UsersController::class, 'index'])->name('user.index');

        Route::get('/users/{user}', [UsersController::class, 'show'])->name('user.show');

        Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('user.destroy');

        Route::get('/admins', [AdminController::class, 'index'])->name('admin.index');

        Route::get('/admins/{admin}', [AdminController::class, 'show'])->name('admin.show');

        Route::delete('/admins/{botAdmin}', [AdminController::class, 'destroy'])->name('admin.destroy');
    });
});


