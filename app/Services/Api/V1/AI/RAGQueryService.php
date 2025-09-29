<?php

namespace App\Services\Api\V1\AI;

use App\Models\Dataset;
use App\Services\Api\V1\DatasetEmbeddingService;
use App\Services\Api\V1\AI\AIProviderService;
use Exception;
use Illuminate\Support\Facades\Log;

class RAGQueryService
{
    private DatasetEmbeddingService $embeddingService;
    private AIProviderService $aiService;

    public function __construct(
        DatasetEmbeddingService $embeddingService, 
        AIProviderService $aiService
    ) {
        $this->embeddingService = $embeddingService;
        $this->aiService = $aiService;
    }

    /**
     * Query dataset using RAG with AI response generation.
     */
    public function queryDataset(
        Dataset $dataset,
        string $query,
        string $aiProvider = 'openai',
        array $options = []
    ): array {
        // Validate that embeddings exist and are ready
        $embedding = $dataset->embedding;
        if (!$embedding || !$embedding->isCompleted()) {
            throw new Exception('Dataset embeddings are not available or not completed. Please generate embeddings first.');
        }

        // Validate AI provider
        if (!$this->aiService->isProviderAvailable($aiProvider)) {
            throw new Exception("AI provider '{$aiProvider}' is not configured or available.");
        }

        Log::info("Starting RAG query", [
            'dataset_id' => $dataset->id,
            'dataset_uuid' => $dataset->uuid,
            'query_length' => strlen($query),
            'ai_provider' => $aiProvider,
            'embedding_model' => $embedding->embedding_model
        ]);

        try {
            // Step 1: Search for relevant context using vector similarity
            $searchResults = $this->embeddingService->search(
                $dataset,
                $query,
                $options['search_limit'] ?? 10,
                $options['search_filter'] ?? null
            );

            Log::info("Vector search completed", [
                'dataset_id' => $dataset->id,
                'query' => $query,
                'total_results' => count($searchResults),
                'scores' => array_map(fn($r) => round($r['score'] ?? 0, 4), array_slice($searchResults, 0, 5))
            ]);

            // Filter by similarity threshold if specified
            $threshold = $options['search_threshold'] ?? 0.1; // Changed from 0.0 to 0.1
            $filteredResults = array_filter($searchResults, function($result) use ($threshold) {
                return $result['score'] >= $threshold;
            });

            if (empty($filteredResults)) {
                Log::warning("No relevant context found for query", [
                    'dataset_id' => $dataset->id,
                    'query' => $query,
                    'threshold' => $threshold,
                    'total_results' => count($searchResults),
                    'max_score' => count($searchResults) > 0 ? max(array_map(fn($r) => $r['score'] ?? 0, $searchResults)) : 0,
                    'all_scores' => array_map(fn($r) => round($r['score'] ?? 0, 4), $searchResults)
                ]);
                
                return [
                    'query' => $query,
                    'dataset_id' => $dataset->uuid,
                    'ai_provider' => $aiProvider,
                    'context_found' => false,
                    'relevant_chunks' => 0,
                    'ai_response' => null,
                    'message' => 'No relevant context found in the dataset for this query.',
                    'debug_info' => [
                        'total_results_from_qdrant' => count($searchResults),
                        'similarity_threshold_used' => $threshold,
                        'max_score_found' => count($searchResults) > 0 ? max(array_map(fn($r) => $r['score'] ?? 0, $searchResults)) : 0,
                        'all_scores' => array_map(fn($r) => round($r['score'] ?? 0, 4), $searchResults),
                        'suggestion' => count($searchResults) > 0 ? 'Try lowering the search_threshold parameter' : 'Check if embeddings were generated successfully'
                    ]
                ];
            }

            // Step 2: Build context from search results
            $context = $this->buildContext($filteredResults, $options);

            // Step 3: Create prompt using template or custom format
            $prompt = $this->buildPrompt($query, $context, $options);

            // Step 4: Generate AI response
            $aiResponse = $this->aiService->generateResponse($aiProvider, $prompt, [
                'model' => $options['model'] ?? null,
                'max_tokens' => $options['max_tokens'] ?? null,
                'temperature' => $options['temperature'] ?? 0.7
            ]);

            // Try to parse JSON response if it looks like JSON
            $parsedResponse = $this->parseAIResponse($aiResponse['response'], $options);

            Log::info("RAG query completed successfully", [
                'dataset_id' => $dataset->id,
                'relevant_chunks' => count($filteredResults),
                'ai_provider' => $aiProvider,
                'ai_model' => $aiResponse['model'],
                'total_tokens' => $aiResponse['usage']['total_tokens'],
                'response_parsed' => $parsedResponse['is_parsed']
            ]);

            return [
                'query' => $query,
                'dataset_id' => $dataset->uuid,
                'dataset_name' => $dataset->title ?? 'Unnamed Dataset',
                'ai_provider' => $aiProvider,
                'ai_model' => $aiResponse['model'],
                'context_found' => true,
                'relevant_chunks' => count($filteredResults),
                'ai_response' => $parsedResponse['parsed'] ?? $aiResponse['response'],
                'ai_response_raw' => $aiResponse['response'], // Keep original for debugging
                'ai_response_format' => $parsedResponse['format'],
                'context_chunks' => $this->formatContextChunks($filteredResults),
                'usage' => $aiResponse['usage'],
                'search_metadata' => [
                    'total_results_before_filter' => count($searchResults),
                    'similarity_threshold' => $threshold,
                    'filtered_results' => count($filteredResults)
                ],
                'prompt_metadata' => [
                    'prompt_template' => $options['prompt_template'] ?? 'default',
                    'context_length' => strlen($context),
                    'final_prompt_length' => strlen($prompt)
                ]
            ];

        } catch (Exception $e) {
            Log::error("RAG query failed", [
                'dataset_id' => $dataset->id,
                'query' => $query,
                'ai_provider' => $aiProvider,
                'error' => $e->getMessage()
            ]);

            throw new Exception("RAG query failed: " . $e->getMessage());
        }
    }

