<?php

namespace App\Http\Controllers\Api\V1\Embeddings;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\DatasetEmbeddingService;
use Illuminate\Http\JsonResponse;
use Exception;

class DeleteDatasetEmbeddingController extends Controller
{
    private DatasetEmbeddingService $embeddingService;

    public function __construct(DatasetEmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Delete embeddings for a dataset.
     */
    public function __invoke(string $datasetUuid): JsonResponse
    {
        try {
            $dataset = Dataset::where('uuid', $datasetUuid)->firstOrFail();

            $this->embeddingService->deleteEmbeddings($dataset);

            return response()->sendResponse(
                ['dataset_id' => $dataset->uuid],
                null,
                'Dataset embeddings deleted successfully.'
            );

        } catch (Exception $e) {
            return response()->sendError(
                'Failed to delete embeddings: ' . $e->getMessage(),
                500
            );
        }
    }
}