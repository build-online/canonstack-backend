<?php

namespace App\Http\Controllers\Api\V1\Datasets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Comments\PostCommentRequest;
use App\Models\Dataset;
use App\Models\Comment;
use App\Transformers\CommentTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class PostCommentController extends Controller
{
    private CommentTransformer $commentTransformer;

    public function __construct(CommentTransformer $commentTransformer)
    {
        $this->commentTransformer = $commentTransformer;
    }

    /**
     * Add a comment to a dataset repository.
     */
    public function __invoke(PostCommentRequest $request, string $uuid): JsonResponse
    {
        $dataset = Dataset::where('uuid', $uuid)->with('repository')->firstOrFail();
        $repository = $dataset->repository;
        $user = auth()->user();

        try {
            $isApprover = Comment::isApproverComment($user, $repository);
            $comment = Comment::create([
                'user_id' => $user->id,
                'repository_id' => $repository->id,
                'text' => $request->validated()['text'],
                'is_approver' => $isApprover,
            ]);

            $comment->load('user');

            return response()->sendResponse(
                $comment,
                $this->commentTransformer,
                'Comment added successfully',
                ['user'],
                [],
                201
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
