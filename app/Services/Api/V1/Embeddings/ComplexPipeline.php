<?php

namespace App\Services\Api\V1\Embeddings;

use App\Events\EmbeddingVariantStarted;
use App\Events\EmbeddingVariantProgress;
use App\Events\EmbeddingVariantCompleted;
use App\Events\EmbeddingVariantFailed;
use App\Models\Dataset;
use App\Models\DatasetEmbedding;
use App\Services\Api\V1\DatasetEmbeddingService;
use App\Services\Api\V1\AI\DatasetStructureAnalyzerService;
use App\Services\Api\V1\AI\DatasetPromptGeneratorService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Complex Embedding Pipeline
 * 
 * Strategy: Same chunking as production (1000 chars, 200 overlap)
 * Creates identical embeddings to default, but in separate Qdrant collection
 * Allows isolated experimentation without affecting production
 * 
 * Future enhancements planned:
 * - Semantic chunking (split at natural boundaries)
 * - Reranking with cross-encoders
 * - Multi-vector representations
 * - Function calling integration
 */
class ComplexPipeline implements EmbeddingPipelineInterface
{
    private DatasetEmbeddingService $embeddingService;
    private DatasetStructureAnalyzerService $structureAnalyzer;
    private DatasetPromptGeneratorService $promptGenerator;

    public function __construct(
        DatasetEmbeddingService $embeddingService,
        DatasetStructureAnalyzerService $structureAnalyzer,
        DatasetPromptGeneratorService $promptGenerator
    ) {
        $this->embeddingService = $embeddingService;
        $this->structureAnalyzer = $structureAnalyzer;
        $this->promptGenerator = $promptGenerator;
    }