    /**
     * Parse AI response to extract structured data if possible.
     */
    private function parseAIResponse(string $response, array $options = []): array
    {
        $trimmedResponse = trim($response);
        
        // Check if response looks like JSON
        if ((str_starts_with($trimmedResponse, '{') && str_ends_with($trimmedResponse, '}')) ||
            (str_starts_with($trimmedResponse, '[') && str_ends_with($trimmedResponse, ']'))) {
            
            try {
                $decoded = json_decode($trimmedResponse, true, 512, JSON_THROW_ON_ERROR);
                
                Log::info("Successfully parsed AI response as JSON", [
                    'original_length' => strlen($response),
                    'parsed_keys' => is_array($decoded) ? array_keys($decoded) : 'not_object'
                ]);
                
                return [
                    'is_parsed' => true,
                    'format' => 'json',
                    'parsed' => $decoded
                ];
            } catch (\JsonException $e) {
                Log::warning("Failed to parse AI response as JSON", [
                    'error' => $e->getMessage(),
                    'response_preview' => substr($trimmedResponse, 0, 200)
                ]);
            }
        }

        // Check for other structured formats (could be extended)
        if (str_contains($response, '|') && str_contains($response, "\n")) {
            // Might be a table format
            return [
                'is_parsed' => false,
                'format' => 'table',
                'parsed' => null
            ];
        }

        // Default to plain text
        return [
            'is_parsed' => false,
            'format' => 'text',
            'parsed' => null
        ];
    }

    /**
     * Build context string from search results.
     */
    private function buildContext(array $searchResults, array $options = []): string
    {
        $maxContextLength = $options['max_context_length'] ?? 8000;
        $includeMetadata = $options['include_metadata'] ?? true;
        $contextFormat = $options['context_format'] ?? 'simple';

        $context = "";
        $currentLength = 0;

        foreach ($searchResults as $index => $result) {
            $score = $result['score'] ?? 0;

            // Format each chunk based on context format
            $chunkText = match($contextFormat) {
                'detailed' => $this->formatDetailedChunk($result, $score, $index + 1),
                'metadata_rich' => $this->formatMetadataRichChunk($result, $score, $index + 1),
                'simple' => $this->formatSimpleChunk($result, $score, $index + 1),
                default => $this->formatSimpleChunk($result, $score, $index + 1)
            };

            // Check if adding this chunk would exceed max context length
            if ($currentLength + strlen($chunkText) > $maxContextLength) {
                Log::info("Context truncated due to length limit", [
                    'max_length' => $maxContextLength,
                    'chunks_included' => $index,
                    'chunks_total' => count($searchResults)
                ]);
                break;
            }

            $context .= $chunkText . "\n\n";
            $currentLength += strlen($chunkText) + 2; // +2 for newlines
        }

        return trim($context);
    }

    /**
     * Format chunk in simple format.
     */
    private function formatSimpleChunk(array $result, float $score, int $index): string
    {
        $text = $result['text'] ?? 'No content available';
        return "[$index] {$text}";
    }

    /**
     * Format chunk with detailed information.
     */
    private function formatDetailedChunk(array $result, float $score, int $index): string
    {
        $text = $result['text'] ?? 'No content available';
        $metadata = $result['metadata'] ?? [];
        
        $output = "[$index] (Relevance: " . round($score * 100, 1) . "%)\n";
        
        if (!empty($metadata)) {
            $metadataStr = [];
            foreach ($metadata as $key => $value) {
                if (!in_array($key, ['chunk_index', 'original_index', 'total_chunks'])) {
                    $metadataStr[] = "{$key}: {$value}";
                }
            }
            if (!empty($metadataStr)) {
                $output .= "Metadata: " . implode(', ', $metadataStr) . "\n";
            }
        }
        
        $output .= "Content: {$text}";
        return $output;
    }

