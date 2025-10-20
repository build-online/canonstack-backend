<?php

namespace App\Http\Controllers\Api\V1\Experiments;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateVariantEmbeddingJob;
use App\Models\Dataset;
use App\Models\DatasetEmbedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PostComplexEmbeddingController extends Controller
{
    /**
     * Generate complex embeddings for a dataset.
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
                'jsonl_config' => 'sometimes|array',
                'jsonl_config.text_field' => 'required_with:jsonl_config|string',
                'jsonl_config.vector_field' => 'sometimes|string', // For pre-embedded JSONL
                'jsonl_config.metadata_fields' => 'sometimes|array',
                'jsonl_config.metadata_fields.*' => 'string',
            ]);

            // Check if embeddings already exist for this variant
            $existingEmbedding = $dataset->getEmbeddingByVariant('complex');
            
            if ($existingEmbedding && $existingEmbedding->isCompleted() && !($validated['force'] ?? false)) {
                return response()->sendResponse([
                    'embedding_uuid' => $existingEmbedding->uuid,
                    'dataset_uuid' => $dataset->uuid,
                    'variant' => 'complex',
                    'status' => $existingEmbedding->status,
                    'collection_name' => $existingEmbedding->qdrant_collection_name,
                ], null, 'Complex embeddings already exist for this dataset. Use force=true to recreate them.');
            }

            if ($existingEmbedding && ($existingEmbedding->isProcessing() || $existingEmbedding->isPending())) {
                return response()->json([
                    'data' => [
                        'embedding_uuid' => $existingEmbedding->uuid,
                        'dataset_uuid' => $dataset->uuid,
                        'variant' => 'complex',
                        'status' => $existingEmbedding->status,
                        'collection_name' => $existingEmbedding->qdrant_collection_name,
                    ],
                    'message' => 'Complex embeddings are currently being processed for this dataset.',
                ], 202);
            }

            // Delete existing embeddings if force regenerate
            if (($validated['force'] ?? false) && $existingEmbedding) {
                $existingEmbedding->delete();
            }

            // Create new embedding record with PENDING status
            $processingStats = [
                'chunk_size' => $validated['chunk_size'] ?? 1000,
                'chunk_overlap' => $validated['chunk_overlap'] ?? 200,
            ];
            
            // Add JSONL configuration if provided
            if (isset($validated['jsonl_config'])) {
                $processingStats['jsonl_config'] = $validated['jsonl_config'];
            }
            
            $embedding = DatasetEmbedding::create([
                'dataset_id' => $dataset->id,
                'variant' => 'complex',
                'qdrant_collection_name' => 'dataset-' . $dataset->uuid . '-complex',
                'embedding_model' => config('services.openai.embedding_model', 'text-embedding-3-small'),
                'status' => 'PENDING',
                'chunk_size' => $validated['chunk_size'] ?? 1000,
                'chunk_overlap' => $validated['chunk_overlap'] ?? 200,
                'processing_stats' => $processingStats
            ]);

            // Queue the background job
            GenerateVariantEmbeddingJob::dispatch($dataset, 'complex', $validated);

            return response()->json([
                'data' => [
                    'embedding_uuid' => $embedding->uuid,
                    'dataset_uuid' => $dataset->uuid,
                    'variant' => 'complex',
                    'status' => 'queued',
                    'collection_name' => $embedding->qdrant_collection_name,
                ],
                'message' => 'Complex embedding generation job has been queued.',
            ], 202);

        } catch (Exception $e) {
            return response()->sendError(
                'Failed to generate complex embeddings: ' . $e->getMessage(),
                500
            );
        }
    }
}

