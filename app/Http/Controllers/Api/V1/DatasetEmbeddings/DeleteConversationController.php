<?php

namespace App\Http\Controllers\Api\V1\DatasetEmbeddings;

use App\Http\Controllers\Controller;
use App\Models\DatasetEmbedding;
use App\Services\Api\V1\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DeleteConversationController extends Controller
{
    private ConversationService $conversationService;

    public function __construct(ConversationService $conversationService)
    {
        $this->conversationService = $conversationService;
    }

    /**
     * Clear/delete conversation for this dataset embedding.
     */
    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        try {
            // Find dataset embedding
            $datasetEmbedding = DatasetEmbedding::where('uuid', $uuid)->firstOrFail();

            // Get authenticated user
            $user = $request->user();

            Log::info("Clearing conversation", [
                'dataset_embedding_uuid' => $uuid,
                'user_id' => $user->id
            ]);

            // Clear the conversation
            $cleared = $this->conversationService->clearConversation($user, $datasetEmbedding);

            if (!$cleared) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'conversation_existed' => false,
                        'dataset_embedding' => [
                            'uuid' => $datasetEmbedding->uuid,
                            'status' => $datasetEmbedding->status,
                            'embedding_model' => $datasetEmbedding->embedding_model,
                        ],
                    ],
                    'message' => 'No conversation found to clear.'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation_existed' => true,
                    'cleared' => true,
                    'dataset_embedding' => [
                        'uuid' => $datasetEmbedding->uuid,
                        'status' => $datasetEmbedding->status,
                        'embedding_model' => $datasetEmbedding->embedding_model,
                    ],
                ],
                'message' => 'Conversation cleared successfully.'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dataset embedding not found.',
                'errors' => []
            ], 404);

        } catch (\Exception $e) {
            Log::error("Failed to clear conversation", [
                'dataset_embedding_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear conversation: ' . $e->getMessage(),
                'errors' => []
            ], 500);
        }
    }
}
