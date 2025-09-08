<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\Auth\GetMeController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\PostLoginController;
use App\Http\Controllers\Api\V1\Auth\PostLogoutController;
use App\Http\Controllers\Api\V1\Auth\PostRegisterController;
use App\Http\Controllers\Api\V1\Auth\RequestPasswordResetController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::redirect('v1/sanctum/csrf-cookie', '/sanctum/csrf-cookie');

Route::group(['prefix' => 'v1'], function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', PostLoginController::class)->name('v1.auth.login');
        Route::post('register', PostRegisterController::class)->name('v1.auth.register');
        Route::post('request-password-reset', RequestPasswordResetController::class)->name('api.v1.auth.request-password-reset');
        Route::post('password-reset', PasswordResetController::class)->name('api.v1.auth.password-reset');
    });

    Route::group(['middleware' => ['auth:sanctum']], function () {
        Route::prefix('auth')->group(function () {
            Route::post('logout', PostLogoutController::class)->name('v1.auth.logout');
            Route::get('me', GetMeController::class)->name('v1.auth.me');
        });
    });
});