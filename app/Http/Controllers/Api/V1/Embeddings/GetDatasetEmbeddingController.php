<?php

namespace App\Http\Controllers\Api\V1\Embeddings;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\DatasetEmbeddingService;
use Illuminate\Http\JsonResponse;
use Exception;

class GetDatasetEmbeddingController extends Controller
{
    private DatasetEmbeddingService $embeddingService;

    public function __construct(DatasetEmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Get embedding status for a dataset.
     */
    public function __invoke(string $datasetUuid): JsonResponse
    {
        try {
            $dataset = Dataset::where('uuid', $datasetUuid)->firstOrFail();
            
            $status = $this->embeddingService->getEmbeddingStatus($dataset);

            return response()->sendResponse(
                $status,
                null,
                'Dataset embedding status retrieved successfully.'
            );

        } catch (Exception $e) {
            return response()->sendError(
                'Failed to get embedding status: ' . $e->getMessage(),
                404
            );
        }
    }
}