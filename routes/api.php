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
use App\Http\Controllers\Api\V1\Models\GetModelsController;
use App\Http\Controllers\Api\V1\Models\GetModelController;
use App\Http\Controllers\Api\V1\Models\PatchModelController;
use App\Http\Controllers\Api\V1\Models\DeleteModelController;
use App\Http\Controllers\Api\V1\Models\GetFilesController;
use App\Http\Controllers\Api\V1\Models\GetFileTreeController;
use App\Http\Controllers\Api\V1\Models\GetFileContentController;
use App\Http\Controllers\Api\V1\Models\DownloadFileController;
use App\Http\Controllers\Api\V1\Models\DownloadZipController;
use App\Http\Controllers\Api\V1\Categories\GetCategoriesController;
use App\Http\Controllers\Api\V1\Tags\GetTagsController;
use App\Http\Controllers\Api\V1\ReligiousMovements\GetReligiousMovementsController;
use App\Http\Controllers\Api\V1\Models\PostLikeController;
use App\Http\Controllers\Api\V1\Models\PostUnlikeController;
use App\Http\Controllers\Api\V1\Models\PostCommentController;
use App\Http\Controllers\Api\V1\Comments\PatchCommentController;
use App\Http\Controllers\Api\V1\Comments\DeleteCommentController;

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
        Route::prefix('models')->group(function () {
            Route::get('/', GetModelsController::class)->name('v1.models.index');
            Route::post('/', PostModelController::class)->name('v1.models.store');
            Route::get('{uuid}', GetModelController::class)->name('v1.models.show');
            Route::patch('{uuid}', PatchModelController::class)->name('v1.models.update');
            Route::delete('{uuid}', DeleteModelController::class)->name('v1.models.destroy');
            Route::get('{uuid}/files', GetFilesController::class)->name('v1.models.files.index');
            Route::get('{uuid}/tree', GetFileTreeController::class)->name('v1.models.tree.show');
            Route::get('{uuid}/download', DownloadZipController::class)->name('v1.models.download');
            Route::post('{uuid}/like', PostLikeController::class)->name('v1.models.like');
            Route::post('{uuid}/unlike', PostUnlikeController::class)->name('v1.models.unlike');
            Route::post('{uuid}/comments', PostCommentController::class)->name('v1.models.comments.store');    
        });

        Route::prefix('files')->group(function () {
            Route::get('{uuid}/download', DownloadFileController::class)->name('v1.models.files.download');
            Route::get('{uuid}/content', GetFileContentController::class)->name('v1.models.files.content');
        });
        
        Route::patch('comments/{uuid}', PatchCommentController::class)->name('v1.comments.update');
        Route::delete('comments/{uuid}', DeleteCommentController::class)->name('v1.comments.destroy');

        Route::get('categories', GetCategoriesController::class)->name('v1.categories.index');
        Route::get('tags', GetTagsController::class)->name('v1.tags.index');
        Route::get('religious-movements', GetReligiousMovementsController::class)->name('v1.religious-movements.index');
     });
});