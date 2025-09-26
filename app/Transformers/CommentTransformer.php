<?php

namespace App\Transformers;

use App\Models\Comment;
use League\Fractal\TransformerAbstract;

class CommentTransformer extends TransformerAbstract
{
    /**
     * List of resources possible to include
     */
    protected array $availableIncludes = [
        'user'
    ];
    
    /**
     * Transform a comment.
     */
    public function transform(Comment $comment): array
    {
        return [
            'uuid' => $comment->uuid,
            'text' => $comment->text,
            'is_approver' => $comment->is_approver,
            'is_from_approval_process' => $comment->is_from_approval_process,
            'created_at' => $comment->created_at ? $comment->created_at->toISOString() : null,
            'updated_at' => $comment->updated_at ? $comment->updated_at->toISOString() : null,
            'created_at_human' => $comment->created_at ? $comment->created_at->diffForHumans() : null,
            'updated_at_human' => $comment->updated_at ? $comment->updated_at->diffForHumans() : null,
        ];
    }

    /**
     * Include user.
     */
    public function includeUser(Comment $comment)
    {
        if ($comment->user) {
            return $this->item($comment->user, new UserTransformer());
        }
        return $this->null();
    }
}
