<?php

namespace App\Http\Controllers\Api\V1\Embeddings;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\DatasetEmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PostDatasetSearchController extends Controller
{
    private DatasetEmbeddingService $embeddingService;

    public function __construct(DatasetEmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Search for similar content in a dataset using RAG.
     */
    public function __invoke(Request $request, string $datasetUuid): JsonResponse
    {
        try {
            $dataset = Dataset::where('uuid', $datasetUuid)->firstOrFail();

            // Validate request
            $validated = $request->validate([
                'query' => 'required|string|min:3|max:1000',
                'limit' => 'sometimes|integer|min:1|max:50',
                'filter' => 'sometimes|array'
            ]);

            $query = $validated['query'];
            $limit = $validated['limit'] ?? 10;
            $filter = $validated['filter'] ?? null;

            // Perform search
            $results = $this->embeddingService->search($dataset, $query, $limit, $filter);

            return response()->sendResponse([
                'query' => $query,
                'dataset_id' => $dataset->uuid,
                'results_count' => count($results),
                'results' => $results
            ], null, 'Search completed successfully.');

        } catch (Exception $e) {
            return response()->sendError(
                'Search failed: ' . $e->getMessage(),
                400
            );
        }
    }
}