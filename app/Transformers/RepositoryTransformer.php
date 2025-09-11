<?php

namespace App\Transformers;

use App\Models\Repository;
use League\Fractal\TransformerAbstract;

class RepositoryTransformer extends TransformerAbstract
{
    protected array $availableIncludes = [
        'user',
        'category',
        'religiousMovement',
        'tags',
        'model',
        'approver',
        'comments'
    ];

    public function transform(Repository $repository): array
    {
        return [
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

    public function includeReligiousMovement(Repository $repository)
    {
        if ($repository->religiousMovement) {
            return $this->item($repository->religiousMovement, new ReligiousMovementTransformer());
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
