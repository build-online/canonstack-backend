<?php

namespace App\Http\Controllers\Api\V1\Datasets;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Transformers\DatasetTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class GetDatasetController extends Controller
{
    private DatasetTransformer $datasetTransformer;

    public function __construct(DatasetTransformer $datasetTransformer)
    {
        $this->datasetTransformer = $datasetTransformer;
    }

    /**
     * Get a specific dataset with all its information.
     */
    public function __invoke(string $uuid): JsonResponse
    {
        $dataset = Dataset::where('uuid', $uuid)
            ->with([
                'repository' => function ($query) {
                    $query->withCount(['downloads', 'likes', 'comments']);
                },
                'repository.user',
                'repository.category',
                'repository.tags',
                'repository.approver',
                'repository.comments',
                'repository.comments.user',
                'repository.likes.user:id,uuid',
                'repository.files:id,repository_id,type,size'
            ])
            ->firstOrFail();

        try {
            return response()->sendResponse(
                $dataset,
                $this->datasetTransformer,
                'Dataset retrieved successfully',
                ['repository', 'repository.user', 'repository.category', 'repository.tags', 'repository.approver', 'repository.comments', 'repository.comments.user'],
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                404
            );
        }
    }
}
