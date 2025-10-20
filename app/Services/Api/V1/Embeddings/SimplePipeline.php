<?php

namespace App\Services\Api\V1\Embeddings;

use App\Events\EmbeddingVariantStarted;
use App\Events\EmbeddingVariantProgress;
use App\Events\EmbeddingVariantCompleted;
use App\Events\EmbeddingVariantFailed;
use App\Models\Dataset;
use App\Models\DatasetEmbedding;
use App\Services\Api\V1\DatasetEmbeddingService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Simple Embedding Pipeline
 * 
 * Strategy: Same chunking as production (1000 chars, 200 overlap)
 * Creates identical embeddings to default, but in separate Qdrant collection
 * Allows isolated experimentation without affecting production
 */
class SimplePipeline implements EmbeddingPipelineInterface
{
    private DatasetEmbeddingService $embeddingService;

    public function __construct(DatasetEmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Process the dataset with simple embedding strategy.
     */
    public function process(Dataset $dataset, DatasetEmbedding $embedding, array $options): void
    {
        try {
            Log::info("Starting simple embedding pipeline", [
                'dataset_id' => $dataset->id,
                'embedding_id' => $embedding->id,
                'variant' => 'simple'
            ]);

            $embedding->markAsProcessing();
            
            // Broadcast that embedding generation has started
            broadcast(new EmbeddingVariantStarted($embedding, 'simple'));

            // Use the existing embedding service's generateEmbeddings logic
            // but we'll handle the status updates ourselves
            $result = $this->generateSimpleEmbeddings($dataset, $embedding, $options);

            // Mark as completed
            $embedding->markAsCompleted(
                $result['total_chunks'],
                $result['total_points'],
                $result['processing_stats']
            );

            // Broadcast completion
            broadcast(new EmbeddingVariantCompleted($embedding, 'simple'));

            Log::info("Simple embedding pipeline completed", [
                'dataset_id' => $dataset->id,
                'embedding_id' => $embedding->id,
                'total_points' => $result['total_points']
            ]);

        } catch (Exception $e) {
            Log::error("Simple embedding pipeline failed", [
                'dataset_id' => $dataset->id,
                'embedding_id' => $embedding->id,
                'error' => $e->getMessage()
            ]);

            $embedding->markAsFailed($e->getMessage());
            broadcast(new EmbeddingVariantFailed($embedding, 'simple', $e->getMessage()));
            
            throw $e;
        }
    }

    /**
     * Generate simple embeddings.
     * Uses same chunking parameters as production for identical embeddings.
     */
    private function generateSimpleEmbeddings(Dataset $dataset, DatasetEmbedding $embedding, array $options): array
    {
        // Set options for simple processing: SAME AS PRODUCTION
        // This creates identical embeddings to default, just in a separate collection
        $simpleOptions = array_merge($options, [
            'chunk_size' => $options['chunk_size'] ?? 1000,     // Same as production
            'chunk_overlap' => $options['chunk_overlap'] ?? 200, // Same as production
            'embedding_model' => $embedding->embedding_model,
            'collection_name' => $embedding->qdrant_collection_name, // Use the variant-specific collection name
        ]);

        // Broadcast progress
        broadcast(new EmbeddingVariantProgress($embedding, 'simple', 10, 'Starting simple embedding generation'));

        // Generate embeddings using the variant-specific collection
        // Pass the embedding object so it doesn't create a new one
        $simpleOptions['existing_embedding'] = $embedding;
        $result = $this->embeddingService->generateEmbeddingsForVariant($dataset, $embedding, $simpleOptions);
        
        broadcast(new EmbeddingVariantProgress($embedding, 'simple', 90, 'Finalizing simple embeddings'));

        return [
            'total_chunks' => $result['total_chunks'],
            'total_points' => $result['total_points'],
            'processing_stats' => array_merge($result['processing_stats'] ?? [], [
                'variant' => 'simple',
                'pipeline' => 'SimplePipeline',
                'strategy' => 'same_as_production',
                'chunk_size' => $simpleOptions['chunk_size'],
                'chunk_overlap' => $simpleOptions['chunk_overlap'],
                'description' => 'Identical embeddings to production, isolated in separate collection for experimentation',
            ]),
        ];
    }
}

