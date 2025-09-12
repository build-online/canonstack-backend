<?php

namespace App\Http\Controllers\Api\V1\Datasets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Datasets\PatchDatasetRequest;
use App\Models\Dataset;
use App\Services\Api\V1\DatasetsService;
use App\Transformers\DatasetTransformer;
use Illuminate\Http\JsonResponse;

class PatchDatasetController extends Controller
{
    private DatasetsService $datasetsService;
    private DatasetTransformer $datasetTransformer;

    public function __construct(DatasetsService $datasetsService, DatasetTransformer $datasetTransformer)
    {
        $this->datasetsService = $datasetsService;
        $this->datasetTransformer = $datasetTransformer;
    }

    /**
     * Update a dataset.
     */
    public function __invoke(PatchDatasetRequest $request, string $uuid): JsonResponse
    {
        $dataset = Dataset::where('uuid', $uuid)->with('repository')->firstOrFail();

        if ($dataset->repository->user_id !== auth()->id()) {
            return response()->sendError(
                'You are not authorized to update this dataset.',
                403
            );
        }

        try {
            $data = $request->validated();
            $zipFile = $request->file('zip_file');

            $updatedDataset = $this->datasetsService->updateDataset($dataset, $data, $zipFile);

            return response()->sendResponse(
                $updatedDataset,
                $this->datasetTransformer,
                'Dataset updated successfully',
                ['repository', 'repository.user', 'repository.category', 'repository.religiousMovement', 'repository.tags']
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
