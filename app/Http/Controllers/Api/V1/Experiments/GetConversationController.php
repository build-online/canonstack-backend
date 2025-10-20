<?php

namespace App\Http\Controllers\Api\V1\Experiments;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\ExperimentConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class GetConversationController extends Controller
{
    private ExperimentConversationService $conversationService;

    public function __construct(ExperimentConversationService $conversationService)
    {
        $this->conversationService = $conversationService;
    }

    /**
     * Get conversation history for a specific variant.
     */
    public function __invoke(Request $request, string $uuid, string $variant): JsonResponse
    {
        try {
            // Validate variant
            if (!in_array($variant, ['simple', 'complex'])) {
                return response()->sendError('Invalid variant. Must be "simple" or "complex".', 400);
            }

            // Find dataset and embedding
            $dataset = Dataset::where('uuid', $uuid)->firstOrFail();
            $embedding = $dataset->getEmbeddingByVariant($variant);

            if (!$embedding) {
                return response()->sendError(ucfirst($variant) . ' embedding not found for this dataset.', 404);
            }

            // Get authenticated user
            $user = $request->user();

            // Get conversation history
            $history = $this->conversationService->getConversationHistory($user, $embedding, $variant);

            if (!$history) {
                return response()->sendResponse([
                    'conversation_id' => null,
                    'variant' => $variant,
                    'messages' => [],
                    'total_messages' => 0,
                    'total_tokens' => 0,
                ], null, 'No conversation history found.');
            }

            return response()->sendResponse(
                $history,
                null,
                'Conversation history retrieved successfully.'
            );

        } catch (Exception $e) {
            return response()->sendError(
                'Failed to retrieve conversation history: ' . $e->getMessage(),
                500
            );
        }
    }
}