    /**
     * Process the dataset with complex embedding strategy.
     * 
     * For now, this uses the same logic as SimplePipeline.
     * Future enhancements: rerankers, multi-vector, function calling, etc.
     */
    public function process(Dataset $dataset, DatasetEmbedding $embedding, array $options): void
    {
        try {
            Log::info("Starting complex embedding pipeline", [
                'dataset_id' => $dataset->id,
                'embedding_id' => $embedding->id,
                'variant' => 'complex'
            ]);

            $embedding->markAsProcessing();
            
            // Broadcast that embedding generation has started
            broadcast(new EmbeddingVariantStarted($embedding, 'complex'));

            // Generate embeddings with complex strategy (with metadata collection)
            $result = $this->generateComplexEmbeddings($dataset, $embedding, $options);

            // Create payload indexes for metadata filtering (if metadata was collected)
            if (!empty($result['collected_metadata'])) {
                broadcast(new EmbeddingVariantProgress($embedding, 'complex', 90, 'Creating metadata indexes for filtering'));
                
                try {
                    $this->embeddingService->ensureMetadataIndexes(
                        $embedding->qdrant_collection_name,
                        $result['collected_metadata']
                    );
                    
                    Log::info("Metadata payload indexes created", [
                        'dataset_id' => $dataset->id,
                        'embedding_id' => $embedding->id,
                        'collection' => $embedding->qdrant_collection_name
                    ]);
                } catch (Exception $e) {
                    Log::warning("Failed to create metadata indexes, filtering may not work", [
                        'dataset_id' => $dataset->id,
                        'embedding_id' => $embedding->id,
                        'error' => $e->getMessage()
                    ]);
                    // Don't fail if index creation fails - filtering just won't work
                }
            }
            
            // Analyze dataset structure and generate system prompt
            broadcast(new EmbeddingVariantProgress($embedding, 'complex', 92, 'Analyzing dataset structure'));
            
            try {
                if (!empty($result['collected_metadata'])) {
                    $structureDescription = $this->structureAnalyzer->analyzeFromCollectedMetadata(
                        $dataset, 
                        $result['collected_metadata']
                    );
                } else {
                    $structureDescription = $this->structureAnalyzer->analyzeStructure($dataset, $embedding, 100);
                }
                
                broadcast(new EmbeddingVariantProgress($embedding, 'complex', 95, 'Generating dataset-specific system prompt'));
                
                $systemPrompt = $this->promptGenerator->generateSystemPrompt($dataset, $embedding, $structureDescription);
                
                // Save structure description and system prompt to embedding
                $embedding->update([
                    'structure_description' => $structureDescription,
                    'system_prompt' => $systemPrompt,
                ]);
                
                Log::info("Dataset metadata generated successfully", [
                    'dataset_id' => $dataset->id,
                    'embedding_id' => $embedding->id,
                    'structure_description_length' => strlen($structureDescription),
                    'system_prompt_length' => strlen($systemPrompt)
                ]);
                
            } catch (Exception $e) {
                Log::warning("Failed to generate dataset metadata, continuing with embedding completion", [
                    'dataset_id' => $dataset->id,
                    'embedding_id' => $embedding->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the entire process if metadata generation fails
                // The embedding is still valid without it
            }

            // Mark as completed
            broadcast(new EmbeddingVariantProgress($embedding, 'complex', 98, 'Finalizing'));
            
            $embedding->markAsCompleted(
                $result['total_chunks'],
                $result['total_points'],
                $result['processing_stats']
            );

            // Broadcast completion
            broadcast(new EmbeddingVariantCompleted($embedding, 'complex'));

            Log::info("Complex embedding pipeline completed", [
                'dataset_id' => $dataset->id,
                'embedding_id' => $embedding->id,
                'total_points' => $result['total_points']
            ]);

        } catch (Exception $e) {
            Log::error("Complex embedding pipeline failed", [
                'dataset_id' => $dataset->id,
                'embedding_id' => $embedding->id,
                'error' => $e->getMessage()
            ]);

            $embedding->markAsFailed($e->getMessage());
            broadcast(new EmbeddingVariantFailed($embedding, 'complex', $e->getMessage()));
            
            throw $e;
        }
    }

    /**
     * Generate complex embeddings.
     * Uses same chunking parameters as production for identical embeddings.
     * 
     * TODO: Implement advanced features:
     * - Semantic chunking
     * - Reranking
     * - Multi-vector representations
     * - Function calling integration
     */
    private function generateComplexEmbeddings(Dataset $dataset, DatasetEmbedding $embedding, array $options): array
    {
        // Set options for complex processing: SAME AS PRODUCTION
        // This creates identical embeddings to default, just in a separate collection
        $complexOptions = array_merge($options, [
            'chunk_size' => $options['chunk_size'] ?? 1000,     // Same as production
            'chunk_overlap' => $options['chunk_overlap'] ?? 200, // Same as production
            'embedding_model' => $embedding->embedding_model,
            'collection_name' => $embedding->qdrant_collection_name, // Use the variant-specific collection name
        ]);

        // Broadcast progress
        broadcast(new EmbeddingVariantProgress($embedding, 'complex', 10, 'Starting complex embedding generation'));

        // Generate embeddings using the variant-specific collection
        // Enable metadata collection for complex pipeline
        $complexOptions['existing_embedding'] = $embedding;
        $complexOptions['collect_metadata'] = true; 
        $result = $this->embeddingService->generateEmbeddingsForVariant($dataset, $embedding, $complexOptions);
        
        broadcast(new EmbeddingVariantProgress($embedding, 'complex', 90, 'Finalizing complex embeddings'));

        return [
            'total_chunks' => $result['total_chunks'],
            'total_points' => $result['total_points'],
            'collected_metadata' => $result['collected_metadata'] ?? null,  // Pass through collected metadata
            'processing_stats' => array_merge($result['processing_stats'] ?? [], [
                'variant' => 'complex',
                'pipeline' => 'ComplexPipeline',
                'strategy' => 'same_as_production',
                'chunk_size' => $complexOptions['chunk_size'],
                'chunk_overlap' => $complexOptions['chunk_overlap'],
                'description' => 'Identical embeddings to production, isolated in separate collection for experimentation',
                'enhancements' => 'metadata collection for comprehensive structure analysis',
            ]),
        ];
    }
}

