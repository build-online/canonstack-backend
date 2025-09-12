<?php

namespace App\Http\Controllers\Api\V1\Datasets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Review\ReviewRepositoryRequest;
use App\Services\Api\V1\ReviewService;
use App\Transformers\DatasetTransformer;
use Illuminate\Http\JsonResponse;
use App\Models\Dataset;
use Exception;

class ReviewDatasetController extends Controller
{
    private ReviewService $reviewService;
    private DatasetTransformer $datasetTransformer;

    public function __construct(ReviewService $reviewService, DatasetTransformer $datasetTransformer)
    {
        $this->reviewService = $reviewService;
        $this->datasetTransformer = $datasetTransformer;
    }

    /**
     * Review a dataset (approve or decline).
     */
    public function __invoke(ReviewRepositoryRequest $request, string $uuid): JsonResponse
    {
        try {
            $reviewData = $request->getReviewData();
            $approver = auth()->user();

            $dataset = Dataset::where('uuid', $uuid)
                ->with('repository')
                ->firstOrFail();

            $dataset = $this->reviewService->reviewRepository($dataset->repository, $reviewData, $approver);

            $action = $reviewData['action'] === 'APPROVE' ? 'approved' : 'declined';
            $message = "Dataset {$action} successfully";

            return response()->sendResponse(
                $dataset,
                $this->datasetTransformer,
                $message,
                ['repository', 'repository.user', 'repository.category', 'repository.religiousMovement']
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
