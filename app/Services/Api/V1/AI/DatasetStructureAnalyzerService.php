<?php

namespace App\Services\Api\V1\AI;

use App\Models\Dataset;
use App\Models\DatasetEmbedding;
use App\Services\Api\V1\QdrantService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class DatasetStructureAnalyzerService
{
    private QdrantService $qdrantService;

    public function __construct(QdrantService $qdrantService)
    {
        $this->qdrantService = $qdrantService;
    }

    /**
     * Analyze dataset structure from embedded vectors in Qdrant.
     * 
     * @param Dataset $dataset
     * @param DatasetEmbedding $embedding
     * @param int $sampleSize Number of points to sample for analysis
     * @return string The structure description
     */
    public function analyzeStructure(Dataset $dataset, DatasetEmbedding $embedding, int $sampleSize = 100): string
    {
        try {
            $samplePoints = $this->samplePointsFromQdrant($embedding->qdrant_collection_name, $sampleSize);

            if (empty($samplePoints)) {
                throw new Exception("No points found in collection for analysis");
            }

            $structureAnalysis = $this->analyzePointsStructure($samplePoints);
            $repositoryDescription = $dataset->repository->description ?? null;

            $structureDescription = $this->generateStructureDescription(
                $structureAnalysis,
                $repositoryDescription,
                $dataset
            );

            return $structureDescription;

        } catch (Exception $e) {
            Log::error("Dataset structure analysis failed", [
                'dataset_id' => $dataset->id,
                'embedding_id' => $embedding->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Analyze dataset structure from pre-collected metadata during embedding generation.
     * This avoids re-sampling from Qdrant and uses the complete dataset metadata.
     * 
     * @param Dataset $dataset
     * @param array $collectedMetadata Metadata collected during embedding generation
     * @return string The structure description
     */
    public function analyzeFromCollectedMetadata(Dataset $dataset, array $collectedMetadata): string
    {
        try {
            if (empty($collectedMetadata)) {
                throw new Exception("No collected metadata provided for analysis");
            }

            $structureAnalysis = $collectedMetadata;

            $repositoryDescription = $dataset->repository->description ?? null;

            $structureDescription = $this->generateStructureDescription(
                $structureAnalysis,
                $repositoryDescription,
                $dataset
            );

            return $structureDescription;

        } catch (Exception $e) {
            Log::error("Dataset structure analysis from collected metadata failed", [
                'dataset_id' => $dataset->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sample random points from Qdrant collection.
     */
    private function samplePointsFromQdrant(string $collectionName, int $limit): array
    {
        try {
            $scrollResult = $this->qdrantService->scroll($collectionName, $limit);
            
            return $scrollResult['points'] ?? [];

        } catch (Exception $e) {
            Log::error("Failed to sample points from Qdrant", [
                'collection' => $collectionName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Analyze the structure of sampled points.
     */
    private function analyzePointsStructure(array $points): array
    {
        $analysis = [
            'total_samples' => count($points),
            'text_field_samples' => [],
            'metadata_fields' => [],
            'metadata_field_types' => [],
            'metadata_field_values' => [],
        ];

        foreach ($points as $point) {
            $payload = $point['payload'] ?? [];

            if (isset($payload['text']) && is_string($payload['text'])) {
                if (count($analysis['text_field_samples']) < 10) {
                    $analysis['text_field_samples'][] = mb_substr($payload['text'], 0, 500); // First 500 chars
                }
            }

            if (isset($payload['metadata']) && is_array($payload['metadata'])) {
                foreach ($payload['metadata'] as $key => $value) {
                    if (!isset($analysis['metadata_fields'][$key])) {
                        $analysis['metadata_fields'][$key] = 0;
                    }
                    $analysis['metadata_fields'][$key]++;

                    // Track field types
                    $type = gettype($value);
                    if (!isset($analysis['metadata_field_types'][$key])) {
                        $analysis['metadata_field_types'][$key] = [];
                    }
                    if (!isset($analysis['metadata_field_types'][$key][$type])) {
                        $analysis['metadata_field_types'][$key][$type] = 0;
                    }
                    $analysis['metadata_field_types'][$key][$type]++;

                    // Collect sample values (limited to avoid memory issues)
                    if (!isset($analysis['metadata_field_values'][$key])) {
                        $analysis['metadata_field_values'][$key] = [];
                    }
                    
                    // Store up to 20 unique values per field
                    $valueStr = is_scalar($value) ? (string)$value : json_encode($value);
                    if (count($analysis['metadata_field_values'][$key]) < 20 && 
                        !in_array($valueStr, $analysis['metadata_field_values'][$key])) {
                        $analysis['metadata_field_values'][$key][] = $valueStr;
                    }
                }
            }
        }

        // Calculate field frequencies as percentages
        foreach ($analysis['metadata_fields'] as $field => $count) {
            $analysis['metadata_fields'][$field] = [
                'count' => $count,
                'frequency_percent' => round(($count / $analysis['total_samples']) * 100, 1)
            ];
        }

        return $analysis;
    }

    /**
     * Generate AI description of dataset structure using OpenAI.
     */
    private function generateStructureDescription(array $analysis, ?string $repositoryDescription, Dataset $dataset): string
    {
        $apiKey = config('services.openai.api_key');
        
        if (empty($apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }

        $analysisSummary = $this->formatAnalysisForAI($analysis, $repositoryDescription);

        $systemPrompt = "You are an expert data analyst specializing in understanding dataset structures for RAG (Retrieval-Augmented Generation) systems. Your task is to analyze the structure of a dataset based on sampled data points and create a clear, comprehensive description.";

        $userPrompt = <<<PROMPT
I need you to analyze the structure of a dataset that will be used in a RAG system. Based on the samples and analysis provided below, create a comprehensive description of the dataset structure.

{$analysisSummary}

Please provide a clear, structured description that includes:
1. **Main Content**: What type of content does the text field contain? What is it about?
2. **Metadata Fields**: List each metadata field and describe:
   - What it represents
   - Its data type
   - Possible values (if it's an enum or has limited options)
   - How it's formatted (if it's structured data like dates, IDs, etc.)
3. **Dataset Context**: What domain or area does this dataset represent?
4. **Key Characteristics**: Any patterns, relationships, or important features of the data

Write this description in a clear, professional tone that would help someone understand how to query and work with this dataset effectively. Keep it concise but comprehensive.
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1500,
            ]);

            if (!$response->successful()) {
                throw new Exception("OpenAI API request failed: " . $response->body());
            }

            $result = $response->json();
            $description = $result['choices'][0]['message']['content'] ?? '';

            if (empty($description)) {
                throw new Exception("Empty description received from OpenAI");
            }

            return trim($description);

        } catch (Exception $e) {
            Log::error("Failed to generate structure description", [
                'dataset_id' => $dataset->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Failed to generate structure description: " . $e->getMessage());
        }
    }

    /**
     * Format analysis data for AI consumption.
     */
    private function formatAnalysisForAI(array $analysis, ?string $repositoryDescription): string
    {
        $formatted = "## Dataset Analysis\n\n";

        if (!empty($repositoryDescription)) {
            $formatted .= "### Repository Description:\n";
            $formatted .= $repositoryDescription . "\n\n";
        }

        $formatted .= "### Sample Information:\n";
        $formatted .= "- Total samples analyzed: {$analysis['total_samples']}\n\n";

        $formatted .= "### Text Field Samples:\n";
        if (!empty($analysis['text_field_samples'])) {
            foreach (array_slice($analysis['text_field_samples'], 0, 5) as $i => $sample) {
                $formatted .= "**Sample " . ($i + 1) . ":**\n```\n" . $sample . "\n```\n\n";
            }
        } else {
            $formatted .= "No text samples available.\n\n";
        }

        $formatted .= "### Metadata Fields:\n";
        if (!empty($analysis['metadata_fields'])) {
            foreach ($analysis['metadata_fields'] as $field => $info) {
                $formatted .= "- **{$field}**:\n";
                $formatted .= "  - Appears in {$info['frequency_percent']}% of samples\n";
                
                if (isset($analysis['metadata_field_types'][$field])) {
                    $types = array_keys($analysis['metadata_field_types'][$field]);
                    $formatted .= "  - Type(s): " . implode(', ', $types) . "\n";
                }
                
                if (isset($analysis['metadata_field_values'][$field]) && !empty($analysis['metadata_field_values'][$field])) {
                    $values = $analysis['metadata_field_values'][$field];
                    $valueCount = count($values);
                    
                    if ($valueCount <= 10) {
                        $formatted .= "  - Sample values: " . implode(', ', array_map(function($v) {
                            return "\"$v\"";
                        }, $values)) . "\n";
                    } else {
                        $formatted .= "  - Sample values (showing 5 of {$valueCount}): " . implode(', ', array_map(function($v) {
                            return "\"$v\"";
                        }, array_slice($values, 0, 5))) . "\n";
                    }
                }
                
                $formatted .= "\n";
            }
        } else {
            $formatted .= "No metadata fields found.\n\n";
        }

        return $formatted;
    }
}

