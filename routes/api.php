<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\Auth\PostLoginController;
use App\Http\Controllers\Api\V1\Auth\PostRegisterController;

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
    });

    Route::group(['middleware' => ['auth:sanctum']], function () {
    });
});