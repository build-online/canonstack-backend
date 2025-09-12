<?php

namespace App\Http\Controllers\Api\V1\Datasets;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\DatasetsService;
use Illuminate\Http\JsonResponse;

class DeleteDatasetController extends Controller
{
    private DatasetsService $datasetsService;

    public function __construct(DatasetsService $datasetsService)
    {
        $this->datasetsService = $datasetsService;
    }

    /**
     * Delete a dataset.
     */
    public function __invoke(string $uuid): JsonResponse
    {
        $dataset = Dataset::where('uuid', $uuid)->with('repository')->firstOrFail();

        if ($dataset->repository->user_id !== auth()->id()) {
            return response()->sendError(
                'You are not authorized to delete this dataset.',
                403
            );
        }

        try {
            $this->datasetsService->deleteDataset($dataset);

            return response()->sendResponse(
                null,
                null,
                'Dataset deleted successfully',
                [],
                [],
                204
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
