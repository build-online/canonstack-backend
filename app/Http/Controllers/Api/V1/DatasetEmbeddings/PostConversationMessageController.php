<?php

namespace App\Http\Controllers\Api\V1\DatasetEmbeddings;

use App\Http\Controllers\Controller;
use App\Models\DatasetEmbedding;
use App\Services\Api\V1\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class PostConversationMessageController extends Controller
{
    private ConversationService $conversationService;

    public function __construct(ConversationService $conversationService)
    {
        $this->conversationService = $conversationService;
    }

    /**
     * Send a message in the conversation for this dataset embedding.
     */
    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        try {
            // Find dataset embedding
            $datasetEmbedding = DatasetEmbedding::where('uuid', $uuid)->firstOrFail();

            // Get authenticated user
            $user = $request->user();

            // Validate request
            $validator = Validator::make($request->all(), [
                // Message content
                'message' => 'required|string|max:10000',
                
                // AI Provider settings
                'ai_provider' => 'sometimes|string|in:openai,claude',
                'model' => 'sometimes|string|max:100',
                'temperature' => 'sometimes|numeric|min:0|max:2',
                'max_tokens' => 'sometimes|integer|min:1|max:8000',
                
                // Search settings
                'search_limit' => 'sometimes|integer|min:1|max:50',
                'search_threshold' => 'sometimes|numeric|min:0|max:1',
                'search_filter' => 'sometimes|array',
                
                // Context settings
                'context_format' => 'sometimes|string|in:simple,detailed,metadata_rich',
                'max_context_length' => 'sometimes|integer|min:100|max:20000',
                'include_metadata' => 'sometimes|boolean',
                
                // Prompt settings
                'prompt_template' => 'sometimes|string|in:default,qa,analysis,summary,structured',
                'instructions' => 'sometimes|string|max:2000',
                'response_format' => 'sometimes|string|max:1000',
                'custom_prompt' => 'sometimes|string|max:5000',
            ]);

            if ($validator->fails()) {
                return response()->sendError('Validation failed', 400, $validator->errors());
            }

            $validated = $validator->validated();

            // Prepare options for RAG service
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
                'custom_prompt' => $validated['custom_prompt'] ?? null,
                
                // AI options
                'model' => $validated['model'] ?? null,
                'temperature' => $validated['temperature'] ?? 0.7,
                'max_tokens' => $validated['max_tokens'] ?? 2000,
            ];

            Log::info("Conversation message sent", [
                'dataset_embedding_uuid' => $uuid,
                'user_id' => $user->id,
                'message_length' => strlen($validated['message']),
                'ai_provider' => $validated['ai_provider'] ?? 'openai'
            ]);

            // Process the conversation message
            $result = $this->conversationService->processChatMessage(
                $user,
                $datasetEmbedding,
                $validated['message'],
                $validated['ai_provider'] ?? 'openai',
                $options
            );

            return response()->sendResponse(
                array_merge($result, [
                    'dataset_embedding' => [
                        'uuid' => $datasetEmbedding->uuid,
                        'status' => $datasetEmbedding->status,
                        'embedding_model' => $datasetEmbedding->embedding_model,
                    ],
                    'user_message' => $validated['message'],
                    'ai_provider' => $validated['ai_provider'] ?? 'openai',
                ]),
                null,
                'Conversation message processed successfully.'
            );

        } catch (ModelNotFoundException $e) {
            return response()->sendError('Dataset embedding not found.', 404);

        } catch (Exception $e) {
            Log::error("Conversation message failed", [
                'dataset_embedding_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Check for specific error types
            if (str_contains($e->getMessage(), 'embeddings are not available')) {
                return response()->sendError('Dataset embeddings are not available or not completed. Please generate embeddings first.', 400);
            }

            if (str_contains($e->getMessage(), 'Qdrant')) {
                return response()->sendError('The answer was not generated due to a search service error: ' . $e->getMessage(), 503);
            }

            return response()->sendError('Failed to process conversation message: ' . $e->getMessage(), 500);
        }
    }
}
