<?php

namespace App\Transformers;

use App\Models\Repository;
use League\Fractal\TransformerAbstract;

class RepositoryTransformer extends TransformerAbstract
{
    protected array $availableIncludes = [
        'user',
        'category',
        'tags',
        'model',
        'approver',
        'comments'
    ];

    public function transform(Repository $repository): array
    {
        $data = [
            'uuid' => $repository->uuid,
            'name' => $repository->name,
            'description' => $repository->description,
            'status' => $repository->status,
            'file_ref' => $repository->file_ref,
            'downloads_count' => $repository->downloads_count ?? $repository->downloads()->count(),
            'likes_count' => $repository->likes_count ?? $repository->likes()->count(),
            'comments_count' => $repository->comments_count ?? $repository->comments()->count(),
            'created_at' => $repository->created_at->toISOString(),
            'updated_at' => $repository->updated_at->toISOString(),
        ];

        if ($repository->relationLoaded('likes') && 
            $repository->likes->isNotEmpty() && 
            $repository->likes->first()->relationLoaded('user')) {
            
            $likedByUserUuids = $repository->likes
                ->pluck('user.uuid')
                ->filter()
                ->values()
                ->toArray();
            
            $data['liked_by_user_uuids'] = $likedByUserUuids;
        }

        return $data;
    }

    public function includeUser(Repository $repository)
    {
        if ($repository->user) {
            return $this->item($repository->user, new UserTransformer());
        }
        return $this->null();
    }

    public function includeCategory(Repository $repository)
    {
        if ($repository->category) {
            return $this->item($repository->category, new CategoryTransformer());
        }
        return $this->null();
    }


    public function includeTags(Repository $repository)
    {
        return $this->collection($repository->tags, new TagTransformer());
    }

    public function includeModel(Repository $repository)
    {
        if ($repository->model) {
            return $this->item($repository->model, new ModelTransformer());
        }
        return $this->null();
    }

    public function includeApprover(Repository $repository)
    {
        if ($repository->approver) {
            return $this->item($repository->approver, new UserTransformer());
        }
        return $this->null();
    }

    public function includeComments(Repository $repository)
    {
        return $this->collection($repository->comments()->with('user')->orderBy('created_at', 'desc')->get(), new CommentTransformer());
    }
}
