<?php

namespace App\Http\Controllers\Api\V1\Experiments;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Models\ExperimentConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class DeleteConversationController extends Controller
{
    /**
     * Delete (soft delete) conversation for a specific variant.
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

            // Find conversation
            $conversation = ExperimentConversation::where('user_id', $user->id)
                ->where('dataset_embedding_id', $embedding->id)
                ->where('variant', $variant)
                ->first();

            if (!$conversation) {
                return response()->sendResponse([
                    'deleted' => false,
                    'message' => 'No conversation found to delete.'
                ], null, 'No conversation found.');
            }

            // Soft delete the conversation
            $conversation->delete();

            return response()->sendResponse([
                'deleted' => true,
                'conversation_id' => $conversation->id,
                'variant' => $variant,
            ], null, 'Conversation deleted successfully.');

        } catch (Exception $e) {
            return response()->sendError(
                'Failed to delete conversation: ' . $e->getMessage(),
                500
            );
        }
    }
}

