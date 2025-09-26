<?php

namespace App\Http\Controllers\Api\V1\Comments;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Exception;

class DeleteCommentController extends Controller
{
    /**
     * Delete a comment.
     */
    public function __invoke(string $uuid): JsonResponse
    {
        $comment = Comment::where('uuid', $uuid)->firstOrFail();
        $userId = auth()->id();

        if ($comment->user_id !== $userId) {
            return response()->sendError(
                'You are not authorized to delete this comment',
                403
            );
        }

        try {
            $comment->delete();

            return response()->sendResponse(
                null,
                null,
                'Comment deleted successfully',
                [],
                [],
                204
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
