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
use App\Http\Controllers\Api\V1\Files\GetFileContentController;
use App\Http\Controllers\Api\V1\Files\DownloadFileController;
use App\Http\Controllers\Api\V1\Models\DownloadZipController;
use App\Http\Controllers\Api\V1\Categories\GetCategoriesController;
use App\Http\Controllers\Api\V1\Tags\GetTagsController;
use App\Http\Controllers\Api\V1\Models\PostLikeController;
use App\Http\Controllers\Api\V1\Models\PostUnlikeController;
use App\Http\Controllers\Api\V1\Models\PostCommentController;
use App\Http\Controllers\Api\V1\Comments\GetCommentsController;
use App\Http\Controllers\Api\V1\Comments\PatchCommentController;
use App\Http\Controllers\Api\V1\Comments\DeleteCommentController;
use App\Http\Controllers\Api\V1\Datasets\PostDatasetController;
use App\Http\Controllers\Api\V1\Datasets\DownloadZipController as DatasetDownloadZipController;
use App\Http\Controllers\Api\V1\Datasets\GetDatasetsController;
use App\Http\Controllers\Api\V1\Datasets\GetDatasetController;
use App\Http\Controllers\Api\V1\Datasets\GetFilesController as DatasetGetFilesController;
use App\Http\Controllers\Api\V1\Datasets\GetFileTreeController as DatasetGetFileTreeController;
use App\Http\Controllers\Api\V1\Datasets\PatchDatasetController;
use App\Http\Controllers\Api\V1\Datasets\DeleteDatasetController;
use App\Http\Controllers\Api\V1\Datasets\PostLikeController as DatasetPostLikeController;
use App\Http\Controllers\Api\V1\Datasets\PostUnlikeController as DatasetPostUnlikeController;
use App\Http\Controllers\Api\V1\Datasets\PostCommentController as DatasetPostCommentController;
use App\Http\Controllers\Api\V1\Search\SearchRepositoriesController;
use App\Http\Controllers\Api\V1\Stats\GetStatsController;
use App\Http\Controllers\Api\V1\Featured\GetFeaturedRepositoriesController;
use App\Http\Controllers\Api\V1\Trending\GetTrendingRepositoriesController;
use App\Http\Controllers\Api\V1\Models\ReviewModelController;
use App\Http\Controllers\Api\V1\Datasets\ReviewDatasetController;
use App\Http\Controllers\Api\V1\ApprovalHistory\GetApprovalHistoryController;
use App\Http\Controllers\Api\V1\ApprovalHistory\GetApprovalHistoryStatsController;
use App\Http\Controllers\Api\V1\Embeddings\PostDatasetEmbeddingController;
use App\Http\Controllers\Api\V1\Embeddings\PostDatasetSearchController;
use App\Http\Controllers\Api\V1\Embeddings\DeleteDatasetEmbeddingController;
use App\Http\Controllers\Api\V1\Embeddings\GetDatasetEmbeddingController;
use App\Http\Controllers\Api\V1\AI\PostRAGQueryController;
use App\Http\Controllers\Api\V1\AI\PostRAGChatController;
use App\Http\Controllers\Api\V1\Debug\SearchDebugController;

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
        Route::post('login', PostLoginController::class)->name('v1.auth.login')->middleware('throttle:login');
        Route::post('register', PostRegisterController::class)->name('v1.auth.register')->middleware('throttle:auth');
        Route::post('request-password-reset', RequestPasswordResetController::class)->name('api.v1.auth.request-password-reset')->middleware('throttle:auth');
        Route::post('password-reset', PasswordResetController::class)->name('api.v1.auth.password-reset')->middleware('throttle:auth');
        
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
            Route::patch('{uuid}/review', ReviewModelController::class)->name('v1.models.review');
        });

        Route::prefix('datasets')->group(function () {
            Route::get('/', GetDatasetsController::class)->name('v1.datasets.index');
            Route::post('/', PostDatasetController::class)->name('v1.datasets.store');
            Route::get('{uuid}', GetDatasetController::class)->name('v1.datasets.show');
            Route::patch('{uuid}', PatchDatasetController::class)->name('v1.datasets.update');
            Route::delete('{uuid}', DeleteDatasetController::class)->name('v1.datasets.destroy');
            Route::get('{uuid}/download', DatasetDownloadZipController::class)->name('v1.datasets.download');
            Route::get('{uuid}/files', DatasetGetFilesController::class)->name('v1.datasets.files.index');
            Route::get('{uuid}/tree', DatasetGetFileTreeController::class)->name('v1.datasets.tree.show');
            Route::post('{uuid}/like', DatasetPostLikeController::class)->name('v1.datasets.like');
            Route::post('{uuid}/unlike', DatasetPostUnlikeController::class)->name('v1.datasets.unlike');
            Route::post('{uuid}/comments', DatasetPostCommentController::class)->name('v1.datasets.comments.store');
            Route::patch('{uuid}/review', ReviewDatasetController::class)->name('v1.datasets.review');
        });

        Route::prefix('files')->group(function () {
            Route::get('{uuid}/download', DownloadFileController::class)->name('v1.models.files.download');
            Route::get('{uuid}/content', GetFileContentController::class)->name('v1.models.files.content');
        });
        
        Route::prefix('comments')->group(function () {
            Route::get('/', GetCommentsController::class)->name('v1.comments.index');
            Route::patch('{uuid}', PatchCommentController::class)->name('v1.comments.update');
            Route::delete('{uuid}', DeleteCommentController::class)->name('v1.comments.destroy');
        });

        Route::get('categories', GetCategoriesController::class)->name('v1.categories.index');
        Route::get('tags', GetTagsController::class)->name('v1.tags.index');
        
        Route::prefix('repositories')->group(function () {
            Route::get('featured', GetFeaturedRepositoriesController::class)->name('v1.repositories.featured');
            Route::get('search', SearchRepositoriesController::class)->name('v1.repositories.search');
            Route::get('trending', GetTrendingRepositoriesController::class)->name('v1.repositories.trending');
        });
        
        Route::prefix('approval-history')->group(function () {
            Route::get('/', GetApprovalHistoryController::class)->name('v1.approval-history.index');
            Route::get('stats', GetApprovalHistoryStatsController::class)->name('v1.approval-history.stats');
        });

        Route::prefix('embeddings')->group(function () {
            Route::post('datasets/{uuid}', PostDatasetEmbeddingController::class)->name('v1.embeddings.datasets.store');
            Route::get('datasets/{uuid}', GetDatasetEmbeddingController::class)->name('v1.embeddings.datasets.show');
            Route::post('datasets/{uuid}/search', PostDatasetSearchController::class)->name('v1.embeddings.datasets.search');
            Route::delete('datasets/{uuid}', DeleteDatasetEmbeddingController::class)->name('v1.embeddings.datasets.destroy');
        });

        Route::prefix('ai')->group(function () {
            Route::post('datasets/{uuid}/query', PostRAGQueryController::class)->name('v1.ai.datasets.query');
            Route::post('datasets/{uuid}/chat', PostRAGChatController::class)->name('v1.ai.datasets.chat');
        });

        Route::prefix('debug')->group(function () {
            Route::post('datasets/{uuid}/search', SearchDebugController::class)->name('v1.debug.datasets.search');
        });
        
        Route::get('stats', GetStatsController::class)->name('v1.stats.general');
     });
});