<?php

namespace App\Services\Api\V1;

use App\Models\Dataset;
use App\Models\DatasetEmbedding;
use App\Models\RepositoryFile;
use App\Utils\FileSystemUtils;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatasetEmbeddingService
{
    private QdrantService $qdrantService;
    private EmbeddingService $embeddingService;

    public function __construct(QdrantService $qdrantService, EmbeddingService $embeddingService)
    {
        $this->qdrantService = $qdrantService;
        $this->embeddingService = $embeddingService;
    }

    /**
     * Generate and store embeddings for a dataset.
     */
    public function generateEmbeddings(Dataset $dataset, array $options = []): DatasetEmbedding
    {
        $chunkSize = $options['chunk_size'] ?? 1000;
        $chunkOverlap = $options['chunk_overlap'] ?? 200;
        $embeddingModel = $options['embedding_model'] ?? $this->embeddingService->getModel();

        Log::info("Starting embedding generation for dataset", [
            'dataset_id' => $dataset->id,
            'dataset_uuid' => $dataset->uuid,
            'chunk_size' => $chunkSize,
            'chunk_overlap' => $chunkOverlap
        ]);

        // Create or get existing embedding record
        $embedding = $dataset->embedding ?? new DatasetEmbedding();
        $embedding->fill([
            'dataset_id' => $dataset->id,
            'qdrant_collection_name' => QdrantService::generateCollectionName($dataset->uuid),
            'embedding_model' => $embeddingModel,
            'chunk_size' => $chunkSize,
            'chunk_overlap' => $chunkOverlap,
        ]);
        $embedding->save();

        try {
            $embedding->markAsProcessing();

            // Extract text from dataset files
            $textData = $this->extractTextFromDataset($dataset);
            
            if (empty($textData)) {
                throw new Exception('No extractable text found in dataset');
            }

            // Prepare chunks
            $chunks = $this->embeddingService->prepareChunks($textData, $chunkSize, $chunkOverlap);
            
            if (empty($chunks)) {
                throw new Exception('No chunks could be prepared from dataset');
            }

            // Create Qdrant collection
            $this->ensureQdrantCollection($embedding->qdrant_collection_name);

            // Generate embeddings and store in Qdrant
            $totalPoints = $this->processChunksToQdrant($chunks, $embedding->qdrant_collection_name);

            // Mark as completed
            $processingStats = [
                'total_files_processed' => count($textData),
                'processing_time_seconds' => $embedding->processing_started_at->diffInSeconds(now()),
                'embedding_model' => $embeddingModel,
                'average_chunk_size' => round(array_sum(array_map('strlen', array_column($chunks, 'text'))) / count($chunks))
            ];

            $embedding->markAsCompleted(count($chunks), $totalPoints, $processingStats);

            Log::info("Embedding generation completed", [
                'dataset_id' => $dataset->id,
                'total_chunks' => count($chunks),
                'total_points' => $totalPoints,
                'stats' => $processingStats
            ]);

            return $embedding;

        } catch (Exception $e) {
            $embedding->markAsFailed($e->getMessage());
            Log::error("Embedding generation failed", [
                'dataset_id' => $dataset->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Search similar content in a dataset.
     */
    public function search(Dataset $dataset, string $query, int $limit = 10, ?array $filter = null): array
    {
        $embedding = $dataset->embedding;
        
        if (!$embedding || !$embedding->isCompleted()) {
            throw new Exception('Dataset embeddings are not available. Please generate embeddings first.');
        }

        // Generate embedding for the query
        $queryVector = $this->embeddingService->generateEmbedding($query);

        // Search in Qdrant
        $results = $this->qdrantService->search(
            $embedding->qdrant_collection_name,
            $queryVector,
            $limit,
            $filter
        );

        return array_map(function ($result) {
            return [
                'id' => $result['id'],
                'score' => $result['score'],
                'text' => $result['payload']['text'] ?? '',
                'metadata' => $result['payload']['metadata'] ?? []
            ];
        }, $results);
    }

    /**
     * Delete embeddings for a dataset.
     */
    public function deleteEmbeddings(Dataset $dataset): bool
    {
        $embedding = $dataset->embedding;
        
        if (!$embedding) {
            return true; // Nothing to delete
        }

        try {
            // Delete from Qdrant
            if ($this->qdrantService->collectionExists($embedding->qdrant_collection_name)) {
                $this->qdrantService->deleteCollection($embedding->qdrant_collection_name);
            }

            // Delete from database
            $embedding->delete();

            Log::info("Dataset embeddings deleted", [
                'dataset_id' => $dataset->id,
                'collection' => $embedding->qdrant_collection_name
            ]);

            return true;

        } catch (Exception $e) {
            Log::error("Failed to delete dataset embeddings", [
                'dataset_id' => $dataset->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Extract text from all files in a dataset.
     */
    private function extractTextFromDataset(Dataset $dataset): array
    {
        $repository = $dataset->repository;
        $files = $repository->files()->where('type', 'file')->get();
        $textData = [];

        foreach ($files as $file) {
            $text = $this->extractTextFromFile($file);
            
            if (!empty($text)) {
                $textData[] = [
                    'text' => $text,
                    'metadata' => [
                        'file_id' => $file->id,
                        'file_name' => $file->name,
                        'file_path' => $file->path,
                        'file_size' => $file->size,
                        'mime_type' => $file->mime_type,
                        'dataset_id' => $dataset->id,
                        'dataset_uuid' => $dataset->uuid,
                        'repository_id' => $repository->id
                    ]
                ];
            }
        }

        return $textData;
    }

    /**
     * Extract text from a single file.
     */
    private function extractTextFromFile(RepositoryFile $file): string
    {
        if (!$file->file_ref) {
            return '';
        }

        $tempPath = null;
        
        try {
            // Download file temporarily
            $tempPath = $this->downloadFileTemporarily($file);
            
            // Extract text based on file extension
            $extension = pathinfo($file->name, PATHINFO_EXTENSION);
            $text = $this->embeddingService->extractTextFromFile($tempPath, $extension);
            
            // Clean up temporary file immediately
            FileSystemUtils::cleanupTemporaryFiles($tempPath);
            
            return $text;

        } catch (Exception $e) {
            Log::warning("Failed to extract text from file", [
                'file_id' => $file->id,
                'file_name' => $file->name,
                'error' => $e->getMessage()
            ]);
            
            // Clean up temp file on error
            if ($tempPath) {
                FileSystemUtils::cleanupTemporaryFiles($tempPath);
            }
            
            return '';
        }
    }

    /**
     * Download a file temporarily for processing.
     */
    private function downloadFileTemporarily(RepositoryFile $file): string
    {
        $tempPath = storage_path('app/temp/' . Str::uuid() . '_' . basename($file->name));
        
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        
        $fileContent = Storage::get($file->file_ref);
        file_put_contents($tempPath, $fileContent);
        
        return $tempPath;
    }

    /**
     * Ensure Qdrant collection exists with proper configuration.
     */
    private function ensureQdrantCollection(string $collectionName): void
    {
        if (!$this->qdrantService->collectionExists($collectionName)) {
            $vectorSize = $this->embeddingService->getVectorSize();
            $this->qdrantService->createCollection($collectionName, $vectorSize);
        }
    }

    /**
     * Process chunks and store in Qdrant with memory-efficient batching.
     */
    private function processChunksToQdrant(array $chunks, string $collectionName): int
    {
        $totalChunks = count($chunks);
        $batchSize = 50; // Start with smaller batches
        $totalPoints = 0;
        
        Log::info("Starting Qdrant processing", [
            'total_chunks' => $totalChunks,
            'initial_batch_size' => $batchSize,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);

        // Process chunks in batches to avoid memory issues
        for ($offset = 0; $offset < $totalChunks; $offset += $batchSize) {
            $chunkBatch = array_slice($chunks, $offset, $batchSize);
            $batchNumber = intval($offset / $batchSize) + 1;
            $totalBatches = ceil($totalChunks / $batchSize);
            
            Log::info("Processing chunk batch for Qdrant", [
                'batch' => $batchNumber,
                'total_batches' => $totalBatches,
                'batch_size' => count($chunkBatch),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]);

            try {
                // Extract texts for this batch
                $texts = array_column($chunkBatch, 'text');
                
                // Generate embeddings for this batch
                $embeddings = $this->embeddingService->generateEmbeddingsWithBatching($texts, min(25, count($texts)));
                
                // Prepare points for Qdrant
                $points = [];
                foreach ($chunkBatch as $index => $chunk) {
                    $points[] = [
                        'id' => $chunk['id'],
                        'vector' => $embeddings[$index],
                        'payload' => [
                            'text' => $chunk['text'],
                            'metadata' => $chunk['metadata']
                        ]
                    ];
                }

                // Insert points in Qdrant
                $this->qdrantService->batchInsertPoints($collectionName, $points, 50);
                $totalPoints += count($points);
                
                // Free memory
                unset($texts, $embeddings, $points, $chunkBatch);
                
                // Force garbage collection every 5 batches
                if ($batchNumber % 5 === 0) {
                    gc_collect_cycles();
                }

                Log::debug("Batch processed successfully", [
                    'batch' => $batchNumber,
                    'points_inserted' => count($points ?? []),
                    'total_points' => $totalPoints,
                    'progress' => round((($offset + $batchSize) / $totalChunks) * 100, 1) . '%'
                ]);

            } catch (Exception $e) {
                Log::error("Failed to process chunk batch for Qdrant", [
                    'batch' => $batchNumber,
                    'error' => $e->getMessage(),
                    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
                ]);
                throw $e;
            }
        }

        Log::info("Qdrant processing completed", [
            'total_points' => $totalPoints,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);

        return $totalPoints;
    }

    /**
     * Get embedding status for a dataset.
     */
    public function getEmbeddingStatus(Dataset $dataset): array
    {
        $embedding = $dataset->embedding;
        
        if (!$embedding) {
            return [
                'status' => 'not_started',
                'message' => 'Embeddings have not been generated for this dataset'
            ];
        }

        $response = [
            'status' => $embedding->status,
            'total_chunks' => $embedding->total_chunks,
            'total_points' => $embedding->total_points,
            'embedding_model' => $embedding->embedding_model,
            'collection_name' => $embedding->qdrant_collection_name,
            'processing_started_at' => $embedding->processing_started_at,
            'processing_completed_at' => $embedding->processing_completed_at,
        ];

        if ($embedding->error_message) {
            $response['error_message'] = $embedding->error_message;
        }

        if ($embedding->processing_stats) {
            $response['processing_stats'] = $embedding->processing_stats;
        }

        return $response;
    }

    /**
     * Re-generate embeddings for a dataset.
     */
    public function regenerateEmbeddings(Dataset $dataset, array $options = []): DatasetEmbedding
    {
        // Delete existing embeddings first
        $this->deleteEmbeddings($dataset);
        
        // Generate new embeddings
        return $this->generateEmbeddings($dataset, $options);
    }

    /**
     * Get collections statistics.
     */
    public function getStatistics(): array
    {
        $embeddings = DatasetEmbedding::with('dataset')->get();
        
        $stats = [
            'total_datasets_with_embeddings' => $embeddings->count(),
            'completed_embeddings' => $embeddings->where('status', 'completed')->count(),
            'processing_embeddings' => $embeddings->where('status', 'processing')->count(),
            'failed_embeddings' => $embeddings->where('status', 'failed')->count(),
            'total_chunks' => $embeddings->sum('total_chunks'),
            'total_points' => $embeddings->sum('total_points'),
            'embedding_models' => $embeddings->pluck('embedding_model')->unique()->values(),
        ];

        return $stats;
    }
}
