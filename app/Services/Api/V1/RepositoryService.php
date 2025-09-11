<?php

namespace App\Services\Api\V1;

use App\Models\Repository;
use App\Services\Api\V1\FileSystemService;

class RepositoryService
{
    private FileSystemService $fileSystemService;

    public function __construct(FileSystemService $fileSystemService)
    {
        $this->fileSystemService = $fileSystemService;
    }

    /**
     * Get complete file tree structure for a repository.
     * This returns a hierarchical tree useful for tree view components.
     */
    public function getFileTree(Repository $repository, int $maxDepth = 5): array
    {
        return [
            'tree' => $this->buildFileTree($repository, null, 0, $maxDepth),
            'stats' => $this->getRepositoryStats($repository),
        ];
    }

    /**
     * Build a hierarchical file tree.
     */
    public function buildFileTree(Repository $repository, ?string $parentPath, int $currentDepth, int $maxDepth): array
    {
        if ($currentDepth >= $maxDepth) {
            return [];
        }

        $query = $repository->files();
        
        if ($parentPath === null) {
            $query->root();
        } else {
            $query->where('parent_path', $parentPath);
        }
        
        $files = $query->orderBy('type', 'desc') // folders first
                      ->orderBy('name', 'asc')
                      ->get();

        return $files->map(function ($file) use ($repository, $currentDepth, $maxDepth) {
            $item = [
                'uuid' => $file->uuid,
                'name' => $file->name,
                'path' => $file->path,
                'type' => $file->type,
                'size' => $file->size,
                'human_size' => $file->human_size,
                'mime_type' => $file->mime_type,
                'parent_path' => $file->parent_path,
                'created_at' => $file->created_at->toISOString(),
                'is_downloadable' => $file->type === 'file' && !empty($file->file_ref),
                'is_previewable' => $file->type === 'file' ? $this->fileSystemService->isPreviewableFile($file) : false,
                'depth' => $currentDepth,
            ];

            // If it's a folder, recursively get children
            if ($file->type === 'folder') {
                $children = $this->buildFileTree($repository, $file->path, $currentDepth + 1, $maxDepth);
                $item['children'] = $children;
                $item['children_count'] = count($children);
                $item['has_children'] = count($children) > 0;
            } else {
                $item['children'] = [];
                $item['children_count'] = 0;
                $item['has_children'] = false;
            }

            return $item;
        })->toArray();
    }

    /**
     * Get overall repository statistics.
     */
    public function getRepositoryStats(Repository $repository): array
    {
        $allFiles = $repository->files;
        
        $folderCount = $allFiles->where('type', 'folder')->count();
        $fileCount = $allFiles->where('type', 'file')->count();
        $totalSize = $allFiles->where('type', 'file')->sum('size');
        
        // Calculate depth statistics
        $maxDepth = 0;
        foreach ($allFiles as $file) {
            $depth = substr_count($file->path, '/');
            $maxDepth = max($maxDepth, $depth);
        }

        return [
            'total_folders' => $folderCount,
            'total_files' => $fileCount,
            'total_items' => $folderCount + $fileCount,
            'total_size' => $totalSize,
            'total_size_human' => $this->fileSystemService->formatBytes($totalSize),
            'max_depth' => $maxDepth + 1,
        ];
    }
}
