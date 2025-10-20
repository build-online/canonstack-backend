<?php

namespace App\Services\Api\V1;

use App\Events\DatasetEmbeddingStarted;
use App\Events\DatasetEmbeddingProgress;
use App\Events\DatasetEmbeddingCompleted;
use App\Events\DatasetEmbeddingFailed;
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
            
            // Broadcast that embedding generation has started
            broadcast(new DatasetEmbeddingStarted($embedding));

            // Check if dataset contains pre-prepared JSONL files
            $jsonlFiles = $this->detectJsonlFiles($dataset);
            
            if (!empty($jsonlFiles)) {
                Log::info("Detected pre-prepared JSONL files, skipping embedding generation", [
                    'dataset_id' => $dataset->id,
                    'jsonl_files' => array_map(fn($f) => $f->name, $jsonlFiles)
                ]);
                
                // Create Qdrant collection
                broadcast(new DatasetEmbeddingProgress($embedding, 1, 3, 'Creating Qdrant collection'));
                $this->ensureQdrantCollection($embedding->qdrant_collection_name);
                
                // Process JSONL files directly
                broadcast(new DatasetEmbeddingProgress($embedding, 2, 3, 'Processing JSONL files'));
                $totalPoints = $this->processJsonlFilesToQdrant($jsonlFiles, $embedding->qdrant_collection_name);
                
                // Mark as completed
                $processingStats = [
                    'total_files_processed' => count($jsonlFiles),
                    'processing_time_seconds' => $embedding->processing_started_at->diffInSeconds(now()),
                    'embedding_model' => $embeddingModel,
                    'processing_type' => 'pre_prepared_jsonl',
                    'jsonl_files' => array_map(fn($f) => $f->name, $jsonlFiles)
                ];

                $embedding->markAsCompleted($totalPoints, $totalPoints, $processingStats);
                
            } else {
                // Standard processing for non-JSONL datasets
                broadcast(new DatasetEmbeddingProgress($embedding, 1, 5, 'Extracting text from dataset files', [
                    'total_progress_percentage' => 10
                ]));
                
                // Extract text from dataset files
                $textData = $this->extractTextFromDataset($dataset);
                
                if (empty($textData)) {
                    throw new Exception('No extractable text found in dataset');
                }

                broadcast(new DatasetEmbeddingProgress($embedding, 2, 5, 'Preparing text chunks', [
                    'total_progress_percentage' => 25
                ]));
                
                // Prepare chunks
                $chunks = $this->embeddingService->prepareChunks($textData, $chunkSize, $chunkOverlap);
                
                if (empty($chunks)) {
                    throw new Exception('No chunks could be prepared from dataset');
                }

                broadcast(new DatasetEmbeddingProgress($embedding, 3, 5, 'Creating Qdrant collection', [
                    'total_progress_percentage' => 40
                ]));
                
                // Create Qdrant collection
                $this->ensureQdrantCollection($embedding->qdrant_collection_name);

                broadcast(new DatasetEmbeddingProgress($embedding, 4, 5, 'Generating embeddings and storing in Qdrant', [
                    'total_progress_percentage' => 60
                ]));
                
                // Generate embeddings and store in Qdrant with detailed progress
                $totalPoints = $this->processChunksToQdrant($chunks, $embedding->qdrant_collection_name, $embedding);

                // Mark as completed
                $processingStats = [
                    'total_files_processed' => count($textData),
                    'processing_time_seconds' => $embedding->processing_started_at->diffInSeconds(now()),
                    'embedding_model' => $embeddingModel,
                    'processing_type' => 'standard_embedding_generation',
                    'average_chunk_size' => round(array_sum(array_map('strlen', array_column($chunks, 'text'))) / count($chunks))
                ];

                broadcast(new DatasetEmbeddingProgress($embedding, 5, 5, 'Finalizing embedding generation', [
                    'total_progress_percentage' => 95
                ]));
                $embedding->markAsCompleted(count($chunks), $totalPoints, $processingStats);
            }

            // Broadcast completion
            broadcast(new DatasetEmbeddingCompleted($embedding));

            Log::info("Embedding generation completed", [
                'dataset_id' => $dataset->id,
                'total_points' => $totalPoints,
                'stats' => $processingStats
            ]);

            return $embedding;

        } catch (Exception $e) {
            $embedding->markAsFailed($e->getMessage());
            
            // Broadcast failure
            broadcast(new DatasetEmbeddingFailed($embedding, $e->getMessage()));
            
            Log::error("Embedding generation failed", [
                'dataset_id' => $dataset->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate embeddings for a variant using an existing DatasetEmbedding record.
     * This method doesn't create a new embedding record, it uses the one provided.
     */
    public function generateEmbeddingsForVariant(Dataset $dataset, DatasetEmbedding $embedding, array $options = []): array
    {
        $chunkSize = $options['chunk_size'] ?? 1000;
        $chunkOverlap = $options['chunk_overlap'] ?? 200;

        Log::info("Starting variant embedding generation", [
            'dataset_id' => $dataset->id,
            'dataset_uuid' => $dataset->uuid,
            'variant' => $embedding->variant,
            'collection' => $embedding->qdrant_collection_name,
            'chunk_size' => $chunkSize,
            'chunk_overlap' => $chunkOverlap
        ]);

        try {
            // Check if dataset contains pre-prepared JSONL files
            $jsonlFiles = $this->detectJsonlFiles($dataset);
            
            if (!empty($jsonlFiles)) {
                Log::info("Detected pre-prepared JSONL files for variant", [
                    'dataset_id' => $dataset->id,
                    'variant' => $embedding->variant,
                    'jsonl_files' => array_map(fn($f) => $f->name, $jsonlFiles)
                ]);
                
                // Create Qdrant collection
                $this->ensureQdrantCollection($embedding->qdrant_collection_name);
                
                // Process JSONL files directly
                $totalPoints = $this->processJsonlFilesToQdrant($jsonlFiles, $embedding->qdrant_collection_name);
                
                // Return results
                return [
                    'total_chunks' => count($jsonlFiles),
                    'total_points' => $totalPoints,
                    'processing_stats' => [
                        'total_files_processed' => count($jsonlFiles),
                        'method' => 'jsonl',
                        'variant' => $embedding->variant,
                    ],
                ];
            }

            // Regular processing (non-JSONL)
            // Extract text from files
            $allFiles = $dataset->repository->files;
            $textFiles = $allFiles->filter(function ($file) {
                return in_array(strtolower(pathinfo($file->name, PATHINFO_EXTENSION)), ['txt', 'md']);
            });

            if ($textFiles->isEmpty()) {
                throw new Exception('No text files found in the dataset for embedding generation');
            }

            $documents = [];
            foreach ($textFiles as $file) {
                $filePath = Storage::disk('local')->path($file->path);
                if (!file_exists($filePath)) {
                    continue;
                }
                
                $content = file_get_contents($filePath);
                if (empty(trim($content))) {
                    continue;
                }

                $documents[] = [
                    'text' => $content,
                    'metadata' => [
                        'file_name' => $file->name,
                        'file_id' => $file->id,
                        'dataset_id' => $dataset->id,
                        'dataset_uuid' => $dataset->uuid,
                    ],
                ];
            }

            if (empty($documents)) {
                throw new Exception('No valid text content found for embedding generation');
            }

            // Chunk documents
            $chunks = $this->chunkDocuments($documents, $chunkSize, $chunkOverlap);
            
            // Ensure Qdrant collection exists
            $this->ensureQdrantCollection($embedding->qdrant_collection_name);

            // Generate embeddings and upload to Qdrant in batches
            $totalPoints = $this->generateAndUploadEmbeddings($chunks, $embedding->qdrant_collection_name);

            Log::info("Variant embedding generation completed", [
                'dataset_id' => $dataset->id,
                'variant' => $embedding->variant,
                'total_chunks' => count($chunks),
                'total_points' => $totalPoints,
            ]);

            return [
                'total_chunks' => count($chunks),
                'total_points' => $totalPoints,
                'processing_stats' => [
                    'total_documents' => count($documents),
                    'chunk_size' => $chunkSize,
                    'chunk_overlap' => $chunkOverlap,
                    'variant' => $embedding->variant,
                ],
            ];

        } catch (Exception $e) {
            Log::error("Variant embedding generation failed", [
                'dataset_id' => $dataset->id,
                'variant' => $embedding->variant,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Search similar content in a dataset.
     */
    public function search(Dataset $dataset, string $query, int $limit = 10, ?array $filter = null, ?string $variant = null): array
    {
        // Get the correct embedding based on variant
        $embedding = $variant 
            ? $dataset->getEmbeddingByVariant($variant)
            : $dataset->embedding;
        
        if (!$embedding || !$embedding->isCompleted()) {
            $variantText = $variant ? " (variant: {$variant})" : '';
            throw new Exception("Dataset embeddings{$variantText} are not available. Please generate embeddings first.");
        }

        // Generate embedding for the query
        $queryVector = $this->embeddingService->generateEmbedding($query);

        // Search in Qdrant using the embedding's collection name
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
    private function processChunksToQdrant(array $chunks, string $collectionName, ?DatasetEmbedding $embedding = null): int
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
            
            // Broadcast detailed batch progress if embedding is provided
            if ($embedding) {
                // Calculate more granular progress for the embedding step
                // Step 4 is the embedding generation (steps 1-3 are 60% of total, step 4 is 35%, step 5 is 5%)
                $baseProgress = 60; // Steps 1-3 completed
                $embeddingStepProgress = ($batchNumber / $totalBatches) * 35; // Step 4 progress (35% of total)
                $totalProgressPercentage = $baseProgress + $embeddingStepProgress;
                
                broadcast(new DatasetEmbeddingProgress(
                    $embedding,
                    $batchNumber,
                    $totalBatches,
                    "Processing batch {$batchNumber}/{$totalBatches}",
                    [
                        'chunks_processed' => $offset + count($chunkBatch),
                        'total_chunks' => $totalChunks,
                        'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                        'embedding_step_progress' => round($embeddingStepProgress, 1),
                        'total_progress_percentage' => round($totalProgressPercentage, 1),
                        'current_batch' => $batchNumber,
                        'total_batches' => $totalBatches
                    ]
                ));
            }

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

        return [
            'embedding_uuid' => $embedding->uuid,
            'dataset_uuid' => $dataset->uuid,
            'status' => $embedding->status,
            'status_text' => $embedding->getStatusText(),
            'collection_name' => $embedding->qdrant_collection_name,
            'embedding_model' => $embedding->embedding_model,
            'total_chunks' => $embedding->total_chunks,
            'total_points' => $embedding->total_points,
            'processing_started_at' => $embedding->processing_started_at,
            'processing_completed_at' => $embedding->processing_completed_at,
            'processing_stats' => $embedding->processing_stats,
            'error_message' => $embedding->error_message,
            'progress_percentage' => $embedding->getProgressPercentage(),
            'estimated_time_remaining' => $embedding->getEstimatedTimeRemaining(),
            'is_pending' => $embedding->isPending(),
            'is_processing' => $embedding->isProcessing(),
            'is_completed' => $embedding->isCompleted(),
            'is_failed' => $embedding->hasFailed(),
        ];
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

    /**
     * Detect JSONL files in the dataset that contain pre-prepared embeddings.
     */
    private function detectJsonlFiles(Dataset $dataset): array
    {
        $repository = $dataset->repository;
        $files = $repository->files()->where('type', 'file')->get();
        
        $jsonlFiles = [];
        
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
            
            if (in_array($extension, ['jsonl', 'ndjson'])) {
                // Verify it's a valid embedding JSONL by checking the first line
                if ($this->isValidEmbeddingJsonl($file)) {
                    $jsonlFiles[] = $file;
                }
            }
        }
        
        return $jsonlFiles;
    }

    /**
     * Check if a JSONL file contains valid embedding data.
     */
    private function isValidEmbeddingJsonl(RepositoryFile $file): bool
    {
        $tempPath = null;
        
        try {
            // Download file temporarily to check format
            $tempPath = $this->downloadFileTemporarily($file);
            
            // Read first line to validate format
            $handle = fopen($tempPath, 'r');
            if (!$handle) {
                return false;
            }
            
            $firstLine = fgets($handle);
            fclose($handle);
            
            if (!$firstLine) {
                return false;
            }
            
            $data = json_decode(trim($firstLine), true);
            
            // Check if it has the required structure for Qdrant embeddings
            // Note: ID can be any string since we'll convert it to UUID
            $isValid = $data !== null 
                && isset($data['id']) 
                && !empty($data['id'])
                && isset($data['vector']) 
                && is_array($data['vector'])
                && count($data['vector']) > 0  // Ensure vector has data
                && isset($data['payload']);
            
            // Clean up temporary file
            FileSystemUtils::cleanupTemporaryFiles($tempPath);
            
            return $isValid;
            
        } catch (Exception $e) {
            Log::warning("Failed to validate JSONL file", [
                'file_id' => $file->id,
                'file_name' => $file->name,
                'error' => $e->getMessage()
            ]);
            
            // Clean up temp file on error
            if ($tempPath) {
                FileSystemUtils::cleanupTemporaryFiles($tempPath);
            }
            
            return false;
        }
    }

    /**
     * Process JSONL files directly to Qdrant without embedding generation.
     */
    private function processJsonlFilesToQdrant(array $jsonlFiles, string $collectionName): int
    {
        $totalPoints = 0;
        
        foreach ($jsonlFiles as $file) {
            $tempPath = null;
            
            try {
                Log::info("Processing JSONL file", [
                    'file_name' => $file->name,
                    'collection' => $collectionName,
                    'file_size' => $file->size
                ]);
                
                // Download file temporarily
                $tempPath = $this->downloadFileTemporarily($file);
                
                // Read and process JSONL file
                $points = [];
                $handle = fopen($tempPath, 'r');
                
                if (!$handle) {
                    throw new Exception("Could not open JSONL file: {$file->name}");
                }
                
                $lineNumber = 0;
                while (($line = fgets($handle)) !== false) {
                    $lineNumber++;
                    $line = trim($line);
                    
                    if (empty($line)) {
                        continue; // Skip empty lines
                    }
                    
                    $data = json_decode($line, true);
                    
                    if ($data === null) {
                        Log::warning("Invalid JSON on line {$lineNumber} in file {$file->name}");
                        continue;
                    }
                    
                    // Validate required fields
                    if (!isset($data['id']) || !isset($data['vector']) || !isset($data['payload'])) {
                        Log::warning("Missing required fields on line {$lineNumber} in file {$file->name}");
                        continue;
                    }
                    
                    // Store original ID and generate UUID for Qdrant
                    $originalId = $data['id'];
                    $originalVector = $data['vector'];
                    $originalPayload = $data['payload'];
                    
                    // Generate a valid UUID for Qdrant point ID
                    $newId = Str::uuid()->toString();
                    
                    // Log ID conversion for first few entries
                    if ($lineNumber <= 3) {
                        Log::info("Converting JSONL ID", [
                            'original_id' => $originalId,
                            'new_uuid' => $newId,
                            'line' => $lineNumber
                        ]);
                    }
                    
                    // Create new payload structure with all original data as metadata
                    $newPayload = [
                        'original_id' => $originalId,
                        'text' => $originalPayload['text'] ?? '', // Extract text field for Qdrant
                        'metadata' => $originalPayload, // Include all original payload data as metadata
                        'source_file' => [
                            'file_id' => $file->id,
                            'file_name' => $file->name,
                            'file_path' => $file->path,
                            'file_size' => $file->size,
                            'mime_type' => $file->mime_type
                        ]
                    ];
                    
                    // Update the data structure
                    $data['id'] = $newId;
                    $data['vector'] = $originalVector;
                    $data['payload'] = $newPayload;
                    
                    $points[] = $data;
                    
                    // Process in batches to manage memory
                    if (count($points) >= 100) {
                        $this->qdrantService->batchInsertPoints($collectionName, $points);
                        $totalPoints += count($points);
                        $points = [];
                        
                        // Force garbage collection
                        gc_collect_cycles();
                    }
                }
                
                fclose($handle);
                
                // Process remaining points
                if (!empty($points)) {
                    $this->qdrantService->batchInsertPoints($collectionName, $points);
                    $totalPoints += count($points);
                }
                
                // Clean up temporary file
                FileSystemUtils::cleanupTemporaryFiles($tempPath);
                
                Log::info("Completed processing JSONL file", [
                    'file_name' => $file->name,
                    'points_inserted' => $totalPoints
                ]);
                
            } catch (Exception $e) {
                Log::error("Failed to process JSONL file", [
                    'file_name' => $file->name,
                    'error' => $e->getMessage()
                ]);
                
                // Clean up temp file on error
                if ($tempPath) {
                    FileSystemUtils::cleanupTemporaryFiles($tempPath);
                }
                
                throw new Exception("Failed to process JSONL file {$file->name}: {$e->getMessage()}");
            }
        }
        
        return $totalPoints;
    }
}
