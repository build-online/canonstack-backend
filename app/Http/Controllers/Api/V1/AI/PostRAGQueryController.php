<?php

namespace App\Http\Controllers\Api\V1\AI;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\AI\RAGQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PostRAGQueryController extends Controller
{
    private RAGQueryService $ragService;

    public function __construct(RAGQueryService $ragService)
    {
        $this->ragService = $ragService;
    }

    /**
     * Query dataset using RAG with AI response generation.
     */
    public function __invoke(Request $request, string $datasetUuid): JsonResponse
    {
        try {
            // Find the dataset
            $dataset = Dataset::where('uuid', $datasetUuid)
                ->with('embedding')
                ->firstOrFail();

            // Validate request
            $validated = $request->validate([
                'query' => 'required|string|min:3|max:2000',
                'ai_provider' => 'sometimes|string|in:openai,claude',
                'model' => 'sometimes|string',
                'temperature' => 'sometimes|numeric|min:0|max:2',
                'max_tokens' => 'sometimes|integer|min:50|max:8000',
                
                // Search options
                'search_limit' => 'sometimes|integer|min:1|max:50',
                'search_threshold' => 'sometimes|numeric|min:0|max:1',
                
                // Context options
                'max_context_length' => 'sometimes|integer|min:500|max:16000',
                'context_format' => 'sometimes|string|in:simple,detailed,metadata_rich',
                'include_metadata' => 'sometimes|boolean',
                
                // Prompt options
                'prompt_template' => 'sometimes|string|in:default,qa,analysis,summary,structured',
                'instructions' => 'sometimes|string|max:1000',
                'response_format' => 'sometimes|string|max:1000',
                'custom_prompt' => 'sometimes|string|max:4000',
            ]);

            // Set defaults
            $aiProvider = $validated['ai_provider'] ?? 'openai';
            $query = $validated['query'];

            // Prepare options
            $options = array_filter([
                'model' => $validated['model'] ?? null,
                'temperature' => $validated['temperature'] ?? 0.7,
                'max_tokens' => $validated['max_tokens'] ?? null,
                'search_limit' => $validated['search_limit'] ?? 10,
                'search_threshold' => $validated['search_threshold'] ?? 0.7,
                'max_context_length' => $validated['max_context_length'] ?? 8000,
                'context_format' => $validated['context_format'] ?? 'simple',
                'include_metadata' => $validated['include_metadata'] ?? true,
                'prompt_template' => $validated['prompt_template'] ?? 'default',
                'instructions' => $validated['instructions'] ?? null,
                'response_format' => $validated['response_format'] ?? null,
                'custom_prompt' => $validated['custom_prompt'] ?? null,
            ], fn($value) => $value !== null);

            // Execute RAG query
            $result = $this->ragService->queryDataset($dataset, $query, $aiProvider, $options);

            return response()->sendResponse(
                $result,
                null,
                'RAG query completed successfully.'
            );

        } catch (Exception $e) {
            // Handle specific error types
            if (str_contains($e->getMessage(), 'not found')) {
                return response()->sendError(
                    'Dataset not found.',
                    404
                );
            }

            if (str_contains($e->getMessage(), 'embeddings are not available')) {
                return response()->sendError(
                    'Dataset embeddings are not available. Please generate embeddings first using POST /api/v1/embeddings/datasets/{uuid}',
                    400
                );
            }

            if (str_contains($e->getMessage(), 'not configured')) {
                return response()->sendError(
                    'AI provider not configured. Please check your environment configuration.',
                    503
                );
            }

            if (str_contains($e->getMessage(), 'API error')) {
                return response()->sendError(
                    'AI provider API error: ' . $e->getMessage(),
                    502
                );
            }

            return response()->sendError(
                'RAG query failed: ' . $e->getMessage(),
                500
            );
        }
    }
}