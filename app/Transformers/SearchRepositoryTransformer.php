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
        'user',
        'category',
        'tags',
        'approver'
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
            'created_at' => $repository->created_at->toISOString(),
            'updated_at' => $repository->updated_at->toISOString(),
            'updated_at_human' => $repository->updated_at->diffForHumans(),
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

    /**
     * Include category.
     */
    public function includeCategory(Repository $repository)
    {
        if ($repository->category) {
            return $this->item($repository->category, new CategoryTransformer());
        }
        return $this->null();
    }

    /**
     * Include tags.
     */
    public function includeTags(Repository $repository)
    {
        return $this->collection($repository->tags, new TagTransformer());
    }

    /**
     * Include approver.
     */
    public function includeApprover(Repository $repository)
    {
        if ($repository->approver) {
            return $this->item($repository->approver, new UserTransformer());
        }
        return $this->null();
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
