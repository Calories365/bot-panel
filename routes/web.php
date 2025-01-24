<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::group(['namespace' => 'App\Http\Controllers'], function () {
    Route::get('/images/{filename}', [ImageController::class, 'show'])->name('image.show');

//    Route::get('/builder', [BuilderController::class, 'index'])->name('builder.index');

    Route::get('/download/{filename}', [FileController::class, 'download'])->name('file.download');

    Route::get('/{any}', function () {
        return view('app');
    })->where('any', '^(?!api|images).*$');

    Route::get('/{any}', function () {
        return view('app');
    })->where('any', '^(?!api|images).*$')->name('login');
});

Route::get('/reset-password/{token}', function ($token) {
    return view('auth.password-reset', ['token' => $token]);
})
    ->middleware(['guest:' . config('fortify.guard')])
    ->name('password.reset');

