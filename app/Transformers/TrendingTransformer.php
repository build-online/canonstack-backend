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

            // Use pre-calculated stats if available, otherwise calculate on the fly
            if (isset($repository->stats)) {
                $totalSize = $repository->stats['total_size'];
                $totalSizeHuman = $repository->stats['total_size_human'];
            } else {
                // Fallback to calculating size manually
                if ($repository->relationLoaded('files')) {
                    $totalSize = $repository->files->where('type', 'file')->sum('size');
                } else {
                    $totalSize = $repository->files()->where('type', 'file')->sum('size');
                }
                $totalSizeHuman = $this->formatBytes($totalSize);
            }

            return [
                'uuid' => $repository->uuid,
                'name' => $repository->name,
                'description' => $repository->description,
                'status' => $repository->status,
                'type' => $type,
                'entity_uuid' => $entityUuid,
                'downloads_count' => $repository->downloads_count ?? $repository->downloads()->count(),
                'likes_count' => $repository->likes_count ?? $repository->likes()->count(),
                'comments_count' => $repository->comments_count ?? $repository->comments()->count(),
                'size' => $totalSize,
                'size_human' => $totalSizeHuman,
                'engagement_score' => $item['engagement_score'],
                'percentage_change' => $item['percentage_change'],
                'created_at' => $repository->created_at ? $repository->created_at->toISOString() : null,
                'updated_at' => $repository->updated_at ? $repository->updated_at->toISOString() : null,
                'updated_at_human' => $repository->updated_at ? $repository->updated_at->diffForHumans() : null,
                'user' => [
                    'uuid' => $repository->user->uuid,
                    'name' => $repository->user->name,
                    'username' => $repository->user->username,
                    'email' => $repository->user->email,
                    'role' => $repository->user->role,
                ],
                'category' => $repository->category ? [
                    'uuid' => $repository->category->uuid,
                    'name' => $repository->category->name,
                ] : null,
                'tags' => $repository->tags->map(function ($tag) {
                    return [
                        'uuid' => $tag->uuid,
                        'name' => $tag->name,
                    ];
                })->toArray(),
                'approver' => $repository->approver ? [
                    'uuid' => $repository->approver->uuid,
                    'name' => $repository->approver->name,
                    'username' => $repository->approver->username,
                    'email' => $repository->approver->email,
                    'role' => $repository->approver->role,
                ] : null,
            ];
        }, $repositories);
    }

    /**
     * Format bytes into human readable format.
     */
    private function formatBytes($bytes): string
    {
        if ($bytes == 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
