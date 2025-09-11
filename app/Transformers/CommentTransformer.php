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
