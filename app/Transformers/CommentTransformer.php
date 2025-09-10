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
            'id' => $comment->id,
            'text' => $comment->text,
            'is_approver' => $comment->is_approver,
            'created_at' => $comment->created_at->toISOString(),
            'updated_at' => $comment->updated_at->toISOString(),
            'user' => $comment->user ? [
                'uuid' => $comment->user->uuid,
                'name' => $comment->user->name,
                'username' => $comment->user->username,
                'role' => $comment->user->role,
            ] : null,
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
