<?php

namespace App\Services\Api\V1\AI;

use App\Models\Dataset;
use App\Services\Api\V1\DatasetEmbeddingService;
use App\Services\Api\V1\AI\AIProviderService;
use App\Services\Api\V1\AI\QueryEnhancementService;
use Exception;
use Illuminate\Support\Facades\Log;

class RAGQueryService
{
    private DatasetEmbeddingService $embeddingService;
    private AIProviderService $aiService;
    private QueryEnhancementService $queryEnhancementService;

    public function __construct(
        DatasetEmbeddingService $embeddingService, 
        AIProviderService $aiService,
        QueryEnhancementService $queryEnhancementService
    ) {
        $this->embeddingService = $embeddingService;
        $this->aiService = $aiService;
        $this->queryEnhancementService = $queryEnhancementService;
    }

    /**
     * Query dataset using RAG with AI response generation.
     */
    public function queryDataset(
        Dataset $dataset,
        string $query,
        string $aiProvider = 'openai',
        array $options = [],
        ?string $variant = null
    ): array {
        // Validate that embeddings exist and are ready
        $embedding = $variant 
            ? $dataset->getEmbeddingByVariant($variant)
            : $dataset->embedding;
            
        if (!$embedding || !$embedding->isCompleted()) {
            $variantText = $variant ? " (variant: {$variant})" : '';
            throw new Exception("Dataset embeddings{$variantText} are not available or not completed. Please generate embeddings first.");
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
            'embedding_model' => $embedding->embedding_model,
            'variant' => $variant
        ]);

