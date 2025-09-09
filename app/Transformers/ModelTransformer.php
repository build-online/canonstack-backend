<?php

namespace App\Transformers;

use App\Models\ModelRepository;
use League\Fractal\TransformerAbstract;

class ModelTransformer extends TransformerAbstract
{
    public function transform(ModelRepository $model): array
    {
        return [
            'uuid' => $model->uuid,
            'repository_id' => $model->repository_id,
            'created_at' => $model->created_at->toISOString(),
            'updated_at' => $model->updated_at->toISOString(),
        ];
    }
}
