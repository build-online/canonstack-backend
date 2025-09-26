<?php

namespace App\Transformers;

use App\Models\Dataset;
use League\Fractal\TransformerAbstract;

class DatasetTransformer extends TransformerAbstract
{
    /**
     * List of resources to automatically include
     */
    protected array $defaultIncludes = [
        'repository'
    ];

    /**
     * List of resources possible to include
     */
    protected array $availableIncludes = [
        'repository'
    ];

    /**
     * Transform a dataset.
     */
    public function transform(Dataset $dataset): array
    {
        return [
            'uuid' => $dataset->uuid,
            'is_featured' => $dataset->is_featured,
        ];
    }

    /**
     * Include repository.
     */
    public function includeRepository(Dataset $dataset)
    {
        if ($dataset->repository) {
            return $this->item($dataset->repository, new RepositoryTransformer());
        }
        return $this->null();
    }
}
