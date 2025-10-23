<?php

namespace App\Services\Api\V1\AI;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QueryMetadataFilterService
{
    /**
     * Extract metadata filters from a user query based on dataset structure.
     * Uses AI to intelligently determine which metadata filters should be applied.
     * 
     * @param string $originalQuery The user's query
     * @param string $structureDescription The dataset structure description
     * @return array Contains 'filters' array and metadata about the extraction
     */
    public function extractFilters(string $originalQuery, string $structureDescription): array
    {
        $apiKey = config('services.openai.api_key');
        
        if (empty($apiKey)) {
            Log::warning("OpenAI API key not configured - skipping metadata filter extraction");
            return [
                'filters' => null,
                'filters_used' => false,
                'reason' => 'OpenAI API key not configured'
            ];
        }

        try {
            $systemPrompt = $this->buildSystemPrompt();
            $userPrompt = $this->buildUserPrompt($originalQuery, $structureDescription);

            Log::info("Extracting metadata filters from query", [
                'query' => $originalQuery,
                'structure_length' => strlen($structureDescription)
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.1, // Low temperature for precise extraction
                'max_tokens' => 500,
                'response_format' => ['type' => 'json_object']
            ]);

            if (!$response->successful()) {
                throw new Exception("OpenAI API request failed: " . $response->body());
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            if (empty($content)) {
                throw new Exception("Empty response from OpenAI");
            }

            // Parse the JSON response
            $filterData = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Failed to parse JSON response: " . json_last_error_msg());
            }

            // Convert AI response to Qdrant filter format
            $qdrantFilter = $this->convertToQdrantFilter($filterData);

            Log::info("Metadata filters extracted successfully", [
                'query' => $originalQuery,
                'filters_extracted' => !empty($qdrantFilter),
                'filter_count' => isset($filterData['filters']) ? count($filterData['filters']) : 0
            ]);

            return [
                'filters' => $qdrantFilter,
                'filters_used' => !empty($qdrantFilter),
                'extracted_filters' => $filterData['filters'] ?? [],
                'reasoning' => $filterData['reasoning'] ?? null,
                'tokens_used' => $data['usage'] ?? null,
                'model_used' => 'gpt-4o-mini'
            ];

        } catch (Exception $e) {
            Log::warning("Metadata filter extraction failed - no filters applied", [
                'original_query' => $originalQuery,
                'error' => $e->getMessage()
            ]);

            // Fallback to no filters if extraction fails
            return [
                'filters' => null,
                'filters_used' => false,
                'reason' => 'Filter extraction failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build system prompt for metadata filter extraction.
     */
    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are an expert query analysis assistant for RAG (Retrieval-Augmented Generation) systems. Your task is to analyze user queries and extract metadata filters that should be applied to Qdrant vector searches.

**Your Responsibilities:**
1. Analyze the user's query to identify specific metadata values they're looking for
2. Review the dataset structure to understand available metadata fields and their possible values
3. Extract ONLY filters that are explicitly or clearly implied in the query
4. DO NOT invent filters that aren't clearly indicated by the query
5. Consider field types (string, integer, boolean, array) when extracting values

**Guidelines for Filter Extraction:**
- Only extract filters when the query clearly references a specific metadata value
- Match field names exactly as they appear in the dataset structure
- For string reference fields (book names, citations): extract the BASE value only (e.g., "Genesis" not "Genesis 1:1") and use "contains" operator
- For enum/category fields (form, function, type): use exact match with "match" operator
- For date/time fields: extract the relevant date or date range
- For numeric fields: extract specific values or ranges
- For boolean fields: determine true/false based on query intent
- **PREFER "contains" operator for text fields that might have additional details (like references, names, titles)**
- **USE "match" operator only for categorical fields with fixed values (like status, type, category)**
- If uncertain, DO NOT extract a filter - vector search alone is sufficient

**Output Format:**
Return a JSON object with this structure:
{
  "filters": [
    {
      "field": "exact_field_name_from_structure",
      "operator": "match|contains|range|in|greater_than|less_than|exists",
      "value": "extracted_value_or_array",
      "confidence": "high|medium|low"
    }
  ],
  "reasoning": "Brief explanation of why these filters were extracted"
}

If NO filters should be applied, return:
{
  "filters": [],
  "reasoning": "Query does not specify metadata criteria"
}

**Supported Operators:**
- match: Exact match (string, number, boolean)
- contains: Substring or array contains (string, array)
- range: Numeric or date range (requires min/max)
- in: Value in list of options (array)
- greater_than: Numeric comparison
- less_than: Numeric comparison
- exists: Field existence check (boolean)

RESPOND ONLY WITH VALID JSON. NO MARKDOWN, NO EXPLANATIONS OUTSIDE THE JSON.
PROMPT;
    }

    /**
     * Build user prompt with query and structure.
     */
    private function buildUserPrompt(string $originalQuery, string $structureDescription): string
    {
        return <<<PROMPT
**User Query:**
{$originalQuery}

**Dataset Structure:**
{$structureDescription}

**Task:**
Analyze the query above and extract any metadata filters that should be applied based on the dataset structure. Return a JSON object with the filters and your reasoning.
PROMPT;
    }

    /**
     * Convert AI-extracted filters to Qdrant filter format.
     * 
     * Qdrant filter format:
     * {
     *   "must": [
     *     { "key": "metadata.field_name", "match": { "value": "some_value" } }
     *   ]
     * }
     */
    private function convertToQdrantFilter(array $filterData): ?array
    {
        if (empty($filterData['filters'])) {
            return null;
        }

        $mustConditions = [];

        foreach ($filterData['filters'] as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? 'match';
            $value = $filter['value'] ?? null;
            $confidence = $filter['confidence'] ?? 'low';

            // Skip low confidence filters
            if ($confidence === 'low') {
                Log::debug("Skipping low confidence filter", ['filter' => $filter]);
                continue;
            }

            if (empty($field) || $value === null) {
                continue;
            }

            // Build Qdrant filter condition based on operator
            $condition = $this->buildQdrantCondition($field, $operator, $value);
            
            if ($condition) {
                $mustConditions[] = $condition;
            }
        }

        if (empty($mustConditions)) {
            return null;
        }

        return [
            'must' => $mustConditions
        ];
    }

    /**
     * Build a single Qdrant filter condition.
     */
    private function buildQdrantCondition(string $field, string $operator, $value): ?array
    {
        // Prefix field with 'metadata.' for payload access in Qdrant
        $qdrantKey = "metadata.{$field}";

        switch ($operator) {
            case 'match':
                return [
                    'key' => $qdrantKey,
                    'match' => ['value' => $value]
                ];

            case 'contains':
                // For string contains/partial match - use 'text' for prefix matching
                // This requires a 'text' index on the field
                // This allows "Genesis" to match "Genesis 1:1", "Genesis 2:4", etc.
                return [
                    'key' => $qdrantKey,
                    'match' => ['text' => $value]
                ];

            case 'in':
                // Value should be in the list
                if (!is_array($value)) {
                    $value = [$value];
                }
                return [
                    'key' => $qdrantKey,
                    'match' => ['any' => $value]
                ];

            case 'range':
                // Numeric or date range
                $rangeCondition = [];
                if (isset($value['min'])) {
                    $rangeCondition['gte'] = $value['min'];
                }
                if (isset($value['max'])) {
                    $rangeCondition['lte'] = $value['max'];
                }
                if (empty($rangeCondition)) {
                    return null;
                }
                return [
                    'key' => $qdrantKey,
                    'range' => $rangeCondition
                ];

            case 'greater_than':
                return [
                    'key' => $qdrantKey,
                    'range' => ['gt' => $value]
                ];

            case 'less_than':
                return [
                    'key' => $qdrantKey,
                    'range' => ['lt' => $value]
                ];

            case 'exists':
                // Check if field exists (has a value)
                // This is tricky in Qdrant - skip for now
                Log::debug("'exists' operator not fully supported, skipping", ['field' => $field]);
                return null;

            default:
                Log::warning("Unknown filter operator", ['operator' => $operator]);
                return null;
        }
    }
}

