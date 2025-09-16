<?php

namespace App\Http\Controllers\Api\V1\Datasets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Datasets\GetDatasetsRequest;
use App\Services\Api\V1\DatasetsService;
use App\Transformers\DatasetTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class GetDatasetsController extends Controller
{
    private DatasetsService $datasetsService;
    private DatasetTransformer $datasetTransformer;

    public function __construct(DatasetsService $datasetsService, DatasetTransformer $datasetTransformer)
    {
        $this->datasetsService = $datasetsService;
        $this->datasetTransformer = $datasetTransformer;
    }

    /**
     * Get paginated list of datasets.
     */
    public function __invoke(GetDatasetsRequest $request): JsonResponse
    {
        try {
            $filters = $request->getQueryParams();
            $datasets = $this->datasetsService->getDatasets($filters);

            return response()->sendResponse(
                $datasets,
                $this->datasetTransformer,
                'Datasets retrieved successfully',
                ['repository', 'repository.user', 'repository.category', 'repository.tags', 'repository.approver'],
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
