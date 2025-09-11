<?php

namespace App\Http\Controllers\Api\V1\Comments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Comments\PatchCommentRequest;
use App\Models\Comment;
use App\Transformers\CommentTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class PatchCommentController extends Controller
{
    private CommentTransformer $commentTransformer;

    public function __construct(CommentTransformer $commentTransformer)
    {
        $this->commentTransformer = $commentTransformer;
    }

    /**
     * Update a comment's text.
     */
    public function __invoke(PatchCommentRequest $request, string $uuid): JsonResponse
    {
        $comment = Comment::where('uuid', $uuid)->with('user')->firstOrFail();
        $userId = auth()->id();

        if ($comment->user_id !== $userId) {
            return response()->sendError(
                'You are not authorized to update this comment',
                403
            );
        }

        try {
            $comment->update([
                'text' => $request->validated()['text']
            ]);

            return response()->sendResponse(
                $comment,
                $this->commentTransformer,
                'Comment updated successfully'
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