        try {
            // Step 0: Enhance query using AI for complex variant
            $enhancementResult = null;
            $searchQuery = $query; // Default to original query
            
            if ($variant === 'complex' && !empty($embedding->structure_description)) {
                Log::info("Enhancing query for complex variant", [
                    'dataset_id' => $dataset->id,
                    'original_query' => $query
                ]);
                
                $enhancementResult = $this->queryEnhancementService->enhanceQuery(
                    $query,
                    $embedding->structure_description
                );
                
                // Use enhanced query for search if enhancement was successful
                if ($enhancementResult['enhancement_used']) {
                    $searchQuery = $enhancementResult['enhanced_query'];
                    
                    Log::info("Query enhanced successfully", [
                        'dataset_id' => $dataset->id,
                        'original_query' => $query,
                        'enhanced_query' => $searchQuery
                    ]);
                }
            }
            
            // Step 1: Search for relevant context using vector similarity
            $searchResults = $this->embeddingService->search(
                $dataset,
                $searchQuery,
                $options['search_limit'] ?? 10,
                $options['search_filter'] ?? null,
                $variant
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

            // Check if the query is actually relevant to the dataset content
            $relevanceCheck = $this->checkQueryRelevance($query, $filteredResults, $dataset, $options);
            
            Log::info("Relevance check completed", [
                'dataset_id' => $dataset->id,
                'query' => $query,
                'total_search_results' => count($searchResults),
                'filtered_results' => count($filteredResults),
                'threshold_used' => $threshold,
                'relevance_check' => $relevanceCheck,
                'top_scores' => array_slice(array_map(fn($r) => round($r['score'] ?? 0, 4), $searchResults), 0, 5)
            ]);
            
            if (empty($filteredResults) || !$relevanceCheck['is_relevant']) {
                $reason = empty($filteredResults) ? 'No results above similarity threshold' : $relevanceCheck['reason'];
                
                Log::warning("No relevant context found for query", [
                    'dataset_id' => $dataset->id,
                    'query' => $query,
                    'threshold' => $threshold,
                    'total_results' => count($searchResults),
                    'filtered_results' => count($filteredResults),
                    'relevance_check' => $relevanceCheck,
                    'max_score' => count($searchResults) > 0 ? max(array_map(fn($r) => $r['score'] ?? 0, $searchResults)) : 0,
                    'all_scores' => array_map(fn($r) => round($r['score'] ?? 0, 4), $searchResults)
                ]);
                
                $noContextResponse = [
                    'query' => $query,
                    'dataset_id' => $dataset->uuid,
                    'ai_provider' => $aiProvider,
                    'context_found' => false,
                    'relevant_chunks' => count($filteredResults),
                    'ai_response' => null,
                    'message' => $relevanceCheck['message'] ?? 'No relevant context found in the dataset for this query.',
                    'debug_info' => [
                        'total_results_from_qdrant' => count($searchResults),
                        'similarity_threshold_used' => $threshold,
                        'relevance_check' => $relevanceCheck,
                        'max_score_found' => count($searchResults) > 0 ? max(array_map(fn($r) => $r['score'] ?? 0, $searchResults)) : 0,
                        'all_scores' => array_map(fn($r) => round($r['score'] ?? 0, 4), $searchResults),
                        'suggestion' => count($searchResults) > 0 ? 'Try lowering the search_threshold parameter or check if your query is relevant to the dataset content' : 'Check if embeddings were generated successfully'
                    ]
                ];
                
                // Add query enhancement information if available
                if ($enhancementResult !== null) {
                    $noContextResponse['query_enhancement'] = [
                        'enhanced_query' => $enhancementResult['enhanced_query'],
                        'original_query' => $enhancementResult['original_query'],
                        'enhancement_used' => $enhancementResult['enhancement_used'],
                        'model_used' => $enhancementResult['model_used'] ?? null,
                        'tokens_used' => $enhancementResult['tokens_used'] ?? null,
                    ];
                    $noContextResponse['enhanced_query'] = $enhancementResult['enhanced_query'];
                }
                
                return $noContextResponse;
            }

            // Step 2: Build context from search results
            $context = $this->buildContext($filteredResults, $options);

            // Step 3: Create prompt using template or custom format
            $prompt = $this->buildPrompt($query, $context, $options, $embedding);

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

            $response = [
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
            
            // Add query enhancement information for complex variant
            if ($enhancementResult !== null) {
                $response['query_enhancement'] = [
                    'enhanced_query' => $enhancementResult['enhanced_query'],
                    'original_query' => $enhancementResult['original_query'],
                    'enhancement_used' => $enhancementResult['enhancement_used'],
                    'model_used' => $enhancementResult['model_used'] ?? null,
                    'tokens_used' => $enhancementResult['tokens_used'] ?? null,
                ];
                
                // Also add enhanced_query at root level for easy access
                $response['enhanced_query'] = $enhancementResult['enhanced_query'];
            }
            
            return $response;

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
    private function buildPrompt(string $query, string $context, array $options = [], $embedding = null): string
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
            default => $this->buildDefaultPrompt($query, $context, $customInstructions, $responseFormat, $embedding)
        };
    }

    /**
     * Build default prompt template with strict guardrails.
     * If a custom system prompt is available from the embedding (complex variant), use it.
     */
    private function buildDefaultPrompt(string $query, string $context, ?string $instructions, ?string $format, $embedding = null): string
    {
        // Check if embedding has a custom system prompt (for complex variant)
        if ($embedding && !empty($embedding->system_prompt)) {
            $prompt = $embedding->system_prompt . "\n\n";
            
            if ($instructions) {
                $prompt .= "### Additional Instructions:\n";
                $prompt .= $instructions . "\n\n";
            }
            
            $prompt .= "### User Question:\n{$query}\n\n";
            $prompt .= "### Dataset Context:\n{$context}\n\n";
            
            if ($format) {
                $prompt .= "### Response Format:\n{$format}\n\n";
            }
            
            $prompt .= "### Your Response:\n";
            
            return $prompt;
        }
        
        $baseInstructions = $instructions ?? "Answer the user's question based ONLY on the provided context from the dataset. Be accurate and cite relevant information when possible.";
        
        $prompt = "### CRITICAL INSTRUCTIONS:\n";
        $prompt .= "You are a RAG (Retrieval-Augmented Generation) assistant. You must follow these rules:\n\n";
        $prompt .= "1. Answer questions confidently using the provided context from the dataset\n";
        $prompt .= "2. Make reasonable connections and interpretations based on the context provided\n";
        $prompt .= "3. If the context contains relevant information, provide a comprehensive answer\n";
        $prompt .= "4. Look for implicit connections and themes in the biblical text\n";
        $prompt .= "5. Only decline to answer if the context is completely unrelated to the question\n";
        $prompt .= "6. When biblical passages relate to the question, explain those connections clearly\n\n";
        
        $prompt .= "### Additional Instructions:\n";
        $prompt .= $baseInstructions . "\n\n";
        
        $prompt .= "### User Question:\n{$query}\n\n";
        $prompt .= "### Dataset Context:\n{$context}\n\n";
        
        if ($format) {
            $prompt .= "### Response Format:\n{$format}\n\n";
        }
        
        $prompt .= "### Your Response:\n";
        $prompt .= "Analyze the context carefully. If you find relevant biblical passages, verses, or themes that relate to the question, use them to provide a helpful answer. Look for connections between different passages and explain their significance.\n\n";
        
        return $prompt;
    }

    /**
     * Build Q&A prompt template.
     */
    private function buildQAPrompt(string $query, string $context, ?string $instructions, ?string $format): string
    {
        $baseInstructions = $instructions ?? "Answer the question directly and concisely based ONLY on the provided context.";
        
        return <<<PROMPT
### CRITICAL INSTRUCTIONS:
Answer the question confidently using the provided context. Look for relevant biblical passages, themes, and connections. Make reasonable interpretations and explain the significance of the passages. Only decline to answer if the context is completely unrelated to the question.

### Additional Instructions:
{$baseInstructions}

### Question:
{$query}

### Dataset Context:
{$context}

### Answer:
Based on the biblical context provided, here is my analysis:

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

    /**
     * Check if the query is actually relevant to the dataset content.
     * This helps prevent the AI from answering questions outside the dataset scope.
     */
    private function checkQueryRelevance(string $query, array $searchResults, Dataset $dataset, array $options = []): array
    {
        // If no search results, definitely not relevant
        if (empty($searchResults)) {
            return [
                'is_relevant' => false,
                'reason' => 'No search results found',
                'message' => 'No relevant content found in the dataset for this query.',
                'confidence' => 0.0
            ];
        }

        // Calculate relevance metrics
        $scores = array_map(fn($r) => $r['score'] ?? 0, $searchResults);
        $maxScore = max($scores);
        $avgScore = array_sum($scores) / count($scores);
        $highScoreCount = count(array_filter($scores, fn($s) => $s >= 0.6));
        
        // Define relevance thresholds (configurable via options)
        $minMaxScore = $options['relevance_min_max_score'] ?? 0.3; // Lowered to be less aggressive
        $minAvgScore = $options['relevance_min_avg_score'] ?? 0.2; // Lowered to be less aggressive  
        $minHighScoreCount = $options['relevance_min_high_score_count'] ?? 0; // Allow queries without high-scoring chunks
        
        // Check for modern technology keywords that are unlikely to be in historical datasets
        $modernTechKeywords = [
            'cryptocurrency', 'blockchain', 'bitcoin', 'ethereum', 'crypto',
            'quantum computing', 'quantum algorithm', 'quantum cryptography',
            'artificial intelligence', 'machine learning', 'deep learning', 'AI', 'ML',
            'internet', 'website', 'email', 'smartphone', 'computer', 'software',
            'social media', 'facebook', 'twitter', 'instagram', 'youtube',
            'cloud computing', 'aws', 'azure', 'google cloud',
            'virtual reality', 'augmented reality', 'VR', 'AR',
            'robotics', 'automation', 'IoT', 'internet of things',
            'cybersecurity', 'hacking', 'malware', 'virus',
            'streaming', 'netflix', 'spotify', 'uber', 'airbnb'
        ];
        
        $queryLower = strtolower($query);
        $containsModernTech = false;
        foreach ($modernTechKeywords as $keyword) {
            if (str_contains($queryLower, strtolower($keyword))) {
                $containsModernTech = true;
                break;
            }
        }
        
        // Determine dataset type for context-specific checks
        $datasetName = strtolower($dataset->title ?? '');
        $isReligiousDataset = str_contains($datasetName, 'bible') || 
                             str_contains($datasetName, 'biblical') || 
                             str_contains($datasetName, 'christian') || 
                             str_contains($datasetName, 'religious') ||
                             str_contains($datasetName, 'cross-reference');
        
        // Apply stricter checks for historical/religious datasets with modern tech queries
        if ($isReligiousDataset && $containsModernTech) {
            return [
                'is_relevant' => false,
                'reason' => 'Modern technology query on historical/religious dataset',
                'message' => 'This query appears to be about modern technology concepts that would not be found in this historical/religious dataset.',
                'confidence' => 0.0,
                'detected_modern_tech' => true,
                'dataset_type' => 'historical/religious'
            ];
        }
        
        // For religious datasets, be more lenient with biblical references
        if ($isReligiousDataset) {
            $queryLower = strtolower($query);
            $biblicalPatterns = [
                '/\b\d*\s*[a-z]+\s+\d+:\d+/', // "Romans 3:23", "1 John 4:16", etc.
                '/\b(genesis|exodus|leviticus|numbers|deuteronomy|joshua|judges|ruth|samuel|kings|chronicles|ezra|nehemiah|esther|job|psalm|proverbs|ecclesiastes|song|isaiah|jeremiah|lamentations|ezekiel|daniel|hosea|joel|amos|obadiah|jonah|micah|nahum|habakkuk|zephaniah|haggai|zechariah|malachi|matthew|mark|luke|john|acts|romans|corinthians|galatians|ephesians|philippians|colossians|thessalonians|timothy|titus|philemon|hebrews|james|peter|jude|revelation)\b/',
                '/\b(jesus|christ|god|lord|holy spirit|bible|scripture|sin|salvation|grace|faith|love|hope|prayer|worship)\b/'
            ];
            
            foreach ($biblicalPatterns as $pattern) {
                if (preg_match($pattern, $queryLower)) {
                    // Lower thresholds for biblical queries
                    $minMaxScore = 0.2;
                    $minAvgScore = 0.15;
                    $minHighScoreCount = 0;
                    break;
                }
            }
        }
        
        // Check relevance based on similarity scores
        $isRelevant = ($maxScore >= $minMaxScore) && 
                     ($avgScore >= $minAvgScore) && 
                     ($highScoreCount >= $minHighScoreCount);
        
        if (!$isRelevant) {
            $reason = [];
            if ($maxScore < $minMaxScore) $reason[] = "max similarity score too low ({$maxScore} < {$minMaxScore})";
            if ($avgScore < $minAvgScore) $reason[] = "average similarity score too low ({$avgScore} < {$minAvgScore})";
            if ($highScoreCount < $minHighScoreCount) $reason[] = "insufficient high-relevance chunks ({$highScoreCount} < {$minHighScoreCount})";
            
            return [
                'is_relevant' => false,
                'reason' => 'Low relevance scores: ' . implode(', ', $reason),
                'message' => 'The query does not appear to be sufficiently related to the content in this dataset.',
                'confidence' => $maxScore,
                'metrics' => [
                    'max_score' => $maxScore,
                    'avg_score' => $avgScore,
                    'high_score_count' => $highScoreCount,
                    'total_results' => count($searchResults)
                ]
            ];
        }
        
        return [
            'is_relevant' => true,
            'reason' => 'Query appears relevant to dataset content',
            'message' => null,
            'confidence' => $maxScore,
            'metrics' => [
                'max_score' => $maxScore,
                'avg_score' => $avgScore,
                'high_score_count' => $highScoreCount,
                'total_results' => count($searchResults)
            ]
        ];
    }
}
