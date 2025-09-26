<?php

namespace App\Http\Controllers\Api\V1\Comments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Comments\GetCommentsRequest;
use App\Models\Comment;
use App\Transformers\CommentWithRepositoryTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class GetCommentsController extends Controller
{
    private CommentWithRepositoryTransformer $commentTransformer;

    public function __construct(CommentWithRepositoryTransformer $commentTransformer)
    {
        $this->commentTransformer = $commentTransformer;
    }

    /**
     * Get all comments sorted by creation date and grouped by type (models/datasets).
     */
    public function __invoke(GetCommentsRequest $request): JsonResponse
    {
        try {
            $params = $request->getQueryParams();
            
            $comments = Comment::with([
                'user:id,uuid,name,username,email,role',
                'repository' => function ($query) {
                    $query->withCount(['downloads', 'likes', 'comments']);
                },
                'repository.user:id,uuid,name,username,email,role',
                'repository.category:id,uuid,name',
                'repository.tags:id,uuid,name',
                'repository.approver:id,uuid,name,username,email,role',
                'repository.model:id,uuid,repository_id',
                'repository.dataset:id,uuid,repository_id',
                'repository.files:id,repository_id,type,size'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(
                $params['per_page'],
                ['*'],
                'page',
                $params['page']
            );

            $groupedComments = $this->groupCommentsByType($comments);

            return response()->sendResponse(
                $groupedComments,
                null, // We'll handle transformation manually since we're grouping
                'Comments retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * Group comments by repository type while preserving pagination structure.
     */
    private function groupCommentsByType($paginatedComments): array
    {
        $modelComments = [];
        $datasetComments = [];

        foreach ($paginatedComments->items() as $comment) {
            $transformedComment = $this->commentTransformer->transform($comment);
            
            if ($comment->repository->model) {
                $modelComments[] = $transformedComment;
            } elseif ($comment->repository->dataset) {
                $datasetComments[] = $transformedComment;
            }
        }

        return [
            'models' => $modelComments,
            'datasets' => $datasetComments,
            'pagination' => [
                'total' => $paginatedComments->total(),
                'count' => $paginatedComments->count(),
                'per_page' => $paginatedComments->perPage(),
                'current_page' => $paginatedComments->currentPage(),
                'total_pages' => $paginatedComments->lastPage(),
                'links' => [
                    'next' => $paginatedComments->nextPageUrl(),
                    'prev' => $paginatedComments->previousPageUrl(),
                ]
            ]
        ];
    }
}
