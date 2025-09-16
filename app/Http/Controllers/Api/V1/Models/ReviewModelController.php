<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Review\ReviewRepositoryRequest;
use App\Services\Api\V1\ReviewService;
use App\Transformers\ModelRepositoryTransformer;
use Illuminate\Http\JsonResponse;
use App\Models\ModelRepository;
use Exception;

class ReviewModelController extends Controller
{
    private ReviewService $reviewService;
    private ModelRepositoryTransformer $modelTransformer;

    public function __construct(ReviewService $reviewService, ModelRepositoryTransformer $modelTransformer)
    {
        $this->reviewService = $reviewService;
        $this->modelTransformer = $modelTransformer;
    }

    /**
     * Review a model (approve or decline).
     */
    public function __invoke(ReviewRepositoryRequest $request, string $uuid): JsonResponse
    {
        try {
            $reviewData = $request->getReviewData();
            $approver = auth()->user();

            $model = ModelRepository::where('uuid', $uuid)
                ->with('repository')
                ->firstOrFail();

            $model = $this->reviewService->reviewRepository($model->repository, $reviewData, $approver);

            $action = $reviewData['action'] === 'APPROVE' ? 'approved' : 'declined';
            $message = "Model {$action} successfully";

            return response()->sendResponse(
                $model,
                $this->modelTransformer,
                $message,
                ['repository', 'repository.user', 'repository.category']
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