    /**
     * Format chunk with rich metadata.
     */
    private function formatMetadataRichChunk(array $result, float $score, int $index): string
    {
        $text = $result['text'] ?? 'No content available';
        $metadata = $result['metadata'] ?? [];
        
        // Extract common metadata fields
        $source = $metadata['file_name'] ?? $metadata['source_file'] ?? 'Unknown source';
        $chunkId = $metadata['chunk_identifier'] ?? $result['id'] ?? "chunk_{$index}";
        
        return "### Source {$index}: {$source}\n**Chunk ID:** {$chunkId}\n**Relevance:** " . round($score * 100, 1) . "%\n**Content:**\n{$text}";
    }

    /**
     * Build the final prompt for AI.
     */
    private function buildPrompt(string $query, string $context, array $options = []): string
    {
        $template = $options['prompt_template'] ?? 'default';
        $customInstructions = $options['instructions'] ?? null;
        $responseFormat = $options['response_format'] ?? null;

        // Use custom prompt if provided
        if (isset($options['custom_prompt'])) {
            return str_replace(
                ['{{query}}', '{{context}}'],
                [$query, $context],
                $options['custom_prompt']
            );
        }

        // Build prompt based on template
        return match($template) {
            'analysis' => $this->buildAnalysisPrompt($query, $context, $customInstructions, $responseFormat),
            'qa' => $this->buildQAPrompt($query, $context, $customInstructions, $responseFormat),
            'summary' => $this->buildSummaryPrompt($query, $context, $customInstructions, $responseFormat),
            'structured' => $this->buildStructuredPrompt($query, $context, $customInstructions, $responseFormat),
            default => $this->buildDefaultPrompt($query, $context, $customInstructions, $responseFormat)
        };
    }

    /**
     * Build default prompt template.
     */
    private function buildDefaultPrompt(string $query, string $context, ?string $instructions, ?string $format): string
    {
        $prompt = "### Instruction:\n";
        $prompt .= $instructions ?? "Answer the user's question based on the provided context from the dataset. Be accurate and cite relevant information when possible.";
        $prompt .= "\n\n### User Question:\n{$query}\n\n";
        $prompt .= "### Relevant Context:\n{$context}\n\n";
        $prompt .= "### Response:\n";
        
        if ($format) {
            $prompt .= "Please format your response as follows:\n{$format}\n\n";
        }
        
        return $prompt;
    }

    /**
     * Build Q&A prompt template.
     */
    private function buildQAPrompt(string $query, string $context, ?string $instructions, ?string $format): string
    {
        return <<<PROMPT
### Instruction:
{$instructions} Answer the question directly and concisely based on the provided context.

### Question:
{$query}

### Context:
{$context}

### Answer:
PROMPT;
    }

    /**
     * Build analysis prompt template.
     */
    private function buildAnalysisPrompt(string $query, string $context, ?string $instructions, ?string $format): string
    {
        return <<<PROMPT
### Instruction:
{$instructions} Analyze the provided context in relation to the user's request. Provide insights, patterns, and detailed explanations.

### Analysis Request:
{$query}

### Data to Analyze:
{$context}

### Analysis:
PROMPT;
    }

    /**
     * Build summary prompt template.
     */
    private function buildSummaryPrompt(string $query, string $context, ?string $instructions, ?string $format): string
    {
        return <<<PROMPT
### Instruction:
{$instructions} Summarize the relevant information from the context based on the user's request.

### Summary Request:
{$query}

### Content to Summarize:
{$context}

### Summary:
PROMPT;
    }

    /**
     * Build structured prompt template (like your biblical example).
     */
    private function buildStructuredPrompt(string $query, string $context, ?string $instructions, ?string $format): string
    {
        $formatInstructions = $format ?: "Return your response in a structured format (JSON, table, or organized list).";
        
        return <<<PROMPT
### Instruction:
{$instructions} Process the provided context and respond to the user's request in a structured format.

### Request:
{$query}

### Context:
{$context}

### Response Format:
{$formatInstructions}

### Structured Response:
PROMPT;
    }

    /**
     * Format context chunks for response.
     */
    private function formatContextChunks(array $searchResults): array
    {
        return array_map(function ($result, $index) {
            return [
                'index' => $index + 1,
                'score' => round($result['score'] ?? 0, 4),
                'text' => $result['text'] ?? '',
                'metadata' => $result['metadata'] ?? [],
                'chunk_id' => $result['id'] ?? null
            ];
        }, $searchResults, array_keys($searchResults));
    }
}
