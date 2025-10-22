<?php

namespace App\Services\Api\V1\AI;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QueryEnhancementService
{
    /**
     * Enhance a user query using AI based on dataset structure.
     * This improves search relevance by considering metadata fields and structure.
     */
    public function enhanceQuery(string $originalQuery, string $structureDescription): array
    {
        $apiKey = config('services.openai.api_key');
        
        if (empty($apiKey)) {
            Log::warning("OpenAI API key not configured - skipping query enhancement");
            return [
                'enhanced_query' => $originalQuery,
                'original_query' => $originalQuery,
                'enhancement_used' => false,
                'reason' => 'OpenAI API key not configured'
            ];
        }

        try {
            $systemPrompt = $this->buildSystemPrompt();
            $userPrompt = $this->buildUserPrompt($originalQuery, $structureDescription);

            Log::info("Enhancing query with AI", [
                'original_query' => $originalQuery,
                'structure_length' => strlen($structureDescription)
            ]);

            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini', // Fast and cost-effective for query enhancement
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt]
                    ],
                    'temperature' => 0.3, // Low temperature for consistent, focused enhancements
                    'max_tokens' => 500,
                ]);

            if (!$response->successful()) {
                throw new Exception("OpenAI API request failed: " . $response->body());
            }

            $data = $response->json();
            $enhancedQuery = trim($data['choices'][0]['message']['content'] ?? '');

            if (empty($enhancedQuery)) {
                throw new Exception("Empty response from OpenAI");
            }

            Log::info("Query enhanced successfully", [
                'original_query' => $originalQuery,
                'enhanced_query' => $enhancedQuery,
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ]);

            return [
                'enhanced_query' => $enhancedQuery,
                'original_query' => $originalQuery,
                'enhancement_used' => true,
                'tokens_used' => $data['usage'] ?? null,
                'model_used' => 'gpt-4o-mini'
            ];

        } catch (Exception $e) {
            Log::warning("Query enhancement failed - using original query", [
                'original_query' => $originalQuery,
                'error' => $e->getMessage()
            ]);

            // Fallback to original query if enhancement fails
            return [
                'enhanced_query' => $originalQuery,
                'original_query' => $originalQuery,
                'enhancement_used' => false,
                'reason' => 'Enhancement failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build system prompt for query enhancement.
     */
    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are an expert query optimization assistant for RAG (Retrieval-Augmented Generation) systems. Your task is to enhance user queries to improve vector search results in Qdrant.

**Your Responsibilities:**
1. Analyze the user's original query and the dataset structure
2. Identify key concepts, entities, and relationships the user is asking about
3. Enrich the query with relevant terms, synonyms, and contextual information
4. Consider metadata fields that could be relevant to the search
5. Maintain the user's original intent while expanding semantic coverage
6. Output ONLY the enhanced query text - no explanations, no markdown, no formatting

**Guidelines:**
- Expand abbreviated terms (e.g., "NT" → "New Testament")
- Add relevant synonyms and related concepts
- Consider metadata fields mentioned in the structure (dates, categories, tags, etc.)
- Keep the enhanced query natural and readable
- Preserve the original question type (who, what, where, when, why, how)
- Focus on semantic richness for better vector similarity matching
- DO NOT add generic filler words or phrases
- DO NOT explain your reasoning - just output the enhanced query

**Output Format:**
Return ONLY the enhanced query as plain text. Nothing else.
PROMPT;
    }

    /**
     * Build user prompt with query and structure.
     */
    private function buildUserPrompt(string $originalQuery, string $structureDescription): string
    {
        return <<<PROMPT
**Original User Query:**
{$originalQuery}

**Dataset Structure:**
{$structureDescription}

**Task:**
Enhance the query above to improve vector search results. Consider the dataset structure and metadata fields. Output ONLY the enhanced query text.
PROMPT;
    }
}

