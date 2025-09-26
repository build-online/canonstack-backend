<?php

namespace App\Transformers;

use App\Models\ModelRepository;
use League\Fractal\TransformerAbstract;

class ModelRepositoryTransformer extends TransformerAbstract
{
    protected array $availableIncludes = [
        'repository'
    ];

    protected array $defaultIncludes = [
        'repository'
    ];

    public function transform(ModelRepository $model): array
    {
        return [
            'uuid' => $model->uuid,
        ];
    }

    public function includeRepository(ModelRepository $model)
    {
        if ($model->repository) {
            return $this->item($model->repository, new RepositoryTransformer());
        }
        return $this->null();
    }
}
