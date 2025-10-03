<?php

namespace App\Http\Controllers\Api\V1\Datasets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Datasets\PostDatasetRequest;
use App\Services\Api\V1\DatasetsService;
use App\Transformers\DatasetTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class PostDatasetController extends Controller
{
    private DatasetsService $datasetsService;
    private DatasetTransformer $datasetTransformer;

    public function __construct(DatasetsService $datasetsService, DatasetTransformer $datasetTransformer)
    {
        $this->datasetsService = $datasetsService;
        $this->datasetTransformer = $datasetTransformer;
    }

    /**
     * Upload and create a new dataset.
     */
    public function __invoke(PostDatasetRequest $request): JsonResponse
    {
        // Increase execution time limit for dataset upload and processing
        set_time_limit(120);
        
        try {
            $dataset = $this->datasetsService->uploadDataset(
                $request->validated(), 
                $request->file('zip_file')
            );

            return response()->sendResponse(
                $dataset,
                $this->datasetTransformer,
                'Dataset uploaded successfully.',
            );

        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
