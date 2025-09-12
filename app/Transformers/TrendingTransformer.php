<?php

namespace App\Transformers;

use App\Models\Repository;
use App\Transformers\UserTransformer;
use League\Fractal\TransformerAbstract;

class TrendingTransformer extends TransformerAbstract
{
    /**
     * Transform the trending data.
     *
     * @param array $trending
     * @return array
     */
    public function transform(array $trending): array
    {
        return [
            'period' => $trending['period'],
            'models' => $this->transformRepositoryList($trending['models']),
            'datasets' => $this->transformRepositoryList($trending['datasets']),
        ];
    }

    /**
     * Transform a list of trending repositories.
     */
    private function transformRepositoryList(array $repositories): array
    {
        return array_map(function ($item) {
            $repository = $item['repository'];
            
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
                'description' => $repository->description,
                'status' => $repository->status,
                'type' => $type,
                'entity_uuid' => $entityUuid,
                'engagement_score' => $item['engagement_score'],
                'percentage_change' => $item['percentage_change'],
                'created_at' => $repository->created_at->toISOString(),
                'user' => [
                    'uuid' => $repository->user->uuid,
                    'name' => $repository->user->name,
                    'username' => $repository->user->username,
                ],
            ];
        }, $repositories);
    }
}
