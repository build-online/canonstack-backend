<?php

namespace App\Http\Controllers\Api\V1\Experiments;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\ExperimentConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class PostSimpleChatController extends Controller
{
    private ExperimentConversationService $conversationService;

    public function __construct(ExperimentConversationService $conversationService)
    {
        $this->conversationService = $conversationService;
    }

    /**
     * Send a message in the simple chat for this dataset.
     */
    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        try {
            // Find dataset and load simple embedding
            $dataset = Dataset::where('uuid', $uuid)->with('repository')->firstOrFail();
            $embedding = $dataset->getEmbeddingByVariant('simple');

            if (!$embedding) {
                return response()->sendError('Simple embedding not found for this dataset.', 404);
            }

            if (!$embedding->isCompleted()) {
                return response()->sendError('Simple embedding is not completed yet. Please wait for generation to finish.', 403);
            }

            // Get authenticated user
            $user = $request->user();

            // Validate request
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:10000',
                'ai_provider' => 'sometimes|string|in:openai,claude',
                'model' => 'sometimes|string|max:100',
                'temperature' => 'sometimes|numeric|min:0|max:2',
                'max_tokens' => 'sometimes|integer|min:1|max:8000',
                'search_limit' => 'sometimes|integer|min:1|max:50',
                'search_threshold' => 'sometimes|numeric|min:0|max:1',
            ]);

            if ($validator->fails()) {
                return response()->sendError('Validation failed', 400, $validator->errors());
            }

            $validated = $validator->validated();

            // Prepare options for RAG service
            $options = [
                'search_limit' => $validated['search_limit'] ?? 10,
                'search_threshold' => $validated['search_threshold'] ?? 0.1,
                'model' => $validated['model'] ?? null,
                'temperature' => $validated['temperature'] ?? 0.7,
                'max_tokens' => $validated['max_tokens'] ?? 2000,
            ];

            Log::info("Simple chat message sent", [
                'dataset_uuid' => $uuid,
                'user_id' => $user->id,
                'message_length' => strlen($validated['message']),
                'ai_provider' => $validated['ai_provider'] ?? 'openai'
            ]);

            // Process the conversation message
            $result = $this->conversationService->processChatMessage(
                $user,
                $embedding,
                $validated['message'],
                'simple',
                $validated['ai_provider'] ?? 'openai',
                $options
            );

            return response()->sendResponse(
                $result,
                null,
                'Simple chat message processed successfully.'
            );

        } catch (Exception $e) {
            Log::error("Simple chat message failed", [
                'dataset_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            if (str_contains($e->getMessage(), 'embeddings are not available')) {
                return response()->sendError('Simple embeddings are not available. Please generate them first.', 400);
            }

            return response()->sendError('Failed to process simple chat message: ' . $e->getMessage(), 500);
        }
    }
}

