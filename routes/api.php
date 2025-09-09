<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\Auth\ChangePasswordController;
use App\Http\Controllers\Api\V1\Auth\GetMeController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\PostLoginController;
use App\Http\Controllers\Api\V1\Auth\PostLogoutController;
use App\Http\Controllers\Api\V1\Auth\PostRegisterController;
use App\Http\Controllers\Api\V1\Auth\RequestPasswordResetController;
use App\Http\Controllers\Api\V1\Models\PostModelController;
use App\Http\Controllers\Api\V1\Models\GetModelController;
use App\Http\Controllers\Api\V1\Models\GetFilesController;
use App\Http\Controllers\Api\V1\Models\GetFileTreeController;
use App\Http\Controllers\Api\V1\Models\GetFileContentController;
use App\Http\Controllers\Api\V1\Models\DownloadFileController;
use App\Http\Controllers\Api\V1\Models\DownloadZipController;

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
        
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::post('logout', PostLogoutController::class)->name('v1.auth.logout');
            Route::get('me', GetMeController::class)->name('v1.auth.me');
            Route::patch('change-password', ChangePasswordController::class)->name('v1.auth.change-password');
        });
     });

     Route::group(['middleware' => ['auth:sanctum']], function () {
         // Model routes
        Route::prefix('models')->group(function () {
            Route::post('/', PostModelController::class)->name('v1.models.store');
            Route::get('{uuid}', GetModelController::class)->name('v1.models.show');
            Route::get('{uuid}/files', GetFilesController::class)->name('v1.models.files.index');
            Route::get('{uuid}/tree', GetFileTreeController::class)->name('v1.models.tree.show');
            Route::get('{uuid}/download', DownloadZipController::class)->name('v1.models.download');
            
            // File routes
            Route::prefix('files')->group(function () {
                Route::get('{uuid}/download', DownloadFileController::class)->name('v1.models.files.download');
                Route::get('{uuid}/content', GetFileContentController::class)->name('v1.models.files.content');
            });
        });
     });
});