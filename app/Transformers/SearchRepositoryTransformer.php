<?php

namespace App\Transformers;

use App\Models\Repository;
use League\Fractal\TransformerAbstract;

class SearchRepositoryTransformer extends TransformerAbstract
{
    /**
     * List of resources to automatically include
     */
    protected array $defaultIncludes = [
        'user'
    ];

    /**
     * List of resources possible to include
     */
    protected array $availableIncludes = [
        'user'
    ];

    /**
     * Transform a repository for search results.
     */
    public function transform(Repository $repository): array
    {
        $type = null;
        $entityUuid = null;

        if ($repository->model) {
            $type = 'model';
            $entityUuid = $repository->model->uuid;
        } elseif ($repository->dataset) {
            $type = 'dataset';
            $entityUuid = $repository->dataset->uuid;
        }

        return [
            'uuid' => $repository->uuid,
            'name' => $repository->name,
            'status' => $repository->status,
            'type' => $type,
            'entity_uuid' => $entityUuid,
            'created_at' => $repository->created_at->toISOString(),
        ];
    }

    /**
     * Include user.
     */
    public function includeUser(Repository $repository)
    {
        if ($repository->user) {
            return $this->item($repository->user, new UserTransformer());
        }
        return $this->null();
    }
}
