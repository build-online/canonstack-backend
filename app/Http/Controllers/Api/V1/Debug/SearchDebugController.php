<?php

namespace App\Http\Controllers\Api\V1\Debug;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\DatasetEmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class SearchDebugController extends Controller
{
    private DatasetEmbeddingService $embeddingService;

    public function __construct(DatasetEmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Debug vector search to troubleshoot similarity issues.
     */
    public function __invoke(Request $request, string $datasetUuid): JsonResponse
    {
        try {
            $dataset = Dataset::where('uuid', $datasetUuid)->firstOrFail();

            // Validate request
            $validated = $request->validate([
                'query' => 'required|string|min:1|max:1000',
                'limit' => 'sometimes|integer|min:1|max:50'
            ]);

            $query = $validated['query'];
            $limit = $validated['limit'] ?? 10;

            // Get embedding status first
            $embedding = $dataset->embedding;
            if (!$embedding || !$embedding->isCompleted()) {
                return response()->sendError(
                    'Dataset embeddings are not available or not completed.',
                    400
                );
            }

            // Perform search with raw results
            $searchResults = $this->embeddingService->search($dataset, $query, $limit);

            return response()->sendResponse([
                'query' => $query,
                'dataset_id' => $dataset->uuid,
                'embedding_status' => $embedding->status,
                'collection_name' => $embedding->qdrant_collection_name,
                'total_points_in_collection' => $embedding->total_points,
                'search_results_count' => count($searchResults),
                'search_results' => $searchResults,
                'analysis' => [
                    'scores_range' => count($searchResults) > 0 ? [
                        'min' => min(array_map(fn($r) => $r['score'] ?? 0, $searchResults)),
                        'max' => max(array_map(fn($r) => $r['score'] ?? 0, $searchResults)),
                        'avg' => array_sum(array_map(fn($r) => $r['score'] ?? 0, $searchResults)) / count($searchResults)
                    ] : null,
                    'recommendations' => [
                        'for_threshold_0.1' => count(array_filter($searchResults, fn($r) => $r['score'] >= 0.1)),
                        'for_threshold_0.3' => count(array_filter($searchResults, fn($r) => $r['score'] >= 0.3)),
                        'for_threshold_0.5' => count(array_filter($searchResults, fn($r) => $r['score'] >= 0.5)),
                        'for_threshold_0.7' => count(array_filter($searchResults, fn($r) => $r['score'] >= 0.7)),
                    ]
                ]
            ], null, 'Search debug completed successfully.');

        } catch (Exception $e) {
            return response()->sendError(
                'Search debug failed: ' . $e->getMessage(),
                500
            );
        }
    }
}