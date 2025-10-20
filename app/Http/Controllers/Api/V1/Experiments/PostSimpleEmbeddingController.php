<?php

namespace App\Http\Controllers\Api\V1\Experiments;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateVariantEmbeddingJob;
use App\Models\Dataset;
use App\Models\DatasetEmbedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PostSimpleEmbeddingController extends Controller
{
    /**
     * Generate simple embeddings for a dataset.
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
                    'Only the dataset author can generate embeddings.',
                    403
                );
            }

            // Validate request parameters
            $validated = $request->validate([
                'force' => 'sometimes|boolean',
                'chunk_size' => 'sometimes|integer|min:100|max:8000',
                'chunk_overlap' => 'sometimes|integer|min:0|max:1000',
            ]);

            // Check if embeddings already exist for this variant
            $existingEmbedding = $dataset->getEmbeddingByVariant('simple');
            
            if ($existingEmbedding && $existingEmbedding->isCompleted() && !($validated['force'] ?? false)) {
                return response()->sendResponse([
                    'embedding_uuid' => $existingEmbedding->uuid,
                    'dataset_uuid' => $dataset->uuid,
                    'variant' => 'simple',
                    'status' => $existingEmbedding->status,
                    'collection_name' => $existingEmbedding->qdrant_collection_name,
                ], null, 'Simple embeddings already exist for this dataset. Use force=true to recreate them.');
            }

            if ($existingEmbedding && ($existingEmbedding->isProcessing() || $existingEmbedding->isPending())) {
                return response()->json([
                    'data' => [
                        'embedding_uuid' => $existingEmbedding->uuid,
                        'dataset_uuid' => $dataset->uuid,
                        'variant' => 'simple',
                        'status' => $existingEmbedding->status,
                        'collection_name' => $existingEmbedding->qdrant_collection_name,
                    ],
                    'message' => 'Simple embeddings are currently being processed for this dataset.',
                ], 202);
            }

            // Delete existing embeddings if force regenerate
            if (($validated['force'] ?? false) && $existingEmbedding) {
                $existingEmbedding->delete();
            }

            // Create new embedding record with PENDING status
            $embedding = DatasetEmbedding::create([
                'dataset_id' => $dataset->id,
                'variant' => 'simple',
                'qdrant_collection_name' => 'dataset-' . $dataset->uuid . '-simple',
                'embedding_model' => config('services.openai.embedding_model', 'text-embedding-3-small'),
                'status' => 'PENDING',
                'chunk_size' => $validated['chunk_size'] ?? 1000,
                'chunk_overlap' => $validated['chunk_overlap'] ?? 200,
                'processing_stats' => [
                    'chunk_size' => $validated['chunk_size'] ?? 1000,
                    'chunk_overlap' => $validated['chunk_overlap'] ?? 200,
                ]
            ]);

            // Queue the background job
            GenerateVariantEmbeddingJob::dispatch($dataset, 'simple', $validated);

            return response()->json([
                'data' => [
                    'embedding_uuid' => $embedding->uuid,
                    'dataset_uuid' => $dataset->uuid,
                    'variant' => 'simple',
                    'status' => 'queued',
                    'collection_name' => $embedding->qdrant_collection_name,
                ],
                'message' => 'Simple embedding generation job has been queued.',
            ], 202);

        } catch (Exception $e) {
            return response()->sendError(
                'Failed to generate simple embeddings: ' . $e->getMessage(),
                500
            );
        }
    }
}

