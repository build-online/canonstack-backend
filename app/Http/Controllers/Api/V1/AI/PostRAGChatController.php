<?php

namespace App\Http\Controllers\Api\V1\AI;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\AI\RAGQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class PostRAGChatController extends Controller
{
    private RAGQueryService $ragService;

    public function __construct(RAGQueryService $ragService)
    {
        $this->ragService = $ragService;
    }

    /**
     * Handle RAG chat query with combined prompt and query in a single field.
     */
    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        try {
            // Find dataset
            $dataset = Dataset::where('uuid', $uuid)->firstOrFail();

            // Validate request
            $validator = Validator::make($request->all(), [
                // Single combined field for chat message
                'message' => 'required|string|max:10000',
                
                // AI Provider settings (same as original endpoint)
                'ai_provider' => 'sometimes|string|in:openai,claude',
                'model' => 'sometimes|string|max:100',
                'temperature' => 'sometimes|numeric|min:0|max:2',
                'max_tokens' => 'sometimes|integer|min:1|max:8000',
                
                // Search settings (same as original endpoint)
                'search_limit' => 'sometimes|integer|min:1|max:50',
                'search_threshold' => 'sometimes|numeric|min:0|max:1',
                'search_filter' => 'sometimes|array',
                
                // Context settings (same as original endpoint)
                'context_format' => 'sometimes|string|in:simple,detailed,metadata_rich',
                'max_context_length' => 'sometimes|integer|min:100|max:20000',
                'include_metadata' => 'sometimes|boolean',
                
                // Prompt settings (same as original endpoint)
                'prompt_template' => 'sometimes|string|in:default,qa,analysis,summary,structured',
                'instructions' => 'sometimes|string|max:2000',
                'response_format' => 'sometimes|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->sendError('Validation failed', 400, $validator->errors());
            }

            $validated = $validator->validated();

            // Extract the message
            $message = $validated['message'];
            
            // Parse the message to extract query and determine if it contains custom instructions
            $parsedMessage = $this->parseMessage($message);

            // Prepare options (same structure as original endpoint)
            $options = [
                // Search options
                'search_limit' => $validated['search_limit'] ?? 10,
                'search_threshold' => $validated['search_threshold'] ?? 0.1,
                'search_filter' => $validated['search_filter'] ?? null,
                
                // Context options
                'context_format' => $validated['context_format'] ?? 'simple',
                'max_context_length' => $validated['max_context_length'] ?? 8000,
                'include_metadata' => $validated['include_metadata'] ?? true,
                
                // Prompt options
                'prompt_template' => $validated['prompt_template'] ?? 'default',
                'instructions' => $validated['instructions'] ?? null,
                'response_format' => $validated['response_format'] ?? null,
                
                // AI options
                'model' => $validated['model'] ?? null,
                'temperature' => $validated['temperature'] ?? 0.7,
                'max_tokens' => $validated['max_tokens'] ?? 2000,
            ];

            // If the message contains a custom prompt structure, use it
            if ($parsedMessage['has_custom_prompt']) {
                $options['custom_prompt'] = $parsedMessage['custom_prompt'];
                $query = $parsedMessage['query'];
            } else {
                // Use the entire message as the query
                $query = $message;
            }

            Log::info("RAG chat query initiated", [
                'dataset_uuid' => $uuid,
                'message_length' => strlen($message),
                'has_custom_prompt' => $parsedMessage['has_custom_prompt'],
                'query_length' => strlen($query),
                'ai_provider' => $validated['ai_provider'] ?? 'openai'
            ]);

            // Execute RAG query
            $result = $this->ragService->queryDataset(
                $dataset,
                $query,
                $validated['ai_provider'] ?? 'openai',
                $options
            );

            return response()->sendResponse(
                array_merge($result, [
                    'original_message' => $message,
                    'parsed_query' => $query,
                    'message_type' => $parsedMessage['has_custom_prompt'] ? 'structured' : 'simple'
                ]),
                null,
                'RAG chat query completed successfully.'
            );

        } catch (ModelNotFoundException $e) {
            return response()->sendError('Dataset not found.', 404);

        } catch (Exception $e) {
            Log::error("RAG chat query failed", [
                'dataset_uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->sendError('Failed to process RAG chat query: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Parse the message to detect if it contains custom prompt structure.
     */
    private function parseMessage(string $message): array
    {
        // Check if message contains structured prompt patterns
        $hasInstructionSection = str_contains($message, '### Instruction:') || str_contains($message, '##Instruction:');
        $hasContextPlaceholder = str_contains($message, '{{context}}') || str_contains($message, '{context}');
        $hasQueryPlaceholder = str_contains($message, '{{query}}') || str_contains($message, '{query}');
        
        // If it looks like a structured prompt
        if ($hasInstructionSection && ($hasContextPlaceholder || $hasQueryPlaceholder)) {
            // Try to extract the actual query from common patterns
            $query = $this->extractQueryFromStructuredMessage($message);
            
            return [
                'has_custom_prompt' => true,
                'custom_prompt' => $message,
                'query' => $query ?: 'Please analyze the provided context', // fallback
                'type' => 'structured'
            ];
        }

        // Check for simple prompt templates (like "Find cross-references for: Romans 3:23")
        if (preg_match('/(?:find|search|analyze|identify).*?(?:for|of|in):\s*(.+?)(?:\n|$)/i', $message, $matches)) {
            return [
                'has_custom_prompt' => false,
                'custom_prompt' => null,
                'query' => trim($matches[1]),
                'type' => 'extracted_query'
            ];
        }

        // Default: treat entire message as query
        return [
            'has_custom_prompt' => false,
            'custom_prompt' => null,
            'query' => $message,
            'type' => 'simple'
        ];
    }

    /**
     * Extract query from structured message.
     */
    private function extractQueryFromStructuredMessage(string $message): ?string
    {
        // Look for common patterns where the actual query might be specified
        $patterns = [
            // Standard query patterns
            '/(?:Query|Question|Input|Search|Request):\s*(.+?)(?:\n|###|##|$)/i',
            '/(?:User (?:Query|Question|Input)):\s*(.+?)(?:\n|###|##|$)/i',
            '/(?:Search (?:for|term|query)):\s*(.+?)(?:\n|###|##|$)/i',
            
            // Action-based patterns (more general)
            '/(?:Find|Search|Analyze|Identify|Explain|Summarize|Compare|List|Show|Tell me about).*?:\s*(.+?)(?:\n|###|##|$)/i',
            '/(?:What|How|Why|When|Where|Who).*?:\s*(.+?)(?:\n|###|##|$)/i',
            
            // Content-specific patterns (general)
            '/(?:Text|Content|Document|Data|Information|Topic|Subject):\s*(.+?)(?:\n|###|##|$)/i',
            '/(?:About|Regarding|Concerning):\s*(.+?)(?:\n|###|##|$)/i',
            
            // Research/analysis patterns
            '/(?:Research|Analysis|Study) (?:topic|subject|focus):\s*(.+?)(?:\n|###|##|$)/i',
            '/(?:Main|Primary|Key) (?:topic|subject|question):\s*(.+?)(?:\n|###|##|$)/i',
            
            // Simple colon patterns (catch-all)
            '/^([^:\n]+):\s*(.+?)(?:\n|###|##|$)/im',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                // For the simple colon pattern, use the second capture group
                $extracted = isset($matches[2]) ? trim($matches[2]) : trim($matches[1]);
                
                // Don't return placeholder text, template variables, or very short strings
                if (!str_contains($extracted, '{{') && 
                    !str_contains($extracted, '<') && 
                    !str_contains($extracted, '}}') &&
                    !str_contains($extracted, 'context') &&
                    !str_contains($extracted, 'query') &&
                    strlen($extracted) > 3 &&
                    !$this->isPlaceholderText($extracted)) {
                    return $extracted;
                }
            }
        }

        return null;
    }

    /**
     * Check if text appears to be placeholder content.
     */
    private function isPlaceholderText(string $text): bool
    {
        $placeholderPatterns = [
            '/^<[^>]+>$/',  // <placeholder>
            '/^\[[^\]]+\]$/',  // [placeholder]
            '/^your .+/i',  // "your question here"
            '/^enter .+/i',  // "enter your query"
            '/^type .+/i',   // "type your question"
            '/^example/i',   // "example query"
            '/^sample/i',    // "sample text"
            '/^placeholder/i', // "placeholder text"
        ];

        foreach ($placeholderPatterns as $pattern) {
            if (preg_match($pattern, trim($text))) {
                return true;
            }
        }

        return false;
    }
}
