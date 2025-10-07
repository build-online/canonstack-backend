<?php

namespace App\Http\Controllers\Api\V1\Embeddings;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDatasetEmbeddings;
use App\Models\Dataset;
use App\Models\DatasetEmbedding;
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

            // Check if the authenticated user is the creator of the dataset
            if ($dataset->repository->user_id !== auth()->id()) {
                return response()->sendError(
                    'You are not authorized to create embeddings for this dataset. Only the dataset creator can generate vectorized embeddings.',
                    403
                );
            }

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

            if ($existingEmbedding && ($existingEmbedding->isProcessing() || $existingEmbedding->isPending())) {
                return response()->sendResponse(
                    $this->embeddingService->getEmbeddingStatus($dataset),
                    null,
                    'Embeddings are currently being processed for this dataset.'
                );
            }

            // Create embedding record and queue background job
            if ($validated['force_regenerate'] ?? false) {
                // Delete existing embeddings first
                if ($existingEmbedding) {
                    $this->embeddingService->deleteEmbeddings($dataset);
                }
            }

            // Create new embedding record with PENDING status
            $embedding = DatasetEmbedding::create([
                'dataset_id' => $dataset->id,
                'qdrant_collection_name' => 'dataset_' . $dataset->uuid,
                'embedding_model' => $validated['embedding_model'] ?? config('services.openai.embedding_model'),
                'status' => 'PENDING',
                'chunk_size' => $validated['chunk_size'] ?? 1000,
                'chunk_overlap' => $validated['chunk_overlap'] ?? 200,
                'processing_stats' => [
                    'chunk_size' => $validated['chunk_size'] ?? 1000,
                    'chunk_overlap' => $validated['chunk_overlap'] ?? 200,
                ]
            ]);

            // Queue the background job
            GenerateDatasetEmbeddings::dispatch($dataset, $validated);

            return response()->sendResponse([
                'embedding_uuid' => $embedding->uuid,
                'dataset_uuid' => $dataset->uuid,
                'status' => $embedding->status,
                'collection_name' => $embedding->qdrant_collection_name,
                'embedding_model' => $embedding->embedding_model,
                'processing_stats' => $embedding->processing_stats,
                'message' => 'Embedding generation job has been queued. Check status using the GET endpoint.'
            ], null, 'Dataset embedding generation queued successfully.');

        } catch (Exception $e) {
            return response()->sendError(
                'Failed to generate embeddings: ' . $e->getMessage(),
                500
            );
        }
    }
}