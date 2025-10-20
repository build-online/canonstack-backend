<?php

namespace App\Http\Controllers\Api\V1\Experiments;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use Illuminate\Http\JsonResponse;
use Exception;

class GetEmbeddingVariantsController extends Controller
{
    /**
     * Get status of embedding variants (simple and complex) for a dataset.
     */
    public function __invoke(string $datasetUuid): JsonResponse
    {
        try {
            $dataset = Dataset::where('uuid', $datasetUuid)->firstOrFail();

            // Get simple embedding
            $simpleEmbedding = $dataset->getEmbeddingByVariant('simple');
            
            // Get complex embedding
            $complexEmbedding = $dataset->getEmbeddingByVariant('complex');

            // Build response
            $response = [
                'dataset_uuid' => $dataset->uuid,
                'variants' => [
                    'simple' => $this->buildVariantResponse($dataset, $simpleEmbedding, 'simple'),
                    'complex' => $this->buildVariantResponse($dataset, $complexEmbedding, 'complex'),
                ],
            ];

            return response()->sendResponse($response, null, 'Embedding variants retrieved successfully.');

        } catch (Exception $e) {
            return response()->sendError(
                'Failed to retrieve embedding variants: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Build response for a specific variant.
     */
    private function buildVariantResponse(Dataset $dataset, $embedding, string $variant): array
    {
        if (!$embedding) {
            return [
                'exists' => false,
                'status' => 'none',
                'collection' => 'dataset-' . $dataset->uuid . '-' . $variant,
            ];
        }

        $status = match($embedding->status) {
            'COMPLETED' => 'completed',
            'PROCESSING' => 'processing',
            'PENDING' => 'queued',
            'FAILED' => 'failed',
            default => 'none',
        };

        return [
            'exists' => true,
            'status' => $status,
            'collection' => $embedding->qdrant_collection_name,
            'embedding_uuid' => $embedding->uuid,
            'total_chunks' => $embedding->total_chunks,
            'total_points' => $embedding->total_points,
            'processing_started_at' => $embedding->processing_started_at?->toISOString(),
            'processing_completed_at' => $embedding->processing_completed_at?->toISOString(),
            'error_message' => $embedding->error_message,
        ];
    }
}

