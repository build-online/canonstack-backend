<?php

namespace App\Transformers;

use App\Models\Comment;
use League\Fractal\TransformerAbstract;

class CommentWithRepositoryTransformer extends TransformerAbstract
{
    /**
     * Transform a comment with repository information.
     */
    public function transform(Comment $comment): array
    {
        $repository = $comment->repository;

        return [
            'uuid' => $comment->uuid,
            'text' => $comment->text,
            'is_approver' => $comment->is_approver,
            'is_from_approval_process' => $comment->is_from_approval_process,
            'created_at' => $comment->created_at->toISOString(),
            'created_at_human' => $comment->created_at->diffForHumans(),
            'user' => [
                'name' => $comment->user->name,
                'username' => $comment->user->username,
            ],
            'repository' => [
                'uuid' => $repository->uuid,
                'name' => $repository->name,
                'description' => $repository->description,
                'status' => $repository->status,
                'user' => [
                    'name' => $repository->user->name,
                    'username' => $repository->user->username,
                ],
            ]
        ];
    }
}
