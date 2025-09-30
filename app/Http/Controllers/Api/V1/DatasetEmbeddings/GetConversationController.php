<?php

namespace App\Http\Controllers\Api\V1\DatasetEmbeddings;

use App\Http\Controllers\Controller;
use App\Models\DatasetEmbedding;
use App\Services\Api\V1\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class GetConversationController extends Controller
{
    private ConversationService $conversationService;

    public function __construct(ConversationService $conversationService)
    {
        $this->conversationService = $conversationService;
    }

    /**
     * Get conversation history for this dataset embedding.
     */
    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        try {
            // Find dataset embedding
            $datasetEmbedding = DatasetEmbedding::where('uuid', $uuid)->firstOrFail();

            // Get authenticated user
            $user = $request->user();

            Log::info("Getting conversation history", [
                'dataset_embedding_uuid' => $uuid,
                'user_id' => $user->id
            ]);

            // Get conversation history
            $conversationHistory = $this->conversationService->getConversationHistory($user, $datasetEmbedding);

            if (!$conversationHistory) {
                return response()->sendResponse(
                    [
                        'exists' => false,
                        'conversation_id' => null,
                        'messages' => [],
                        'total_messages' => 0,
                        'total_tokens' => 0,
                        'should_suggest_new_conversation' => false,
                        'dataset_embedding' => [
                            'uuid' => $datasetEmbedding->uuid,
                            'status' => $datasetEmbedding->status,
                            'embedding_model' => $datasetEmbedding->embedding_model,
                        ],
                    ],
                    null,
                    'No conversation found for this dataset embedding.'
                );
            }

            return response()->sendResponse(
                array_merge($conversationHistory, [
                    'exists' => true,
                    'dataset_embedding' => [
                        'uuid' => $datasetEmbedding->uuid,
                        'status' => $datasetEmbedding->status,
                        'embedding_model' => $datasetEmbedding->embedding_model,
                    ],
                ]),
                null,
                'Conversation history retrieved successfully.'
            );

        } catch (ModelNotFoundException $e) {
            return response()->sendError('Dataset embedding not found.', 404);

        } catch (Exception $e) {
            Log::error("Failed to get conversation history", [
                'dataset_embedding_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->sendError('Failed to retrieve conversation history: ' . $e->getMessage(), 500);
        }
    }
}
