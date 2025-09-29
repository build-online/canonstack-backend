<?php

namespace App\Http\Controllers\Api\V1\Embeddings;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\DatasetEmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PostDatasetEmbeddingController extends Controller
{
    private DatasetEmbeddingService $embeddingService;

    public function __construct(DatasetEmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Generate embeddings for a dataset.
     */
    public function __invoke(Request $request, string $datasetUuid): JsonResponse
    {
        try {
            $dataset = Dataset::where('uuid', $datasetUuid)
                ->with('repository')
                ->firstOrFail();

            // Validate request parameters
            $validated = $request->validate([
                'chunk_size' => 'sometimes|integer|min:100|max:8000',
                'chunk_overlap' => 'sometimes|integer|min:0|max:1000',
                'embedding_model' => 'sometimes|string|in:text-embedding-3-small,text-embedding-3-large,text-embedding-ada-002',
                'force_regenerate' => 'sometimes|boolean'
            ]);

            // Check if embeddings already exist
            $existingEmbedding = $dataset->embedding;
            
            if ($existingEmbedding && $existingEmbedding->isCompleted() && !($validated['force_regenerate'] ?? false)) {
                return response()->sendResponse(
                    $this->embeddingService->getEmbeddingStatus($dataset),
                    null,
                    'Embeddings already exist for this dataset. Use force_regenerate=true to recreate them.'
                );
            }

            if ($existingEmbedding && $existingEmbedding->isProcessing()) {
                return response()->sendResponse(
                    $this->embeddingService->getEmbeddingStatus($dataset),
                    null,
                    'Embeddings are currently being processed for this dataset.'
                );
            }

            // Generate embeddings
            if ($validated['force_regenerate'] ?? false) {
                $embedding = $this->embeddingService->regenerateEmbeddings($dataset, $validated);
            } else {
                $embedding = $this->embeddingService->generateEmbeddings($dataset, $validated);
            }

            return response()->sendResponse([
                'embedding_id' => $embedding->uuid,
                'dataset_id' => $dataset->uuid,
                'status' => $embedding->status,
                'collection_name' => $embedding->qdrant_collection_name,
                'total_chunks' => $embedding->total_chunks,
                'total_points' => $embedding->total_points,
                'embedding_model' => $embedding->embedding_model,
                'processing_started_at' => $embedding->processing_started_at,
                'processing_completed_at' => $embedding->processing_completed_at,
                'processing_stats' => $embedding->processing_stats
            ], null, 'Dataset embeddings generated successfully.');

        } catch (Exception $e) {
            return response()->sendError(
                'Failed to generate embeddings: ' . $e->getMessage(),
                500
            );
        }
    }
}