<?php

namespace App\Services\Api\V1;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $maxTokensPerRequest;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->baseUrl = config('services.openai.base_url');
        $this->model = config('services.openai.embedding_model');
        $this->maxTokensPerRequest = config('services.openai.max_tokens_per_request');
    }

    /**
     * Validate that API key is configured.
     */
    private function validateApiKey(): void
    {
        if (!$this->apiKey) {
            throw new Exception('OpenAI API key is not configured');
        }
    }

    /**
     * Generate embeddings for a single text.
     */
    public function generateEmbedding(string $text): array
    {
        $response = $this->makeRequest([
            'input' => $text,
            'model' => $this->model
        ]);

        return $response['data'][0]['embedding'] ?? [];
    }

    /**
     * Generate embeddings for multiple texts in batch.
     */
    public function generateBatchEmbeddings(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $response = $this->makeRequest([
            'input' => $texts,
            'model' => $this->model
        ]);

        $embeddings = [];
        foreach ($response['data'] as $item) {
            $embeddings[] = $item['embedding'];
        }

        return $embeddings;
    }

    /**
     * Generate embeddings with automatic batching for large datasets.
     */
    public function generateEmbeddingsWithBatching(array $texts, int $batchSize = 50): array
    {
        // Check memory usage and adjust batch size if needed
        $memoryLimit = $this->getMemoryLimitBytes();
        $currentMemory = memory_get_usage(true);
        $availableMemory = $memoryLimit - $currentMemory;
        
        // If we're using more than 70% of memory, reduce batch size
        if ($currentMemory > ($memoryLimit * 0.7)) {
            $batchSize = max(10, $batchSize / 2);
            Log::warning("Reducing batch size due to memory usage", [
                'current_memory_mb' => round($currentMemory / 1024 / 1024, 2),
                'memory_limit_mb' => round($memoryLimit / 1024 / 1024, 2),
                'new_batch_size' => $batchSize
            ]);
        }

        $allEmbeddings = [];
        $totalTexts = count($texts);
        $processed = 0;

        // Process in chunks to avoid memory issues
        for ($offset = 0; $offset < $totalTexts; $offset += $batchSize) {
            $batch = array_slice($texts, $offset, $batchSize);
            $batchNumber = intval($offset / $batchSize) + 1;
            $totalBatches = ceil($totalTexts / $batchSize);

            Log::info("Processing embedding batch", [
                'batch' => $batchNumber,
                'total_batches' => $totalBatches,
                'batch_size' => count($batch),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]);

            try {
                $batchEmbeddings = $this->generateBatchEmbeddings($batch);
                $allEmbeddings = array_merge($allEmbeddings, $batchEmbeddings);
                $processed += count($batch);

                // Force garbage collection to free memory
                if ($batchNumber % 5 === 0) {
                    gc_collect_cycles();
                }

                // Rate limiting: small delay between batches
                if ($offset + $batchSize < $totalTexts) {
                    usleep(200000); // 200ms delay
                }

                Log::debug("Batch completed", [
                    'processed' => $processed,
                    'total' => $totalTexts,
                    'progress' => round(($processed / $totalTexts) * 100, 1) . '%'
                ]);

            } catch (Exception $e) {
                Log::error("Failed to generate embeddings for batch", [
                    'batch' => $batchNumber,
                    'error' => $e->getMessage(),
                    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
                ]);
                throw $e;
            }
        }

        return $allEmbeddings;
    }

    /**
     * Chunk text into smaller pieces for embedding.
     */
    public function chunkText(string $text, int $chunkSize = 1000, int $overlap = 200): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // For very large texts, check memory and potentially increase chunk size
        $textLength = strlen($text);
        if ($textLength > 1000000) { // 1MB
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->getMemoryLimitBytes();
            
            if ($memoryUsage > ($memoryLimit * 0.6)) {
                // Increase chunk size to reduce number of chunks
                $chunkSize = min($chunkSize * 2, 4000);
                Log::info("Increased chunk size for large text", [
                    'text_size_mb' => round($textLength / 1024 / 1024, 2),
                    'new_chunk_size' => $chunkSize,
                    'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2)
                ]);
            }
        }

        $chunks = [];
        $start = 0;
        $chunkCount = 0;

        while ($start < $textLength) {
            $chunk = substr($text, $start, $chunkSize);
            
            // Avoid cutting words in half - find the last space
            if ($start + $chunkSize < $textLength) {
                $lastSpace = strrpos($chunk, ' ');
                if ($lastSpace !== false && $lastSpace > $chunkSize * 0.8) {
                    $chunk = substr($chunk, 0, $lastSpace);
                }
            }

            $chunk = trim($chunk);
            if (!empty($chunk)) {
                $chunks[] = $chunk;
                $chunkCount++;
                
                // Check memory every 1000 chunks
                if ($chunkCount % 1000 === 0) {
                    $currentMemory = memory_get_usage(true);
                    $memoryLimit = $this->getMemoryLimitBytes();
                    
                    if ($currentMemory > ($memoryLimit * 0.8)) {
                        Log::warning("High memory usage during chunking", [
                            'chunks_created' => $chunkCount,
                            'memory_usage_mb' => round($currentMemory / 1024 / 1024, 2),
                            'memory_limit_mb' => round($memoryLimit / 1024 / 1024, 2)
                        ]);
                        
                        // Force garbage collection
                        gc_collect_cycles();
                    }
                }
            }

            $actualChunkLength = strlen($chunk);
            $start += max(1, $actualChunkLength - $overlap); // Ensure progress
        }

        Log::info("Text chunking completed", [
            'total_chunks' => count($chunks),
            'text_size_mb' => round($textLength / 1024 / 1024, 2),
            'chunk_size' => $chunkSize,
            'overlap' => $overlap
        ]);

        return $chunks;
    }

    /**
     * Prepare chunks from structured data.
     */
    public function prepareChunks(array $data, int $chunkSize = 1000, int $overlap = 200): array
    {
        $chunks = [];
        $totalRows = count($data);
        
        Log::info("Starting chunk preparation", [
            'total_rows' => $totalRows,
            'chunk_size' => $chunkSize,
            'overlap' => $overlap,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);
        
        foreach ($data as $index => $row) {
            if (!isset($row['text']) || empty($row['text'])) {
                continue;
            }

            // Log progress for large datasets
            if ($index > 0 && $index % 100 === 0) {
                $currentMemory = memory_get_usage(true);
                Log::info("Chunk preparation progress", [
                    'processed_rows' => $index,
                    'total_rows' => $totalRows,
                    'progress' => round(($index / $totalRows) * 100, 1) . '%',
                    'chunks_created' => count($chunks),
                    'memory_usage_mb' => round($currentMemory / 1024 / 1024, 2)
                ]);
                
                // Check memory usage
                $memoryLimit = $this->getMemoryLimitBytes();
                if ($currentMemory > ($memoryLimit * 0.8)) {
                    Log::warning("High memory usage during chunk preparation", [
                        'memory_usage_mb' => round($currentMemory / 1024 / 1024, 2),
                        'memory_limit_mb' => round($memoryLimit / 1024 / 1024, 2)
                    ]);
                    gc_collect_cycles();
                }
            }

            $textChunks = $this->chunkText($row['text'], $chunkSize, $overlap);
            
            foreach ($textChunks as $chunkIndex => $chunkText) {
                $chunks[] = [
                    'id' => uniqid("chunk_{$index}_{$chunkIndex}_"),
                    'text' => $chunkText,
                    'metadata' => array_merge($row['metadata'] ?? [], [
                        'original_index' => $index,
                        'chunk_index' => $chunkIndex,
                        'total_chunks' => count($textChunks)
                    ])
                ];
            }
            
            // Free memory for processed text
            unset($data[$index]);
        }

        Log::info("Chunk preparation completed", [
            'total_chunks' => count($chunks),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);

        return $chunks;
    }

    /**
     * Extract text from common file types.
     */
    public function extractTextFromFile(string $filePath, string $fileExtension): string
    {
        $text = '';

        try {
            switch (strtolower($fileExtension)) {
                case 'txt':
                case 'md':
                case 'readme':
                    $text = file_get_contents($filePath);
                    break;

                case 'json':
                    $jsonData = json_decode(file_get_contents($filePath), true);
                    if ($jsonData) {
                        $text = $this->extractTextFromArray($jsonData);
                    }
                    break;

                case 'csv':
                    $text = $this->extractTextFromCsv($filePath);
                    break;

                case 'xml':
                    $text = $this->extractTextFromXml($filePath);
                    break;

                default:
                    // For unsupported types, try to read as plain text
                    if (is_readable($filePath)) {
                        $content = file_get_contents($filePath);
                        // Check if it's likely text (contains readable characters)
                        if (mb_check_encoding($content, 'UTF-8')) {
                            $text = $content;
                        }
                    }
            }
        } catch (Exception $e) {
            Log::warning("Failed to extract text from file", [
                'file' => $filePath,
                'extension' => $fileExtension,
                'error' => $e->getMessage()
            ]);
        }

        return $text;
    }

    /**
     * Extract text from array (JSON data).
     */
    private function extractTextFromArray(array $data): string
    {
        $texts = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $texts[] = $value;
            } elseif (is_array($value)) {
                $texts[] = $this->extractTextFromArray($value);
            }
        }
        
        return implode(' ', $texts);
    }

    /**
     * Extract text from CSV file.
     */
    private function extractTextFromCsv(string $filePath): string
    {
        $texts = [];
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $texts[] = implode(' ', $data);
            }
            fclose($handle);
        }
        
        return implode(' ', $texts);
    }

    /**
     * Extract text from XML file.
     */
    private function extractTextFromXml(string $filePath): string
    {
        $content = file_get_contents($filePath);
        // Remove XML tags and return plain text
        return strip_tags($content);
    }

    /**
     * Make HTTP request to OpenAI API.
     */
    private function makeRequest(array $data): array
    {
        $this->validateApiKey();
        
        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])
            ->post($this->baseUrl . '/embeddings', $data);

        if (!$response->successful()) {
            $error = "OpenAI API error: {$response->status()} - {$response->body()}";
            Log::error($error, [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            throw new Exception($error);
        }

        return $response->json();
    }

    /**
     * Estimate token count for text (approximate).
     */
    public function estimateTokenCount(string $text): int
    {
        // Rough approximation: 1 token ≈ 4 characters for English text
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Check if text is too long for embedding.
     */
    public function isTextTooLong(string $text): bool
    {
        return $this->estimateTokenCount($text) > $this->maxTokensPerRequest;
    }

    /**
     * Get the embedding model being used.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get vector size for the current model.
     */
    public function getVectorSize(): int
    {
        return match($this->model) {
            'text-embedding-3-small' => 1536,
            'text-embedding-3-large' => 3072,
            'text-embedding-ada-002' => 1536,
            default => 1536
        };
    }

    /**
     * Get memory limit in bytes.
     */
    private function getMemoryLimitBytes(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            // No memory limit
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) $memoryLimit;
        
        return match($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }
}
