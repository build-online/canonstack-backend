<?php

namespace App\Services\Api\V1;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QdrantService
{
    private string $baseUrl;
    private ?string $apiKey;
    private int $timeout;
    private int $defaultVectorSize;

    public function __construct()
    {
        $this->baseUrl = config('services.qdrant.url') ?? 'http://localhost:6333';
        $this->apiKey = config('services.qdrant.api_key');
        $this->timeout = config('services.qdrant.timeout') ?? 30;
        $this->defaultVectorSize = config('services.qdrant.default_vector_size') ?? 1536;
    }

    /**
     * Create a new collection in Qdrant.
     */
    public function createCollection(string $collectionName, int $vectorSize = null, array $payloadIndexes = []): array
    {
        $vectorSize = $vectorSize ?? $this->defaultVectorSize;
        
        $response = $this->makeRequest('PUT', "/collections/{$collectionName}", [
            'vectors' => [
                'size' => $vectorSize,
                'distance' => 'Cosine'
            ]
        ]);

        Log::info("Qdrant collection created", [
            'collection' => $collectionName,
            'vector_size' => $vectorSize
        ]);

        // Create payload indexes if specified
        if (!empty($payloadIndexes)) {
            foreach ($payloadIndexes as $fieldPath => $fieldType) {
                $this->createPayloadIndex($collectionName, $fieldPath, $fieldType);
            }
        }

        return $response;
    }

    /**
     * Create a payload index for filtering on a specific field.
     * 
     * @param string $collectionName The collection name
     * @param string $fieldPath The field path (e.g., 'metadata.ot_ref')
     * @param string $fieldType The field type ('keyword', 'integer', 'float', 'bool', 'geo', 'text')
     */
    public function createPayloadIndex(string $collectionName, string $fieldPath, string $fieldType = 'keyword'): array
    {
        $response = $this->makeRequest('PUT', "/collections/{$collectionName}/index", [
            'field_name' => $fieldPath,
            'field_schema' => $fieldType
        ]);

        Log::info("Qdrant payload index created", [
            'collection' => $collectionName,
            'field_path' => $fieldPath,
            'field_type' => $fieldType
        ]);

        return $response;
    }

    /**
     * Create multiple payload indexes at once.
     * 
     * @param string $collectionName The collection name
     * @param array $indexes Associative array of field_path => field_type
     */
    public function createPayloadIndexes(string $collectionName, array $indexes): void
    {
        foreach ($indexes as $fieldPath => $fieldType) {
            try {
                $this->createPayloadIndex($collectionName, $fieldPath, $fieldType);
            } catch (Exception $e) {
                // Log but don't fail if index already exists
                if (!str_contains($e->getMessage(), 'already exists')) {
                    Log::warning("Failed to create payload index", [
                        'collection' => $collectionName,
                        'field_path' => $fieldPath,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Check if a collection exists.
     */
    public function collectionExists(string $collectionName): bool
    {
        try {
            $this->makeRequest('GET', "/collections/{$collectionName}");
            return true;
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), '404')) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Delete a collection.
     */
    public function deleteCollection(string $collectionName): array
    {
        $response = $this->makeRequest('DELETE', "/collections/{$collectionName}");
        
        Log::info("Qdrant collection deleted", ['collection' => $collectionName]);
        
        return $response;
    }

    /**
     * Insert points into a collection.
     */
    public function insertPoints(string $collectionName, array $points): array
    {
        $response = $this->makeRequest('PUT', "/collections/{$collectionName}/points?wait=true", [
            'points' => $points
        ]);

        Log::info("Points inserted to Qdrant", [
            'collection' => $collectionName,
            'count' => count($points)
        ]);

        return $response;
    }

    /**
     * Search for similar vectors.
     */
    public function search(string $collectionName, array $vector, int $limit = 10, ?array $filter = null): array
    {
        $searchPayload = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
            'with_vector' => false
        ];

        if ($filter) {
            $searchPayload['filter'] = $filter;
        }

        $response = $this->makeRequest('POST', "/collections/{$collectionName}/points/search", $searchPayload);

        Log::debug("Qdrant search performed", [
            'collection' => $collectionName,
            'limit' => $limit,
            'results_count' => count($response['result'] ?? [])
        ]);

        return $response['result'] ?? [];
    }

    /**
     * Batch insert points with automatic chunking.
     */
    public function batchInsertPoints(string $collectionName, array $points, int $batchSize = 100): array
    {
        $results = [];
        $chunks = array_chunk($points, $batchSize);
        
        foreach ($chunks as $index => $chunk) {
            Log::info("Processing batch", [
                'batch' => $index + 1,
                'total_batches' => count($chunks),
                'batch_size' => count($chunk)
            ]);
            
            $results[] = $this->insertPoints($collectionName, $chunk);
            
            // Small delay to avoid overwhelming the server
            if (count($chunks) > 1) {
                usleep(100000); // 100ms
            }
        }
        
        return $results;
    }

    /**
     * Scroll through points in a collection (for sampling/iteration).
     */
    public function scroll(string $collectionName, int $limit = 100, ?string $offset = null, bool $withVectors = false): array
    {
        $scrollPayload = [
            'limit' => $limit,
            'with_payload' => true,
            'with_vector' => $withVectors
        ];

        if ($offset) {
            $scrollPayload['offset'] = $offset;
        }

        $response = $this->makeRequest('POST', "/collections/{$collectionName}/points/scroll", $scrollPayload);

        Log::debug("Qdrant scroll performed", [
            'collection' => $collectionName,
            'limit' => $limit,
            'points_count' => count($response['result']['points'] ?? [])
        ]);

        return $response['result'] ?? [];
    }

    /**
     * Make HTTP request to Qdrant API.
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        
        $request = Http::timeout($this->timeout);
        
        if ($this->apiKey) {
            $request = $request->withHeaders([
                'api-key' => $this->apiKey
            ]);
        }

        $response = match(strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'DELETE' => $request->delete($url),
            default => throw new Exception("Unsupported HTTP method: {$method}")
        };

        if (!$response->successful()) {
            $error = "Qdrant API error: {$response->status()} - {$response->body()}";
            Log::error($error, [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status()
            ]);
            throw new Exception($error);
        }

        return $response->json();
    }

    /**
     * Generate a unique collection name for a dataset.
     */
    public static function generateCollectionName(string $datasetUuid): string
    {
        return "dataset_" . str_replace('-', '_', $datasetUuid);
    }
}
